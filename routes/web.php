<?php

use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\PaiementSimuleController;
use Illuminate\Support\Facades\Route;

// Côté coureur
Route::get('/', [InscriptionController::class, 'accueil'])->name('accueil');
Route::get('/epreuves/{epreuve}/inscription', [InscriptionController::class, 'formulaire'])->name('inscription.formulaire');
Route::post('/epreuves/{epreuve}/inscription', [InscriptionController::class, 'enregistrer'])
    ->middleware('throttle:10,1')->name('inscription.enregistrer');
Route::get('/inscriptions/{inscription}', [InscriptionController::class, 'suivi'])->name('inscriptions.show');
Route::post('/inscriptions/{inscription}/pps', [InscriptionController::class, 'fournirPps'])
    ->middleware('throttle:10,1')->name('inscriptions.pps');
Route::post('/inscriptions/{inscription}/payer', [InscriptionController::class, 'payer'])->name('inscriptions.payer');

// Le faux prestataire de paiement : adresses signées et limitées dans le temps
Route::middleware('signed')->group(function () {
    Route::get('/paiement-simule/{inscription}', [PaiementSimuleController::class, 'page'])->name('paiement-simule.page');
    Route::post('/paiement-simule/{inscription}', [PaiementSimuleController::class, 'confirmer'])->name('paiement-simule.confirmer');
});

// Côté organisation
Route::get('/organisation/connexion', [OrganisationController::class, 'connexion'])->middleware('guest')->name('login');
Route::post('/organisation/connexion', [OrganisationController::class, 'connecter'])->middleware(['guest', 'throttle:5,1']);
Route::middleware('auth')->prefix('organisation')->name('organisation.')->group(function () {
    Route::post('/deconnexion', [OrganisationController::class, 'deconnecter'])->name('deconnexion');
    Route::get('/', [OrganisationController::class, 'tableau'])->name('tableau');
    Route::get('/inscrits', [OrganisationController::class, 'inscrits'])->name('inscrits');
    Route::post('/inscrits/{inscription}/justificatif', [OrganisationController::class, 'justificatif'])->name('justificatif');
    Route::post('/inscrits/{inscription}/annuler', [OrganisationController::class, 'annuler'])->name('annuler');
    Route::get('/epreuves/{epreuve}/export', [OrganisationController::class, 'export'])->name('export');
});
