<?php

namespace Tests\Feature;

use App\Enums\StatutInscription;
use App\Enums\StatutJustificatif;
use App\Mail\PlaceProposee;
use App\Models\Epreuve;
use App\Models\Inscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrganisationTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_back_office_exige_une_connexion(): void
    {
        $epreuve = Epreuve::factory()->create();

        $this->get(route('organisation.tableau'))->assertRedirect(route('login'));
        $this->get(route('organisation.export', $epreuve))->assertRedirect(route('login'));
    }

    public function test_connexion_refusee_puis_acceptee(): void
    {
        $user = User::factory()->create(['email' => 'orga@exemple.test', 'password' => 'mot-de-passe-de-test']);

        $this->post(route('login'), ['email' => 'orga@exemple.test', 'password' => 'faux'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);
        $this->post(route('login'), ['email' => 'orga@exemple.test', 'password' => 'mot-de-passe-de-test'])
            ->assertRedirect(route('organisation.tableau'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_le_tableau_de_bord_montre_le_remplissage_et_la_recette(): void
    {
        $epreuve = Epreuve::factory()->create(['nom' => 'Grand trail', 'quota' => 3, 'prix_centimes' => 4800]);
        Inscription::factory()->for($epreuve)->payee(101)->create(['montant_centimes' => 4800]);
        Inscription::factory()->for($epreuve)->payee(102)->create(['montant_centimes' => 4800, 'justificatif_statut' => StatutJustificatif::Valide]);
        Inscription::factory()->for($epreuve)->create();

        $this->actingAs(User::factory()->create())->get(route('organisation.tableau'))
            ->assertOk()->assertSeeInOrder(['Grand trail', 'Payées', '2', '/ 3', 'en cours de paiement', '1', 'Justificatifs à vérifier', '1', 'Recette', '96 €']);
    }

    public function test_valider_et_refuser_un_justificatif(): void
    {
        $orga = User::factory()->create();
        $a = Inscription::factory()->payee(101)->create();
        $b = Inscription::factory()->payee(102)->create(['epreuve_id' => $a->epreuve_id]);

        $this->actingAs($orga)->post(route('organisation.justificatif', $a), ['decision' => 'valider'])->assertSessionHas('succes');
        $this->actingAs($orga)->post(route('organisation.justificatif', $b), ['decision' => 'refuser'])
            ->assertSessionHasErrors(['motif' => 'Indiquez au coureur pourquoi son justificatif est refusé.']);
        $this->actingAs($orga)->post(route('organisation.justificatif', $b), ['decision' => 'refuser', 'motif' => 'Licence expirée']);

        $this->assertSame(StatutJustificatif::Valide, $a->refresh()->justificatif_statut);
        $this->assertSame(StatutJustificatif::Refuse, $b->refresh()->justificatif_statut);
        $this->get(route('inscriptions.show', $b))->assertSee('Licence expirée');
    }

    public function test_annuler_une_inscription_propose_la_place_a_la_liste_d_attente(): void
    {
        Mail::fake();
        $epreuve = Epreuve::factory()->create(['quota' => 1]);
        $inscrit = Inscription::factory()->for($epreuve)->payee(101)->create();
        $attente = Inscription::factory()->for($epreuve)->enAttente(1)->create();

        $this->actingAs(User::factory()->create())->post(route('organisation.annuler', $inscrit))->assertSessionHas('succes');

        $this->assertSame(StatutInscription::Annulee, $inscrit->refresh()->statut);
        $this->assertNull($inscrit->dossard);
        $this->assertSame(StatutInscription::OffreEnCours, $attente->refresh()->statut);
        Mail::assertSent(PlaceProposee::class);
    }

    public function test_l_export_du_chronometreur(): void
    {
        $epreuve = Epreuve::factory()->create(['nom' => 'Trail découverte', 'slug' => 'trail-decouverte', 'depart_le' => '2027-05-15 09:30']);
        Inscription::factory()->for($epreuve)->payee(1002)->create(['nom' => 'Hourcade', 'prenom' => 'Léa', 'naissance' => '1984-03-01', 'club' => 'AC Dax']);
        Inscription::factory()->for($epreuve)->payee(1001)->create(['nom' => 'Peyrot', 'prenom' => 'Jon', 'sexe' => 'H', 'naissance' => '2005-07-20']);
        Inscription::factory()->for($epreuve)->enAttente(1)->create(['nom' => 'Absent']);

        $reponse = $this->actingAs(User::factory()->create())->get(route('organisation.export', $epreuve));

        $reponse->assertOk()->assertDownload('chronometrage-trail-decouverte.csv');
        $lignes = explode("\n", trim($reponse->streamedContent()));
        $this->assertStringStartsWith("\u{FEFF}dossard;nom;prenom;sexe;naissance;categorie;club;epreuve", $lignes[0]);
        $this->assertSame('1001;PEYROT;Jon;H;20/07/2005;ES;;"Trail découverte"', $lignes[1]);
        $this->assertSame('1002;HOURCADE;Léa;F;01/03/1984;M1;"AC Dax";"Trail découverte"', $lignes[2]);
        $this->assertCount(3, $lignes);
    }

    public function test_la_liste_des_inscrits_se_filtre(): void
    {
        $epreuve = Epreuve::factory()->create();
        Inscription::factory()->for($epreuve)->payee(101)->create(['nom' => 'Darrigade']);
        Inscription::factory()->for($epreuve)->enAttente(1)->create(['nom' => 'Larrieu']);

        $this->actingAs(User::factory()->create())
            ->get(route('organisation.inscrits', ['statut' => 'liste_attente']))
            ->assertOk()->assertSee('LARRIEU')->assertDontSee('DARRIGADE');
    }
}
