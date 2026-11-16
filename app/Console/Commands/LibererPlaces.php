<?php

namespace App\Console\Commands;

use App\Services\Guichet;
use Illuminate\Console\Command;

class LibererPlaces extends Command
{
    protected $signature = 'inscriptions:liberer';

    protected $description = 'Expire les réservations non payées et propose les places libres à la liste d\'attente';

    public function handle(Guichet $guichet): int
    {
        $bilan = $guichet->libererPlaces();
        $this->info("{$bilan['expirees']} réservation(s) expirée(s), {$bilan['proposees']} place(s) proposée(s).");

        return self::SUCCESS;
    }
}
