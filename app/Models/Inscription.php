<?php

namespace App\Models;

use App\Enums\Justificatif;
use App\Enums\StatutInscription;
use App\Enums\StatutJustificatif;
use App\Support\Categorie;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inscription extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'naissance' => 'date',
            'justificatif_date' => 'date',
            'place_reservee_jusqu_au' => 'datetime',
            'payee_le' => 'datetime',
            'statut' => StatutInscription::class,
            'justificatif_type' => Justificatif::class,
            'justificatif_statut' => StatutJustificatif::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function epreuve(): BelongsTo
    {
        return $this->belongsTo(Epreuve::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function nomComplet(): string
    {
        return $this->prenom.' '.mb_strtoupper($this->nom);
    }

    public function categorie(): string
    {
        return Categorie::pour($this->naissance, $this->epreuve->depart_le);
    }

    /** Inscrit avec « PPS à fournir » : il n'a pas encore donné son numéro. */
    public function ppsAFournir(): bool
    {
        return $this->justificatif_type === Justificatif::Pps && $this->justificatif_numero === null;
    }

    public function reservationEnCours(): bool
    {
        return $this->statut->attendPaiement()
            && $this->place_reservee_jusqu_au !== null
            && $this->place_reservee_jusqu_au->isFuture();
    }
}
