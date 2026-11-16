<?php

namespace App\Paiement;

use App\Models\Inscription;
use Illuminate\Support\Facades\URL;

/**
 * Paiement simulé, pour la démonstration : une page qui imite celle d'un prestataire.
 * Les adresses sont signées et expirent : on ne confirme pas un paiement en tapant une URL.
 */
class PasserelleSimulee implements Passerelle
{
    public function nom(): string
    {
        return 'simulee';
    }

    public function adressePaiement(Inscription $inscription): string
    {
        return URL::temporarySignedRoute('paiement-simule.page', now()->addMinutes(30), ['inscription' => $inscription]);
    }
}
