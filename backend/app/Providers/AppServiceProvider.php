<?php

namespace App\Providers;

use App\Services\GameLookup\CexGameLookupService;
use App\Services\GameLookup\GameLookupInterface;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
