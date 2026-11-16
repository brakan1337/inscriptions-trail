<?php

namespace App\Services;

use App\Enums\Justificatif;
use App\Enums\StatutInscription;
use App\Enums\StatutJustificatif;
use App\Mail\InscriptionConfirmee;
use App\Mail\PlaceProposee;
use App\Models\Epreuve;
use App\Models\Inscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Toutes les règles des inscriptions : places, liste d'attente, dossards.
 *
 * Chaque opération qui touche aux places se fait dans une transaction qui verrouille
 * la ligne de l'épreuve. Deux coureurs qui valident en même temps la dernière place
 * ne peuvent pas l'obtenir tous les deux.
 */
class Guichet
{
    /** Le temps laissé pour payer après avoir réservé sa place. */
    public const DELAI_PAIEMENT_MINUTES = 30;

    /** Le temps laissé à un coureur de la liste d'attente quand une place se libère. */
    public const DELAI_OFFRE_HEURES = 48;

    /**
     * Enregistre une demande d'inscription : place réservée s'il en reste, liste d'attente sinon.
     *
     * @param  array{nom: string, prenom: string, sexe: string, naissance: string, email: string, club?: ?string,
     *     justificatif_type: string, justificatif_numero: string, justificatif_date?: ?string}  $donnees
     */
    public function inscrire(Epreuve $epreuve, array $donnees): Inscription
    {
        $naissance = Carbon::parse($donnees['naissance']);
        $this->verifierRegles($epreuve, $naissance, $donnees);

        return DB::transaction(function () use ($epreuve, $donnees, $naissance) {
            $epreuve = Epreuve::query()->lockForUpdate()->findOrFail($epreuve->id);
            $this->refuserDoublon($epreuve, $donnees, $naissance);

            $inscription = new Inscription([
                ...$donnees,
                'epreuve_id' => $epreuve->id,
                'reference' => Str::upper(Str::random(12)),
                'justificatif_statut' => StatutJustificatif::AVerifier,
                'montant_centimes' => $epreuve->prix_centimes,
            ]);

            if ($epreuve->placesLibres() > 0) {
                $inscription->statut = StatutInscription::EnAttentePaiement;
                $inscription->place_reservee_jusqu_au = now()->addMinutes(self::DELAI_PAIEMENT_MINUTES);
            } else {
                $inscription->statut = StatutInscription::ListeAttente;
                $inscription->position_attente = (int) $epreuve->inscriptions()
                    ->where('statut', StatutInscription::ListeAttente)->max('position_attente') + 1;
            }
            $inscription->save();

            return $inscription;
        });
    }

    /**
     * Le paiement est confirmé par la passerelle : le coureur reçoit le plus petit dossard libre de sa plage.
     */
    public function confirmerPaiement(Inscription $inscription, string $passerelle, string $referenceExterne): Inscription
    {
        $confirmee = DB::transaction(function () use ($inscription, $passerelle, $referenceExterne) {
            $epreuve = Epreuve::query()->lockForUpdate()->findOrFail($inscription->epreuve_id);
            $inscription = Inscription::query()->lockForUpdate()->findOrFail($inscription->id);

            if ($inscription->statut === StatutInscription::Payee) {
                return $inscription; // la passerelle peut confirmer deux fois : on ne paie pas deux fois
            }
            if (! $inscription->reservationEnCours()) {
                throw new InscriptionRefusee('Le délai de paiement est dépassé : la place a été remise en jeu.', 'paiement');
            }

            $inscription->paiements()->create([
                'passerelle' => $passerelle,
                'reference_externe' => $referenceExterne,
                'montant_centimes' => $inscription->montant_centimes,
            ]);
            $inscription->forceFill([
                'statut' => StatutInscription::Payee,
                'dossard' => $this->premierDossardLibre($epreuve),
                'payee_le' => now(),
                'place_reservee_jusqu_au' => null,
            ])->save();

            return $inscription;
        });

        if ($confirmee->wasChanged('statut')) {
            Mail::to($confirmee->email)->send(new InscriptionConfirmee($confirmee));
        }

        return $confirmee;
    }

    /** L'organisateur annule une inscription : le dossard redevient libre et la place est proposée à la liste d'attente. */
    public function annuler(Inscription $inscription): void
    {
        $inscription->forceFill([
            'statut' => StatutInscription::Annulee,
            'dossard' => null,
            'place_reservee_jusqu_au' => null,
            'position_attente' => null,
        ])->save();

        $this->libererPlaces($inscription->epreuve);
    }

