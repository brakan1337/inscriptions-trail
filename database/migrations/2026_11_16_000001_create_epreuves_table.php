<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epreuves', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->decimal('distance_km', 5, 1);
            $table->unsignedInteger('denivele_m');
            $table->dateTime('depart_le');
            $table->unsignedInteger('quota');
            $table->unsignedInteger('prix_centimes');
            // Chaque épreuve a sa plage de dossards : le chronométreur reconnaît la course au numéro.
            $table->unsignedInteger('dossard_premier');
            $table->unsignedInteger('dossard_dernier');
            $table->unsignedTinyInteger('age_minimum');
            $table->dateTime('inscriptions_ouvertes_le');
            $table->dateTime('inscriptions_closes_le');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epreuves');
    }
};
