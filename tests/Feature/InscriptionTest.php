<?php

namespace Tests\Feature;

use App\Enums\StatutInscription;
use App\Models\Epreuve;
use App\Models\Inscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function demande(array $champs = []): array
    {
        return [
            'nom' => 'Lacoste', 'prenom' => 'Maïa', 'sexe' => 'F', 'naissance' => '1991-06-02',
            'email' => 'maia@exemple.test', 'club' => '', 'justificatif_type' => 'licence',
            'justificatif_numero' => '9876543', 'justificatif_date' => '', 'reglement' => '1', ...$champs,
        ];
    }

    private function inscrire(Epreuve $epreuve, array $champs = [])
    {
        return $this->post(route('inscription.enregistrer', $epreuve), $this->demande($champs));
    }

    public function test_une_place_libre_est_reservee_le_temps_de_payer(): void
    {
        $epreuve = Epreuve::factory()->create();

        $reponse = $this->inscrire($epreuve);

        $inscription = Inscription::query()->sole();
        $reponse->assertRedirect(route('inscriptions.show', $inscription));
        $this->assertSame(StatutInscription::EnAttentePaiement, $inscription->statut);
        $this->assertEqualsWithDelta(now()->addMinutes(30)->timestamp, $inscription->place_reservee_jusqu_au->timestamp, 5);
        $this->assertSame(3000, $inscription->montant_centimes);
        $this->get(route('inscriptions.show', $inscription))->assertSee('Payer 30,00');
    }

    public function test_une_fois_complete_les_coureurs_passent_en_liste_d_attente_dans_l_ordre(): void
    {
        $epreuve = Epreuve::factory()->create(['quota' => 1]);

        $this->inscrire($epreuve);
        $this->inscrire($epreuve, ['nom' => 'Darrigade', 'email' => 'b@exemple.test']);
        $this->inscrire($epreuve, ['nom' => 'Hourcade', 'email' => 'c@exemple.test']);

        $attente = Inscription::query()->where('statut', StatutInscription::ListeAttente)->orderBy('position_attente')->get();
        $this->assertSame(['Darrigade', 'Hourcade'], $attente->pluck('nom')->all());
        $this->assertSame([1, 2], $attente->pluck('position_attente')->all());
        $this->get(route('inscriptions.show', $attente[1]))->assertSee('2<sup>e</sup>', false);
    }

    public function test_une_reservation_echue_ne_bloque_plus_la_place(): void
    {
        $epreuve = Epreuve::factory()->create(['quota' => 1]);
        Inscription::factory()->for($epreuve)->create(['place_reservee_jusqu_au' => now()->subMinute()]);

        // La tâche planifiée n'est pas encore passée, mais la place est déjà disponible.
        $this->inscrire($epreuve);

        $this->assertSame(StatutInscription::EnAttentePaiement, Inscription::query()->where('nom', 'Lacoste')->sole()->statut);
    }

    public function test_un_pps_qui_aura_expire_le_jour_de_la_course_est_refuse(): void
    {
        $epreuve = Epreuve::factory()->create(['depart_le' => now()->addMonths(4)]);

        $this->inscrire($epreuve, ['justificatif_type' => 'pps', 'justificatif_numero' => 'P123', 'justificatif_date' => now()->subDays(2)->toDateString()])
            ->assertSessionHasErrors(['justificatif_date' => 'Ce PPS ne sera plus valable le jour de la course (valable jusqu\'au '.now()->subDays(2)->addMonthsNoOverflow(3)->format('d/m/Y').'). '
                .'Inscrivez-vous sans, et ajoutez-le depuis votre page de suivi à partir du '.$epreuve->depart_le->copy()->subMonthsNoOverflow(3)->format('d/m/Y').'.']);
        $this->assertDatabaseCount('inscriptions', 0);
    }

    public function test_un_pps_encore_valable_le_jour_de_la_course_est_accepte(): void
    {
        $epreuve = Epreuve::factory()->create(['depart_le' => now()->addMonths(2)]);

        $this->inscrire($epreuve, ['justificatif_type' => 'pps', 'justificatif_numero' => 'P123', 'justificatif_date' => now()->subDays(5)->toDateString()])
            ->assertSessionHasNoErrors();
    }

    public function test_une_licence_exige_son_numero(): void
    {
        $this->inscrire(Epreuve::factory()->create(), ['justificatif_numero' => ''])
            ->assertSessionHasErrors(['justificatif_numero' => 'Indiquez votre numéro de licence.']);
    }

    public function test_on_s_inscrit_six_mois_avant_avec_un_pps_a_fournir_puis_on_l_ajoute(): void
    {
        $epreuve = Epreuve::factory()->create(['depart_le' => now()->addMonths(6), 'inscriptions_closes_le' => now()->addMonths(5)]);
        $this->inscrire($epreuve, ['justificatif_type' => 'pps', 'justificatif_numero' => ''])->assertSessionHasNoErrors();
        $inscription = Inscription::query()->sole();
        $this->assertTrue($inscription->ppsAFournir());
        $ouverture = $epreuve->depart_le->copy()->subMonthsNoOverflow(3)->format('d/m/Y');
        $this->get(route('inscriptions.show', $inscription))->assertSee('PPS à fournir')->assertSee($ouverture)
            ->assertDontSee('Enregistrer mon PPS'); // trop tôt : pas encore de formulaire

        // Trois mois et un jour plus tard, la fenêtre est ouverte. Un PPS trop ancien est refusé, un PPS récent accepté.
        $this->travel(3)->months();
        $this->travel(1)->day();
        $this->get(route('inscriptions.show', $inscription))->assertSee('Enregistrer mon PPS');
        $this->post(route('inscriptions.pps', $inscription), ['justificatif_numero' => 'P42', 'justificatif_date' => now()->subMonths(2)->toDateString()])
            ->assertSessionHasErrors('justificatif_date');
        $this->post(route('inscriptions.pps', $inscription), ['justificatif_numero' => 'P42', 'justificatif_date' => now()->toDateString()])
            ->assertSessionHas('succes');
        $this->assertSame('P42', $inscription->refresh()->justificatif_numero);
        $this->assertFalse($inscription->ppsAFournir());
    }

    public function test_un_numero_de_pps_sans_date_est_refuse(): void
    {
        $epreuve = Epreuve::factory()->create();

        $this->inscrire($epreuve, ['justificatif_type' => 'pps', 'justificatif_numero' => 'P123'])
            ->assertSessionHasErrors(['justificatif_date' => 'Indiquez la date à laquelle vous avez obtenu votre PPS.']);
    }

    public function test_l_age_minimum_se_calcule_au_jour_de_la_course(): void
    {
        $epreuve = Epreuve::factory()->create(['age_minimum' => 18, 'depart_le' => now()->addMonths(2)]);

        // 18 ans dans 3 mois : trop jeune le jour de la course.
        $this->inscrire($epreuve, ['naissance' => now()->subYears(18)->addMonths(3)->toDateString()])
            ->assertSessionHasErrors(['naissance']);
        // 18 ans dans 1 mois : il les aura le jour J.
        $this->inscrire($epreuve, ['naissance' => now()->subYears(18)->addMonth()->toDateString()])
            ->assertSessionHasNoErrors();
    }

    public function test_un_coureur_ne_s_inscrit_pas_deux_fois(): void
    {
        $epreuve = Epreuve::factory()->create();
        $this->inscrire($epreuve);

        $this->inscrire($epreuve, ['nom' => 'LACOSTE', 'prenom' => 'maïa', 'email' => 'autre@exemple.test'])
            ->assertSessionHasErrors(['nom' => 'Ce coureur est déjà inscrit sur cette épreuve.']);
        $this->assertDatabaseCount('inscriptions', 1);
    }

    public function test_hors_periode_d_inscription_la_demande_est_refusee(): void
    {
        $close = Epreuve::factory()->create(['inscriptions_closes_le' => now()->subDay()]);
        $future = Epreuve::factory()->create(['inscriptions_ouvertes_le' => now()->addDay()]);

        $this->inscrire($close)->assertSessionHasErrors(['epreuve' => 'Les inscriptions sont closes.']);
        $this->inscrire($future)->assertSessionHasErrors('epreuve');
        $this->assertDatabaseCount('inscriptions', 0);
    }

    public function test_le_reglement_doit_etre_accepte_et_les_champs_remplis(): void
    {
        $epreuve = Epreuve::factory()->create();

        $this->inscrire($epreuve, ['reglement' => '', 'email' => 'pas-une-adresse', 'sexe' => 'X'])
            ->assertSessionHasErrors(['reglement', 'email', 'sexe']);
    }

    public function test_l_accueil_affiche_le_remplissage_et_la_liste_d_attente(): void
    {
        $epreuve = Epreuve::factory()->create(['nom' => 'Grand trail', 'quota' => 1]);
        Inscription::factory()->for($epreuve)->payee(101)->create();
        Inscription::factory()->for($epreuve)->enAttente(1)->create();

        $this->get('/')->assertOk()->assertSee('Grand trail')->assertSee('Complet (1 places) · 1 sur liste d\'attente', false)
            ->assertSee('Rejoindre la liste d');
    }
}
