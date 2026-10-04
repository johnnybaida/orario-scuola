<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Ruoli;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('gestisci-anagrafica', fn (User $user) => in_array($user->ruolo, Ruoli::GESTIONE_ANAGRAFICA, true));
        Gate::define('approva-orari', fn (User $user) => in_array($user->ruolo, Ruoli::APPROVAZIONE, true));
        Gate::define('consulta', fn (User $user) => in_array($user->ruolo, Ruoli::CONSULTAZIONE, true));
        Gate::define('gestisci-utenze', fn (User $user) => $user->ruolo === Ruoli::AMMINISTRATORE);
        Gate::define('gestisci-docenti-classi', fn (User $user) => in_array($user->ruolo, Ruoli::GESTIONE_DOCENTI_CLASSI, true));
    }
}
