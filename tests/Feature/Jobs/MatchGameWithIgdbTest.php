<?php

namespace Tests\Feature\Jobs;

use App\Jobs\MatchGameWithIgdb;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A diferencia de Tests\Unit\Services\GameLookup\IgdbGameMatcherTest (mapeo
 * de campos), esto prueba lo que es responsabilidad propia del job: cargar
 * el juego por id y resolver las credenciales de IGDB de su dueño sin
 * depender de auth()->user() — el worker de cola (docker-compose "queue")
 * no tiene sesión ni petición HTTP en curso, a diferencia de
 * GameController::show(), que sí la tenía cuando el match era síncrono.
 */
class MatchGameWithIgdbTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_matches_using_the_games_owner_credentials_without_any_authenticated_user(): void
    {
        $owner = User::factory()->create([
            'igdb_enabled' => true,
            'igdb_client_id' => 'owner-client-id',
            'igdb_client_secret' => 'owner-client-secret',
        ]);
        $game = Game::factory()->for($owner)->create(['title' => 'Celeste', 'developer' => null]);

        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'owner-token', 'expires_in' => 5184000], 200),
            'api.igdb.com/v4/games' => Http::response([[
                'id' => 305,
                'name' => 'Celeste',
                'involved_companies' => [['developer' => true, 'company' => ['name' => 'Maddy Makes Games']]],
            ]], 200),
            'api.igdb.com/v4/game_time_to_beats' => Http::response([
                ['hastily' => 36000, 'normally' => 64800, 'completely' => 115200, 'count' => 150],
            ], 200),
        ]);

        // Sin actingAs(): simula el worker de cola de verdad, no una
        // petición HTTP con sesión.
        (new MatchGameWithIgdb($game->id))->handle();

        $this->assertSame('Maddy Makes Games', $game->fresh()->developer);
        $this->assertSame(
            ['hastily' => 36000, 'normally' => 64800, 'completely' => 115200, 'count' => 150],
            $game->fresh()->igdb_time_to_beat,
        );
        Http::assertSent(fn ($request) => $request->hasHeader('Client-ID', 'owner-client-id'));
    }

    public function test_handle_does_nothing_when_the_owner_has_not_enabled_igdb(): void
    {
        $owner = User::factory()->create(['igdb_enabled' => false]);
        $game = Game::factory()->for($owner)->create();
        Http::fake();

        (new MatchGameWithIgdb($game->id))->handle();

        Http::assertNothingSent();
        $this->assertNotNull($game->fresh()->igdb_matched_at);
    }

    public function test_handle_does_nothing_when_the_game_no_longer_exists(): void
    {
        Http::fake();

        // No debe lanzar ninguna excepción: el juego pudo borrarse
        // definitivamente entre que show() despachó el job y el worker lo
        // procesó.
        (new MatchGameWithIgdb(999999))->handle();

        Http::assertNothingSent();
    }
}
