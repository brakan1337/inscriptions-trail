<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    protected $guarded = [];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }
}
