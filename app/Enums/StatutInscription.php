<?php

namespace App\Enums;

enum StatutInscription: string
{
    /** Place réservée le temps de payer (30 minutes). */
    case EnAttentePaiement = 'en_attente_paiement';

    /** Payée : le coureur a son dossard. */
    case Payee = 'payee';

    /** Épreuve complète : le coureur attend son tour. */
    case ListeAttente = 'liste_attente';

    /** Une place s'est libérée : elle lui est proposée pendant 48 heures. */
    case OffreEnCours = 'offre_en_cours';

    /** Délai de paiement dépassé : la place repart. */
    case Expiree = 'expiree';

    case Annulee = 'annulee';

    /** Les statuts qui occupent une place sur l'épreuve. */
    public static function occupantUnePlace(): array
    {
        return [self::Payee, self::EnAttentePaiement, self::OffreEnCours];
    }

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttentePaiement => 'Place réservée, en attente de paiement',
            self::Payee => 'Inscription confirmée',
            self::ListeAttente => 'Sur liste d\'attente',
            self::OffreEnCours => 'Place proposée, en attente de paiement',
            self::Expiree => 'Délai de paiement dépassé',
            self::Annulee => 'Annulée',
        };
    }

    public function attendPaiement(): bool
    {
        return in_array($this, [self::EnAttentePaiement, self::OffreEnCours], true);
    }
}
