<?php

namespace App\Http\Controllers;

use App\Models\Inscription;
use App\Services\Guichet;
use App\Services\InscriptionRefusee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Imite le site d'un prestataire de paiement. Toutes ses adresses sont signées (middleware « signed »). */
class PaiementSimuleController extends Controller
{
    public function page(Inscription $inscription): View
    {
        return view('paiement-simule', [
            'inscription' => $inscription->load('epreuve'),
            'confirmer' => URL::temporarySignedRoute('paiement-simule.confirmer', now()->addMinutes(30), ['inscription' => $inscription]),
        ]);
    }

    public function confirmer(Inscription $inscription, Guichet $guichet): RedirectResponse
    {
        try {
            $guichet->confirmerPaiement($inscription, 'simulee', 'SIM-'.Str::upper(Str::random(14)));
        } catch (InscriptionRefusee $refus) {
            return redirect()->route('inscriptions.show', $inscription)->withErrors(['paiement' => $refus->getMessage()]);
        }

        return redirect()->route('inscriptions.show', $inscription)->with('succes', 'Paiement reçu : votre inscription est confirmée.');
    }
}
