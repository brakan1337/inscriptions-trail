<?php

namespace App\Models;

use App\Enums\StatutInscription;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Epreuve extends Model
{
    use HasFactory;

    protected $table = 'epreuves';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'depart_le' => 'datetime',
            'inscriptions_ouvertes_le' => 'datetime',
            'inscriptions_closes_le' => 'datetime',
            'distance_km' => 'decimal:1',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /**
     * Les places prises maintenant : payées, ou réservées le temps d'un paiement
     * dont le délai court encore. Une réservation échue ne compte plus, même avant
     * que la tâche planifiée ne l'ait passée en « expirée ».
     */
    public function placesOccupees(?CarbonInterface $maintenant = null): int
    {
        $maintenant ??= now();

        return $this->inscriptions()
            ->where(function ($q) use ($maintenant) {
                $q->where('statut', StatutInscription::Payee)
                    ->orWhere(function ($q) use ($maintenant) {
                        $q->whereIn('statut', [StatutInscription::EnAttentePaiement, StatutInscription::OffreEnCours])
                            ->where('place_reservee_jusqu_au', '>', $maintenant);
                    });
            })
            ->count();
    }

    public function placesLibres(?CarbonInterface $maintenant = null): int
    {
        return max(0, $this->quota - $this->placesOccupees($maintenant));
    }

    public function enListeAttente(): int
    {
        return $this->inscriptions()->where('statut', StatutInscription::ListeAttente)->count();
    }

    public function inscriptionsOuvertes(?CarbonInterface $maintenant = null): bool
    {
        $maintenant ??= now();

        return $maintenant->between($this->inscriptions_ouvertes_le, $this->inscriptions_closes_le);
    }

    public function prixEnEuros(): string
    {
        return number_format($this->prix_centimes / 100, 2, ',', "\u{202F}").' €';
    }
}
