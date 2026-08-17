<?php

namespace App\Providers;

use App\Models\Platform;
use App\Services\GameLookup\CexGameLookupService;
use App\Services\GameLookup\GameLookupInterface;
use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Único sitio que sabe que el proveedor de búsqueda externa es CEX
        // hoy: cambiar de proveedor (u ofrecer varios) es cambiar este bind,
        // no App\Http\Controllers\Web\SearchController.
        $this->app->bind(GameLookupInterface::class, function () {
            $config = config('services.cex');

            return new CexGameLookupService(
                host: $config['host'],
                appId: $config['app_id'],
                apiKey: $config['api_key'],
                index: $config['index'],
            );
        });

        // Complemento a CEX, no un sustituto (ver IgdbLookupService): solo
        // autocompleta desarrollador/fecha de lanzamiento cuando la cuenta
        // autenticada ha activado IGDB y dado sus propias credenciales
        // (users.igdb_enabled/igdb_client_id/igdb_client_secret, ver
        // Ajustes) — son por cuenta, no de instancia, así que se resuelven
        // en cada petición (bind, no singleton) en vez de una sola vez con
        // config() como antes. Sin usuario autenticado (no debería darse:
        // el único consumidor vive tras el middleware 'auth') o con IGDB
        // desactivado, se instancia sin credenciales: IgdbLookupService ya
        // sabe no hacer ninguna petición en ese caso.
        $this->app->bind(IgdbLookupService::class, function () {
            $user = auth()->user();
            $enabled = $user?->igdb_enabled ?? false;

            return new IgdbLookupService(
                clientId: $enabled ? (string) $user->igdb_client_id : '',
                clientSecret: $enabled ? (string) $user->igdb_client_secret : '',
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Los filtros compactos del buscador rápido (Ctrl+K, ver
        // quick-search-dialog en layouts/app.blade.php) viven en el layout,
        // que incluye toda página autenticada, no solo el listado de la
        // colección: de ahí un composer en vez de pasarlo controlador a
        // controlador.
        View::composer('layouts.app', function ($view) {
            $view->with('quickSearchPlatforms', Platform::orderBy('name')->get(['id', 'name']));
        });
    }
}
