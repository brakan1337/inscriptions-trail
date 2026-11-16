<?php

namespace App\Paiement;

use App\Models\Inscription;

/**
 * Le prestataire de paiement. L'application ne connaît que cette interface :
 * passer de la simulation à Stripe, HelloAsso ou une banque ne touche qu'une classe.
 */
interface Passerelle
{
    public function nom(): string;

    /** L'adresse où envoyer le coureur pour qu'il paie. */
    public function adressePaiement(Inscription $inscription): string;
}
