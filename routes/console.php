<?php

use Illuminate\Support\Facades\Schedule;

// Sur un hébergement mutualisé, une seule tâche cron suffit : * * * * * php artisan schedule:run
Schedule::command('inscriptions:liberer')->everyMinute()->withoutOverlapping();
