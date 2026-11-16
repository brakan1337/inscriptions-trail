<?php

namespace Tests\Feature;

use App\Enums\StatutInscription;
use App\Mail\PlaceProposee;
use App\Models\Epreuve;
use App\Models\Inscription;
use App\Services\Guichet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ListeAttenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_reservation_non_payee_libere_sa_place_pour_le_premier_en_attente(): void
    {
        Mail::fake();
        $epreuve = Epreuve::factory()->create(['quota' => 1]);
        $oublie = Inscription::factory()->for($epreuve)->create(['place_reservee_jusqu_au' => now()->subMinute()]);
        $premier = Inscription::factory()->for($epreuve)->enAttente(1)->create();
        $second = Inscription::factory()->for($epreuve)->enAttente(2)->create();

        $this->artisan('inscriptions:liberer')
            ->expectsOutput('1 réservation(s) expirée(s), 1 place(s) proposée(s).')
            ->assertSuccessful();

        $this->assertSame(StatutInscription::Expiree, $oublie->refresh()->statut);
        $this->assertSame(StatutInscription::OffreEnCours, $premier->refresh()->statut);
        $this->assertEqualsWithDelta(now()->addHours(48)->timestamp, $premier->place_reservee_jusqu_au->timestamp, 5);
        $this->assertSame(StatutInscription::ListeAttente, $second->refresh()->statut);
        Mail::assertSent(PlaceProposee::class, fn ($m) => $m->hasTo($premier->email));
        Mail::assertSentCount(1);
    }

    public function test_une_offre_non_payee_passe_au_suivant(): void
    {
        Mail::fake();
        $epreuve = Epreuve::factory()->create(['quota' => 1]);
        $premier = Inscription::factory()->for($epreuve)->create([
            'statut' => StatutInscription::OffreEnCours, 'place_reservee_jusqu_au' => now()->subMinute(),
        ]);
        $second = Inscription::factory()->for($epreuve)->enAttente(2)->create();

        app(Guichet::class)->libererPlaces();

        $this->assertSame(StatutInscription::Expiree, $premier->refresh()->statut);
        $this->assertSame(StatutInscription::OffreEnCours, $second->refresh()->statut);
    }

    public function test_une_place_offerte_se_paie_comme_une_autre(): void
    {
        Mail::fake();
        $epreuve = Epreuve::factory()->create(['quota' => 1]);
        Inscription::factory()->for($epreuve)->create(['place_reservee_jusqu_au' => now()->subMinute()]);
        $premier = Inscription::factory()->for($epreuve)->enAttente(1)->create();
        $guichet = app(Guichet::class);

        $guichet->libererPlaces();
        $guichet->confirmerPaiement($premier->refresh(), 'simulee', 'SIM-OFFRE');

        $this->assertSame(StatutInscription::Payee, $premier->refresh()->statut);
        $this->assertSame(101, $premier->dossard);
    }

    public function test_tant_que_l_epreuve_est_pleine_personne_n_est_sollicite(): void
    {
        Mail::fake();
        $epreuve = Epreuve::factory()->create(['quota' => 1]);
        Inscription::factory()->for($epreuve)->payee(101)->create();
        Inscription::factory()->for($epreuve)->enAttente(1)->create();

        $this->assertSame(['expirees' => 0, 'proposees' => 0], app(Guichet::class)->libererPlaces());
        Mail::assertNothingSent();
    }

    public function test_apres_la_cloture_la_liste_d_attente_n_est_plus_sollicitee(): void
    {
        Mail::fake();
        $epreuve = Epreuve::factory()->create(['quota' => 1, 'inscriptions_closes_le' => now()->subDay()]);
        Inscription::factory()->for($epreuve)->create(['place_reservee_jusqu_au' => now()->subMinute()]);
        $attente = Inscription::factory()->for($epreuve)->enAttente(1)->create();

        app(Guichet::class)->libererPlaces();

        $this->assertSame(StatutInscription::ListeAttente, $attente->refresh()->statut);
        Mail::assertNothingSent();
    }
}
