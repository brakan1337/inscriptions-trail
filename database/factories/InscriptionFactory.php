<?php

namespace Database\Factories;

use App\Enums\StatutInscription;
use App\Enums\StatutJustificatif;
use App\Models\Epreuve;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Inscription> */
class InscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'epreuve_id' => Epreuve::factory(),
            'reference' => Str::upper(Str::random(12)),
            'nom' => $this->faker->lastName(),
            'prenom' => $this->faker->firstName(),
            'sexe' => 'F',
            'naissance' => '1990-04-12',
            'email' => $this->faker->unique()->safeEmail(),
            'justificatif_type' => 'licence',
            'justificatif_numero' => '1234567',
            'justificatif_statut' => StatutJustificatif::AVerifier,
            'statut' => StatutInscription::EnAttentePaiement,
            'place_reservee_jusqu_au' => now()->addMinutes(30),
            'montant_centimes' => 3000,
        ];
    }

    public function payee(int $dossard): static
    {
        return $this->state(['statut' => StatutInscription::Payee, 'dossard' => $dossard, 'payee_le' => now(), 'place_reservee_jusqu_au' => null]);
    }

    public function enAttente(int $position): static
    {
        return $this->state(['statut' => StatutInscription::ListeAttente, 'position_attente' => $position, 'place_reservee_jusqu_au' => null]);
    }
}
