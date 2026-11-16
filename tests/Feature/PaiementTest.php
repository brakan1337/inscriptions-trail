<?php

namespace Tests\Feature;

use App\Enums\StatutInscription;
use App\Mail\InscriptionConfirmee;
use App\Models\Epreuve;
use App\Models\Inscription;
use App\Services\Guichet;
use App\Services\InscriptionRefusee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PaiementTest extends TestCase
{
    use RefreshDatabase;

    public function test_du_bouton_payer_au_dossard(): void
    {
        Mail::fake();
        $inscription = Inscription::factory()->create();

        $versPasserelle = $this->post(route('inscriptions.payer', $inscription));
        $adresse = $versPasserelle->headers->get('Location');
        $this->assertStringContainsString('signature=', $adresse);

        $page = $this->get($adresse)->assertOk()->assertSee('Simuler un paiement réussi');
        preg_match('/action="([^"]+)"/', $page->getContent(), $formulaire);
        $this->post(html_entity_decode($formulaire[1]))->assertRedirect(route('inscriptions.show', $inscription));

        $inscription->refresh();
        $this->assertSame(StatutInscription::Payee, $inscription->statut);
        $this->assertSame(101, $inscription->dossard);
        $this->assertNull($inscription->place_reservee_jusqu_au);
        $this->assertDatabaseCount('paiements', 1);
        Mail::assertSent(InscriptionConfirmee::class, fn ($m) => $m->hasTo($inscription->email) && $m->inscription->dossard === 101);
        $this->get(route('inscriptions.show', $inscription))->assertSee('Votre dossard')->assertSee('101');
    }

    public function test_une_adresse_de_paiement_non_signee_ou_retouchee_est_refusee(): void
    {
        $inscription = Inscription::factory()->create();

        $this->post(route('paiement-simule.confirmer', $inscription))->assertForbidden();
        $signee = URL::temporarySignedRoute('paiement-simule.confirmer', now()->addMinutes(30), ['inscription' => $inscription]);
        $autre = Inscription::factory()->create();
        $this->post(str_replace($inscription->reference, $autre->reference, $signee))->assertForbidden();
        $this->assertNull($autre->refresh()->dossard);
    }

    public function test_une_confirmation_en_double_ne_paie_pas_deux_fois(): void
    {
        Mail::fake();
        $inscription = Inscription::factory()->create();
        $guichet = app(Guichet::class);

        $guichet->confirmerPaiement($inscription, 'simulee', 'SIM-1');
        $guichet->confirmerPaiement($inscription, 'simulee', 'SIM-2');

        $this->assertDatabaseCount('paiements', 1);
        Mail::assertSentCount(1);
    }

    public function test_le_plus_petit_dossard_libre_est_attribue_meme_apres_une_annulation(): void
    {
        Mail::fake();
        $epreuve = Epreuve::factory()->create(['quota' => 10]);
        $guichet = app(Guichet::class);
        $premier = Inscription::factory()->for($epreuve)->payee(101)->create();
        Inscription::factory()->for($epreuve)->payee(102)->create();

        $guichet->annuler($premier);
        $nouvelle = $guichet->confirmerPaiement(Inscription::factory()->for($epreuve)->create(), 'simulee', 'SIM-3');

        $this->assertSame(101, $nouvelle->dossard);
    }

    public function test_un_paiement_apres_le_delai_est_refuse(): void
    {
        $inscription = Inscription::factory()->create(['place_reservee_jusqu_au' => now()->subMinute()]);

        $this->expectException(InscriptionRefusee::class);
        app(Guichet::class)->confirmerPaiement($inscription, 'simulee', 'SIM-4');
    }

    public function test_rien_a_payer_sur_une_liste_d_attente(): void
    {
        $inscription = Inscription::factory()->enAttente(1)->create();

        $this->post(route('inscriptions.payer', $inscription))
            ->assertRedirect(route('inscriptions.show', $inscription))
            ->assertSessionHasErrors('paiement');
    }
}
