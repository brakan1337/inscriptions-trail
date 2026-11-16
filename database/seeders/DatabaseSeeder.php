<?php

namespace Database\Seeders;

use App\Enums\Justificatif;
use App\Enums\StatutInscription;
use App\Enums\StatutJustificatif;
use App\Models\Epreuve;
use App\Models\Inscription;
use App\Models\User;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Données fictives : un trail de printemps et ses trois épreuves, à six mois de la course.
 * Le grand parcours est complet avec une liste d'attente, les deux autres se remplissent.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'organisation@trail.example'],
            ['name' => 'Organisation', 'password' => Hash::make(env('MOT_DE_PASSE_DEMO', 'demo-organisation'))],
        );

        $depart = now()->addMonths(6)->startOfWeek()->addDays(5)->setTime(7, 30);
        $commun = [
            'inscriptions_ouvertes_le' => now()->subMonth(),
            'inscriptions_closes_le' => $depart->copy()->subDays(3)->setTime(23, 59),
        ];

        $grand = Epreuve::query()->create([...$commun,
            'nom' => 'Grand trail des crêtes', 'slug' => 'grand-trail', 'distance_km' => 42.5, 'denivele_m' => 2150,
            'depart_le' => $depart, 'quota' => 400, 'prix_centimes' => 4800,
            'dossard_premier' => 1, 'dossard_dernier' => 999, 'age_minimum' => 20,
        ]);
        $decouverte = Epreuve::query()->create([...$commun,
            'nom' => 'Trail découverte', 'slug' => 'trail-decouverte', 'distance_km' => 18, 'denivele_m' => 650,
            'depart_le' => $depart->copy()->setTime(9, 30), 'quota' => 600, 'prix_centimes' => 2400,
            'dossard_premier' => 1001, 'dossard_dernier' => 1999, 'age_minimum' => 16,
        ]);
        $marche = Epreuve::query()->create([...$commun,
            'nom' => 'Marche nordique', 'slug' => 'marche-nordique', 'distance_km' => 12, 'denivele_m' => 320,
            'depart_le' => $depart->copy()->setTime(10, 0), 'quota' => 150, 'prix_centimes' => 1500,
            'dossard_premier' => 3001, 'dossard_dernier' => 3499, 'age_minimum' => 14,
        ]);

        $faker = Faker::create('fr_FR');
        $faker->seed(2027);
        $this->remplir($faker, $grand, payees: 396, reservees: 4, attente: 7);
        $this->remplir($faker, $decouverte, payees: 231, reservees: 6, attente: 0);
        $this->remplir($faker, $marche, payees: 48, reservees: 1, attente: 0);
    }

    private function remplir(Generator $faker, Epreuve $epreuve, int $payees, int $reservees, int $attente): void
    {
        $lignes = [];
        $total = $payees + $reservees + $attente;
        for ($i = 0; $i < $total; $i++) {
            $sexe = $faker->randomElement(['F', 'H', 'H']);
            $pps = $faker->boolean(55);
            $statut = $i < $payees ? StatutInscription::Payee : ($i < $payees + $reservees ? StatutInscription::EnAttentePaiement : StatutInscription::ListeAttente);
            $lignes[] = [
                'epreuve_id' => $epreuve->id,
                'reference' => Str::upper(Str::random(12)),
                'nom' => $faker->lastName(),
                'prenom' => $sexe === 'F' ? $faker->firstNameFemale() : $faker->firstNameMale(),
                'sexe' => $sexe,
                'naissance' => $faker->dateTimeBetween('-70 years', '-'.($epreuve->age_minimum + 1).' years')->format('Y-m-d'),
                'email' => $faker->unique()->safeEmail(),
                'club' => $faker->boolean(35) ? 'AC '.$faker->city() : null,
                'justificatif_type' => $pps ? Justificatif::Pps->value : Justificatif::Licence->value,
                // La course est dans six mois : un PPS fait aujourd'hui aurait expiré. Les non-licenciés le fourniront plus tard.
                'justificatif_numero' => $pps ? null : $faker->numerify('#######'),
                'justificatif_date' => null,
                'justificatif_statut' => ! $pps && $statut === StatutInscription::Payee && $faker->boolean(80)
                    ? StatutJustificatif::Valide->value : StatutJustificatif::AVerifier->value,
                'statut' => $statut->value,
                'place_reservee_jusqu_au' => $statut === StatutInscription::EnAttentePaiement ? now()->addMinutes($faker->numberBetween(5, 29)) : null,
                'position_attente' => $statut === StatutInscription::ListeAttente ? $i - $payees - $reservees + 1 : null,
                'dossard' => $statut === StatutInscription::Payee ? $epreuve->dossard_premier + $i : null,
                'montant_centimes' => $epreuve->prix_centimes,
                'payee_le' => $statut === StatutInscription::Payee ? now()->subDays($faker->numberBetween(1, 30)) : null,
                'created_at' => now()->subDays($faker->numberBetween(1, 30)),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($lignes, 200) as $paquet) {
            Inscription::query()->insert($paquet);
        }
    }
}
