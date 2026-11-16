<?php

namespace App\Http\Controllers;

use App\Enums\Justificatif;
use App\Http\Requests\DemandeInscription;
use App\Models\Epreuve;
use App\Models\Inscription;
use App\Paiement\Passerelle;
use App\Services\Guichet;
use App\Services\InscriptionRefusee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InscriptionController extends Controller
{
    public function accueil(): View
    {
        return view('accueil', ['epreuves' => Epreuve::query()->orderBy('depart_le')->orderByDesc('distance_km')->get()]);
    }

    public function formulaire(Epreuve $epreuve): View
    {
        return view('inscription', ['epreuve' => $epreuve, 'justificatifs' => Justificatif::cases()]);
    }

    public function enregistrer(DemandeInscription $demande, Epreuve $epreuve, Guichet $guichet): RedirectResponse
    {
        try {
            $inscription = $guichet->inscrire($epreuve, $demande->donnees());
        } catch (InscriptionRefusee $refus) {
            return back()->withErrors([$refus->champ => $refus->getMessage()])->withInput();
        }

        return redirect()->route('inscriptions.show', $inscription);
    }

    /** La page du coureur : on y arrive par la référence, reçue à l'inscription et dans les e-mails. */
    public function suivi(Inscription $inscription): View
    {
        $inscription->load('epreuve');

        return view('suivi', [
            'inscription' => $inscription,
            'devant' => $inscription->position_attente === null ? 0 : $inscription->epreuve->inscriptions()
                ->where('statut', 'liste_attente')->where('position_attente', '<', $inscription->position_attente)->count(),
        ]);
    }

    public function fournirPps(Request $request, Inscription $inscription, Guichet $guichet): RedirectResponse
    {
        $pps = $request->validate([
            'justificatif_numero' => ['required', 'string', 'max:40'],
            'justificatif_date' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'required' => 'Ce champ est obligatoire.',
            'date' => 'Cette date n\'est pas valide.',
            'justificatif_date.before_or_equal' => 'La date du PPS ne peut pas être dans le futur.',
        ]);
        try {
            $guichet->fournirPps($inscription->load('epreuve'), $pps['justificatif_numero'], $pps['justificatif_date']);
        } catch (InscriptionRefusee $refus) {
            return back()->withErrors(['justificatif_date' => $refus->getMessage()])->withInput();
        }

        return back()->with('succes', 'PPS enregistré : l\'organisation le vérifiera avant la course.');
    }

    public function payer(Inscription $inscription, Passerelle $passerelle): RedirectResponse
    {
        if (! $inscription->reservationEnCours()) {
            return redirect()->route('inscriptions.show', $inscription)
                ->withErrors(['paiement' => 'Il n\'y a rien à payer : la place n\'est pas, ou plus, réservée.']);
        }

        return redirect()->away($passerelle->adressePaiement($inscription));
    }
}
