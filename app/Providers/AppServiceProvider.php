<?php

namespace App\Providers;

use App\Models\Game;
use App\Models\Platform;
use App\Observers\GameObserver;
use App\Services\GameLookup\CexGameLookupService;
use App\Services\GameLookup\GameLookupInterface;
use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        //
        // Solo válido para resolución ligada a la petición HTTP actual
        // (auth()->user()): el worker de cola no tiene sesión, así que
        // Jobs\MatchGameWithIgdb no pasa por este bind — construye su propio
        // IgdbLookupService::forUser() con el dueño del juego.
        $this->app->bind(IgdbLookupService::class, fn () => IgdbLookupService::forUser(auth()->user()));
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

        Game::observe(GameObserver::class);

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Límite general de la API (activado en bootstrap/app.php vía
        // throttleApi()): antes solo /login tenía protección propia
        // (ThrottlesLogins) y el resto (/games) no tenía ningún tope. Por
        // usuario autenticado cuando hay token; por IP en /login antes de
        // conseguirlo (ahí manda igualmente el throttle de fuerza bruta, más
        // estricto).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Claves por usuario pendiente de verificar (guardado en sesión al
        // entrar al desafío, ver TwoFactorController), no por IP: dos
        // cuentas distintas desde la misma IP no deben compartir límite, y
        // la IP sola no sirve para frenar a alguien tanteando códigos contra
        // una única cuenta rotando de IP.
        RateLimiter::for('two-factor-verify', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->session()->get('two_factor.user_id', $request->ip()));
        });

        RateLimiter::for('two-factor-resend', function (Request $request) {
            return Limit::perMinutes(5, 3)->by($request->session()->get('two_factor.user_id', $request->ip()));
        });

        // Equivalentes de arriba para el desafío de 2FA de la API
        // (Api\AuthController::verifyTwoFactor()/resendTwoFactor()): sin
        // sesión en las rutas 'api', la clave es el propio
        // "two_factor_token" de un solo uso (identifica el intento de login
        // a medias tan bien como el user_id de sesión) y no la IP.
        RateLimiter::for('api-two-factor-verify', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->input('two_factor_token', $request->ip()));
        });

        RateLimiter::for('api-two-factor-resend', function (Request $request) {
            return Limit::perMinutes(5, 3)->by($request->input('two_factor_token', $request->ip()));
        });
    }
}
