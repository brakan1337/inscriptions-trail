<?php

namespace Database\Factories;

use App\Models\Epreuve;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Epreuve> */
class EpreuveFactory extends Factory
{
    public function definition(): array
    {
        $nom = 'Trail '.$this->faker->unique()->word();

        return [
            'nom' => $nom,
            'slug' => Str::slug($nom),
            'distance_km' => 25,
            'denivele_m' => 900,
            'depart_le' => now()->addMonths(2)->setTime(8, 0),
            'quota' => 3,
            'prix_centimes' => 3000,
            'dossard_premier' => 101,
            'dossard_dernier' => 199,
            'age_minimum' => 18,
            'inscriptions_ouvertes_le' => now()->subWeek(),
            'inscriptions_closes_le' => now()->addMonth(),
        ];
    }
}