    /**
     * Lancée chaque minute par le planificateur : les réservations échues expirent,
     * puis chaque place libre est proposée au premier de la liste d'attente.
     *
     * @return array{expirees: int, proposees: int}
     */
    public function libererPlaces(?Epreuve $seulement = null): array
    {
        $expirees = Inscription::query()
            ->whereIn('statut', [StatutInscription::EnAttentePaiement, StatutInscription::OffreEnCours])
            ->where('place_reservee_jusqu_au', '<=', now())
            ->when($seulement, fn ($q) => $q->where('epreuve_id', $seulement->id))
            ->update(['statut' => StatutInscription::Expiree, 'place_reservee_jusqu_au' => null]);

        $offres = collect();
        $epreuves = $seulement ? collect([$seulement]) : Epreuve::all();
        foreach ($epreuves as $epreuve) {
            DB::transaction(function () use ($epreuve, $offres) {
                $epreuve = Epreuve::query()->lockForUpdate()->findOrFail($epreuve->id);
                $libres = $epreuve->placesLibres();
                if ($libres === 0 || ! $epreuve->inscriptionsOuvertes()) {
                    return;
                }
                $suivants = $epreuve->inscriptions()
                    ->where('statut', StatutInscription::ListeAttente)
                    ->orderBy('position_attente')
                    ->limit($libres)
                    ->get();
                foreach ($suivants as $suivant) {
                    $suivant->forceFill([
                        'statut' => StatutInscription::OffreEnCours,
                        'place_reservee_jusqu_au' => now()->addHours(self::DELAI_OFFRE_HEURES),
                        'position_attente' => null,
                    ])->save();
                    $offres->push($suivant);
                }
            });
        }

        // Les e-mails partent une fois la transaction validée : jamais d'offre annoncée puis annulée.
        $offres->each(fn (Inscription $i) => Mail::to($i->email)->send(new PlaceProposee($i)));

        return ['expirees' => $expirees, 'proposees' => $offres->count()];
    }

    public function validerJustificatif(Inscription $inscription): void
    {
        $inscription->forceFill(['justificatif_statut' => StatutJustificatif::Valide, 'justificatif_motif' => null])->save();
    }

    public function refuserJustificatif(Inscription $inscription, string $motif): void
    {
        $inscription->forceFill(['justificatif_statut' => StatutJustificatif::Refuse, 'justificatif_motif' => $motif])->save();
    }

    private function verifierRegles(Epreuve $epreuve, Carbon $naissance, array $donnees): void
    {
        if (! $epreuve->inscriptionsOuvertes()) {
            throw new InscriptionRefusee($epreuve->inscriptions_ouvertes_le->isFuture()
                ? 'Les inscriptions ouvrent le '.$epreuve->inscriptions_ouvertes_le->translatedFormat('j F Y à H\hi').'.'
                : 'Les inscriptions sont closes.');
        }

        $age = (int) $naissance->diffInYears($epreuve->depart_le);
        if ($age < $epreuve->age_minimum) {
            throw new InscriptionRefusee("Cette épreuve est ouverte à partir de {$epreuve->age_minimum} ans le jour de la course.", 'naissance');
        }

        if (Justificatif::from($donnees['justificatif_type']) === Justificatif::Pps && ! empty($donnees['justificatif_date'])) {
            $this->verifierValiditePps($epreuve, Carbon::parse($donnees['justificatif_date']));
        }
    }

    /**
     * Un PPS obtenu trop tôt aura expiré le jour de la course. Le coureur peut s'inscrire sans,
     * puis le fournir à partir de la date donnée par debutFenetrePps().
     */
    private function verifierValiditePps(Epreuve $epreuve, Carbon $delivre): void
    {
        $finValidite = $delivre->copy()->addMonthsNoOverflow(Justificatif::VALIDITE_PPS_MOIS);
        if ($finValidite->lt($epreuve->depart_le->copy()->startOfDay())) {
            throw new InscriptionRefusee(
                'Ce PPS ne sera plus valable le jour de la course (valable jusqu\'au '.$finValidite->format('d/m/Y').'). '
                .'Inscrivez-vous sans, et ajoutez-le depuis votre page de suivi à partir du '
                .self::debutFenetrePps($epreuve)->format('d/m/Y').'.',
                'justificatif_date'
            );
        }
    }

    /** Le premier jour où un PPS obtenu sera encore valable le jour de la course. */
    public static function debutFenetrePps(Epreuve $epreuve): Carbon
    {
        return $epreuve->depart_le->copy()->startOfDay()->subMonthsNoOverflow(Justificatif::VALIDITE_PPS_MOIS);
    }

    /** Le coureur ajoute son PPS après coup, depuis sa page de suivi. */
    public function fournirPps(Inscription $inscription, string $numero, string $date): void
    {
        $delivre = Carbon::parse($date);
        $this->verifierValiditePps($inscription->epreuve, $delivre);
        $inscription->forceFill([
            'justificatif_type' => Justificatif::Pps,
            'justificatif_numero' => $numero,
            'justificatif_date' => $delivre,
            'justificatif_statut' => StatutJustificatif::AVerifier,
            'justificatif_motif' => null,
        ])->save();
    }

    private function refuserDoublon(Epreuve $epreuve, array $donnees, Carbon $naissance): void
    {
        $existe = $epreuve->inscriptions()
            ->whereIn('statut', [...StatutInscription::occupantUnePlace(), StatutInscription::ListeAttente])
            ->whereRaw('lower(nom) = ? and lower(prenom) = ?', [mb_strtolower($donnees['nom']), mb_strtolower($donnees['prenom'])])
            ->whereDate('naissance', $naissance)
            ->exists();
        if ($existe) {
            throw new InscriptionRefusee('Ce coureur est déjà inscrit sur cette épreuve.', 'nom');
        }
    }

    private function premierDossardLibre(Epreuve $epreuve): int
    {
        $pris = $epreuve->inscriptions()->whereNotNull('dossard')->pluck('dossard')->flip();
        for ($n = $epreuve->dossard_premier; $n <= $epreuve->dossard_dernier; $n++) {
            if (! $pris->has($n)) {
                return $n;
            }
        }

        throw new InscriptionRefusee('Plus aucun dossard libre dans la plage de cette épreuve : prévenez l\'organisation.', 'paiement');
    }
}
