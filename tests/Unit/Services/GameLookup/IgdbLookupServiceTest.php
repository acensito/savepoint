<?php

namespace Tests\Unit\Services\GameLookup;

use App\Models\User;
use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IgdbLookupServiceTest extends TestCase
{
    // Sin RefreshDatabase/factory a propósito: forUser() solo lee atributos
    // en memoria, sin tocar la base de datos (importa para
    // Jobs\MatchGameWithIgdb, que la llama desde el worker de cola).
    public function test_for_user_uses_the_given_users_own_credentials_when_igdb_is_enabled(): void
    {
        $user = new User([
            'igdb_enabled' => true,
            'igdb_client_id' => 'owner-client-id',
            'igdb_client_secret' => 'owner-client-secret',
        ]);

        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response([], 200)]);

        IgdbLookupService::forUser($user)->search('Celeste');

        Http::assertSent(fn ($request) => $request->hasHeader('Client-ID', 'owner-client-id'));
    }

    public function test_for_user_returns_an_unconfigured_service_when_igdb_is_disabled(): void
    {
        $user = new User([
            'igdb_enabled' => false,
            'igdb_client_id' => 'owner-client-id',
            'igdb_client_secret' => 'owner-client-secret',
        ]);

        $this->assertFalse(IgdbLookupService::forUser($user)->isConfigured());
    }

    public function test_for_user_returns_an_unconfigured_service_without_a_user(): void
    {
        $this->assertFalse(IgdbLookupService::forUser(null)->isConfigured());
    }

    private function makeService(string $clientId = 'test-client-id', string $clientSecret = 'test-client-secret'): IgdbLookupService
    {
        return new IgdbLookupService(clientId: $clientId, clientSecret: $clientSecret);
    }

    private function fakeToken(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 5184000], 200),
        ]);
    }

    public function test_search_returns_empty_array_without_credentials_and_never_makes_a_request(): void
    {
        // Sin Http::fake(): Http::preventStrayRequests() (Tests\TestCase) haría
        // fallar el test si esto disparase una petición real.
        $this->assertSame([], $this->makeService(clientId: '', clientSecret: '')->search('Celeste'));
    }

    public function test_search_returns_empty_array_for_a_blank_query_without_making_a_request(): void
    {
        $this->assertSame([], $this->makeService()->search('   '));
    }

    public function test_search_maps_a_result_with_developer_release_date_genres_and_rating(): void
    {
        $this->fakeToken();
        Http::fake([
            'api.igdb.com/v4/games' => Http::response([[
                'id' => 305,
                'name' => 'Celeste',
                'first_release_date' => Carbon::create(2018, 1, 25, 0, 0, 0, 'UTC')->timestamp,
                'involved_companies' => [
                    ['developer' => false, 'company' => ['name' => 'Editora, S.A.']],
                    ['developer' => true, 'company' => ['name' => 'Maddy Makes Games']],
                ],
                'genres' => [['name' => 'Platform'], ['name' => 'Indie']],
                'aggregated_rating' => 87.654,
                'platforms' => [['name' => 'Nintendo Switch']],
            ]], 200),
        ]);

        $results = $this->makeService()->search('Celeste');

        $this->assertCount(1, $results);
        $match = $results[0];
        $this->assertSame(305, $match->igdbId);
        $this->assertSame('Celeste', $match->title);
        $this->assertSame('Maddy Makes Games', $match->developer);
        $this->assertSame('2018-01-25', $match->releaseDate);
        $this->assertSame(['Platform', 'Indie'], $match->genres);
        $this->assertSame(87.65, $match->rating);
        $this->assertSame('Nintendo Switch', $match->platforms);
    }

    public function test_search_falls_back_to_the_user_rating_when_there_is_no_aggregated_rating(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response([['id' => 1, 'name' => 'Celeste', 'rating' => 92.1]], 200)]);

        $this->assertSame(92.1, $this->makeService()->search('Celeste')[0]->rating);
    }

    public function test_search_sends_the_credentials_and_query_to_igdb(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response([], 200)]);

        $this->makeService()->search('Hollow Knight');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.igdb.com/v4/games'
                && $request->hasHeader('Client-ID', 'test-client-id')
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && str_contains($request->body(), 'search "Hollow Knight"');
        });
    }

    public function test_search_lists_results_matching_the_given_platform_first(): void
    {
        $this->fakeToken();
        Http::fake([
            'api.igdb.com/v4/games' => Http::response([
                ['id' => 1, 'name' => 'Celeste', 'platforms' => [['name' => 'PlayStation 4']]],
                ['id' => 2, 'name' => 'Celeste', 'platforms' => [['name' => 'Nintendo Switch']]],
            ], 200),
        ]);

        $results = $this->makeService()->search('Celeste', 'Nintendo Switch');

        $this->assertSame(2, $results[0]->igdbId);
    }

    public function test_search_prioritizes_an_exact_title_match_over_a_bundle_or_dlc_edition(): void
    {
        // Caso real: IGDB puede devolver antes una edición/bundle con DLC en
        // el nombre que el juego base, aunque el título buscado sea exacto.
        $this->fakeToken();
        Http::fake([
            'api.igdb.com/v4/games' => Http::response([
                ['id' => 1, 'name' => 'Breath of the Wild and Expansion Pass Bundle'],
                ['id' => 2, 'name' => 'Breath of the Wild'],
            ], 200),
        ]);

        $results = $this->makeService()->search('Breath of the Wild');

        $this->assertSame(2, $results[0]->igdbId);
    }

    public function test_search_prioritizes_exact_title_over_platform_match(): void
    {
        $this->fakeToken();
        Http::fake([
            'api.igdb.com/v4/games' => Http::response([
                ['id' => 1, 'name' => 'Celeste (Deluxe Edition)', 'platforms' => [['name' => 'Nintendo Switch']]],
                ['id' => 2, 'name' => 'Celeste', 'platforms' => [['name' => 'PC']]],
            ], 200),
        ]);

        $results = $this->makeService()->search('Celeste', 'Nintendo Switch');

        $this->assertSame(2, $results[0]->igdbId);
    }

    public function test_search_skips_results_without_a_name(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response([['id' => 1], ['id' => 2, 'name' => 'Celeste']], 200)]);

        $results = $this->makeService()->search('Celeste');

        $this->assertCount(1, $results);
        $this->assertSame('Celeste', $results[0]->title);
    }

    public function test_search_returns_empty_array_when_the_games_request_fails(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response('', 500)]);

        $this->assertSame([], $this->makeService()->search('Celeste'));
    }

    public function test_search_returns_empty_array_when_the_token_request_fails(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response('', 401),
            'api.igdb.com/v4/games' => Http::response([['id' => 1, 'name' => 'Celeste']], 200),
        ]);

        $this->assertSame([], $this->makeService()->search('Celeste'));

        // Sin token no tiene sentido ni intentar la búsqueda.
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.igdb.com/v4/games');
    }

    public function test_search_reuses_the_cached_token_across_calls(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response([], 200)]);

        $this->makeService()->search('Celeste');
        $this->makeService()->search('Hollow Knight');

        Http::assertSentCount(3); // 1 token + 2 búsquedas
    }

    public function test_search_does_not_share_the_cached_token_between_different_credentials(): void
    {
        // Las credenciales son ahora por cuenta (ver AppServiceProvider), no
        // de instancia: un token cacheado para un client_id no debe colarse
        // en una petición con otro.
        $this->fakeToken();
        Http::fake(array_merge(
            ['id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 5184000], 200)],
            ['api.igdb.com/v4/games' => Http::response([], 200)],
        ));

        $this->makeService(clientId: 'client-a', clientSecret: 'secret-a')->search('Celeste');
        $this->makeService(clientId: 'client-b', clientSecret: 'secret-b')->search('Celeste');

        Http::assertSentCount(4); // 2 tokens (uno por cliente) + 2 búsquedas
    }

    public function test_search_returns_empty_array_on_connection_failure(): void
    {
        Http::fake(['id.twitch.tv/oauth2/token' => fn () => throw new ConnectionException('timed out')]);

        $this->assertSame([], $this->makeService()->search('Celeste'));
    }

    public function test_artworks_returns_the_image_ids_for_the_given_igdb_id(): void
    {
        $this->fakeToken();
        Http::fake([
            'api.igdb.com/v4/artworks' => Http::response([
                ['id' => 1, 'image_id' => 'ar1abc'],
                ['id' => 2, 'image_id' => 'ar2def'],
            ], 200),
        ]);

        $this->assertSame(['ar1abc', 'ar2def'], $this->makeService()->artworks(305));

        Http::assertSent(fn ($request) => $request->url() === 'https://api.igdb.com/v4/artworks'
            && str_contains($request->body(), 'where game = 305'));
    }

    public function test_artworks_returns_empty_array_without_credentials(): void
    {
        $this->assertSame([], $this->makeService(clientId: '', clientSecret: '')->artworks(305));
    }

    public function test_artworks_returns_empty_array_when_the_request_fails(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/artworks' => Http::response('', 500)]);

        $this->assertSame([], $this->makeService()->artworks(305));
    }

    public function test_time_to_beat_returns_the_durations_for_the_given_igdb_id(): void
    {
        $this->fakeToken();
        Http::fake([
            'api.igdb.com/v4/game_time_to_beats' => Http::response([
                ['hastily' => 36000, 'normally' => 64800, 'completely' => 115200, 'count' => 150],
            ], 200),
        ]);

        $this->assertSame(
            ['hastily' => 36000, 'normally' => 64800, 'completely' => 115200, 'count' => 150],
            $this->makeService()->timeToBeat(305),
        );

        Http::assertSent(fn ($request) => $request->url() === 'https://api.igdb.com/v4/game_time_to_beats'
            && str_contains($request->body(), 'where game_id = 305'));
    }

    public function test_time_to_beat_omits_tiers_the_response_does_not_have(): void
    {
        $this->fakeToken();
        Http::fake([
            'api.igdb.com/v4/game_time_to_beats' => Http::response([
                ['normally' => 64800, 'count' => 3],
            ], 200),
        ]);

        $this->assertSame(['normally' => 64800, 'count' => 3], $this->makeService()->timeToBeat(305));
    }

    public function test_time_to_beat_returns_null_when_igdb_has_no_entry_for_the_game(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/game_time_to_beats' => Http::response([], 200)]);

        $this->assertNull($this->makeService()->timeToBeat(305));
    }

    public function test_time_to_beat_returns_null_without_credentials(): void
    {
        $this->assertNull($this->makeService(clientId: '', clientSecret: '')->timeToBeat(305));
    }

    public function test_time_to_beat_returns_null_when_the_request_fails(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/game_time_to_beats' => Http::response('', 500)]);

        $this->assertNull($this->makeService()->timeToBeat(305));
    }
}
