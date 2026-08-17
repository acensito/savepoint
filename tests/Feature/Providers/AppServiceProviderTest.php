<?php

namespace Tests\Feature\Providers;

use App\Models\User;
use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * IgdbLookupService::class se resuelve por cuenta autenticada, no por
 * config() de instancia (ver AppServiceProvider::register() y Ajustes). No
 * se prueba a través de una ruta/controlador real porque
 * Illuminate\Routing\Route::getController() cachea la instancia del
 * controlador (con su IgdbLookupService ya inyectado) mientras dure la
 * aplicación — inofensivo en producción (cada petición PHP-FPM arranca una
 * aplicación nueva) pero falsearía este test, que reutiliza la misma
 * aplicación para dos usuarios distintos a propósito.
 */
class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_igdb_lookup_service_is_unconfigured_without_an_authenticated_user(): void
    {
        $this->assertFalse(app(IgdbLookupService::class)->isConfigured());
    }

    public function test_igdb_lookup_service_is_unconfigured_when_the_user_has_not_enabled_igdb(): void
    {
        $user = User::factory()->create([
            'igdb_enabled' => false,
            'igdb_client_id' => 'some-client-id',
            'igdb_client_secret' => 'some-client-secret',
        ]);

        $this->actingAs($user);

        $this->assertFalse(app(IgdbLookupService::class)->isConfigured());
    }

    public function test_igdb_lookup_service_uses_the_authenticated_users_own_credentials(): void
    {
        $userA = User::factory()->create([
            'igdb_enabled' => true,
            'igdb_client_id' => 'client-a',
            'igdb_client_secret' => 'secret-a',
        ]);
        $userB = User::factory()->create([
            'igdb_enabled' => true,
            'igdb_client_id' => 'client-b',
            'igdb_client_secret' => 'secret-b',
        ]);

        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 5184000], 200),
            'api.igdb.com/v4/games' => Http::response([], 200),
        ]);

        $this->actingAs($userA);
        app(IgdbLookupService::class)->search('Celeste');

        $this->actingAs($userB);
        app(IgdbLookupService::class)->search('Celeste');

        // Un token por cliente, no uno compartido para toda la instancia:
        // 2 peticiones de token + 2 búsquedas, nunca menos.
        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.igdb.com/v4/games'
            && $request->hasHeader('Client-ID', 'client-a'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.igdb.com/v4/games'
            && $request->hasHeader('Client-ID', 'client-b'));
    }
}
