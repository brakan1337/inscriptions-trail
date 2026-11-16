<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('epreuve_id')->constrained('epreuves');
            // La référence sert de clé d'accès au coureur pour suivre son inscription : longue et imprévisible.
            $table->string('reference', 16)->unique();
            $table->string('nom', 80);
            $table->string('prenom', 80);
            $table->char('sexe', 1);
            $table->date('naissance');
            $table->string('email');
            $table->string('club', 120)->nullable();

            $table->string('justificatif_type', 10);
            // Un PPS peut être fourni plus tard : il doit dater de moins de trois mois le jour de la course.
            $table->string('justificatif_numero', 40)->nullable();
            $table->date('justificatif_date')->nullable();
            $table->string('justificatif_statut', 12)->default('a_verifier');
            $table->string('justificatif_motif', 200)->nullable();

            $table->string('statut', 20);
            $table->dateTime('place_reservee_jusqu_au')->nullable();
            $table->unsignedInteger('position_attente')->nullable();
            $table->unsignedInteger('dossard')->nullable();
            $table->unsignedInteger('montant_centimes');
            $table->dateTime('payee_le')->nullable();
            $table->timestamps();

            // La base elle-même refuse deux coureurs avec le même dossard sur une épreuve.
            $table->unique(['epreuve_id', 'dossard']);
            $table->index(['epreuve_id', 'statut']);
            $table->index(['statut', 'place_reservee_jusqu_au']);
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions');
            $table->string('passerelle', 20);
            $table->string('reference_externe', 80)->unique();
            $table->unsignedInteger('montant_centimes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('inscriptions');
    }
};
