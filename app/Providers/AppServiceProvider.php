<?php

namespace App\Providers;

use App\Paiement\Passerelle;
use App\Paiement\PasserelleSimulee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Le jour où le club choisit un vrai prestataire, seule cette ligne change.
        $this->app->bind(Passerelle::class, PasserelleSimulee::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        // En développement, une relation chargée paresseusement dans une boucle lève une erreur au lieu de ralentir en silence.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
