<?php

namespace Tests\Unit\Services\GameLookup;

use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IgdbLookupServiceTest extends TestCase
{
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

    public function test_find_by_title_returns_null_without_credentials_and_never_makes_a_request(): void
    {
        // Sin Http::fake(): Http::preventStrayRequests() (Tests\TestCase) haría
        // fallar el test si esto disparase una petición real.
        $this->assertNull($this->makeService(clientId: '', clientSecret: '')->findByTitle('Celeste'));
    }

    public function test_find_by_title_returns_null_for_a_blank_title_without_making_a_request(): void
    {
        $this->assertNull($this->makeService()->findByTitle('   '));
    }

    public function test_find_by_title_returns_developer_and_release_date(): void
    {
        $this->fakeToken();
        Http::fake([
            'api.igdb.com/v4/games' => Http::response([[
                'name' => 'Celeste',
                'first_release_date' => Carbon::create(2018, 1, 25, 0, 0, 0, 'UTC')->timestamp,
                'involved_companies' => [
                    ['developer' => false, 'company' => ['name' => 'Editora, S.A.']],
                    ['developer' => true, 'company' => ['name' => 'Maddy Makes Games']],
                ],
                'platforms' => [['name' => 'Nintendo Switch']],
            ]], 200),
        ]);

        $result = $this->makeService()->findByTitle('Celeste');

        $this->assertSame('Maddy Makes Games', $result['developer']);
        $this->assertSame('2018-01-25', $result['release_date']);
    }

    public function test_find_by_title_sends_the_credentials_and_query_to_igdb(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response([], 200)]);

        $this->makeService()->findByTitle('Hollow Knight');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.igdb.com/v4/games'
                && $request->hasHeader('Client-ID', 'test-client-id')
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && str_contains($request->body(), 'search "Hollow Knight"');
        });
    }

    public function test_find_by_title_prefers_the_result_matching_the_given_platform(): void
    {
        $this->fakeToken();
        Http::fake([
            'api.igdb.com/v4/games' => Http::response([
                [
                    'name' => 'Celeste',
                    'involved_companies' => [['developer' => true, 'company' => ['name' => 'PS4 edition dev']]],
                    'platforms' => [['name' => 'PlayStation 4']],
                ],
                [
                    'name' => 'Celeste',
                    'involved_companies' => [['developer' => true, 'company' => ['name' => 'Maddy Makes Games']]],
                    'platforms' => [['name' => 'Nintendo Switch']],
                ],
            ], 200),
        ]);

        $result = $this->makeService()->findByTitle('Celeste', 'Nintendo Switch');

        $this->assertSame('Maddy Makes Games', $result['developer']);
    }

    public function test_find_by_title_reuses_the_cached_token_across_calls(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response([], 200)]);

        $this->makeService()->findByTitle('Celeste');
        $this->makeService()->findByTitle('Hollow Knight');

        Http::assertSentCount(3); // 1 token + 2 búsquedas
    }

    public function test_find_by_title_returns_null_when_neither_developer_nor_release_date_is_available(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response([['name' => 'Celeste']], 200)]);

        $this->assertNull($this->makeService()->findByTitle('Celeste'));
    }

    public function test_find_by_title_returns_null_when_there_are_no_results(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response([], 200)]);

        $this->assertNull($this->makeService()->findByTitle('Un juego que no existe'));
    }

    public function test_find_by_title_returns_null_when_the_games_request_fails(): void
    {
        $this->fakeToken();
        Http::fake(['api.igdb.com/v4/games' => Http::response('', 500)]);

        $this->assertNull($this->makeService()->findByTitle('Celeste'));
    }

    public function test_find_by_title_returns_null_when_the_token_request_fails(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response('', 401),
            'api.igdb.com/v4/games' => Http::response([['name' => 'Celeste']], 200),
        ]);

        $this->assertNull($this->makeService()->findByTitle('Celeste'));

        // Sin token no tiene sentido ni intentar la búsqueda.
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.igdb.com/v4/games');
    }

    public function test_find_by_title_returns_null_on_connection_failure(): void
    {
        Http::fake(['id.twitch.tv/oauth2/token' => fn () => throw new ConnectionException('timed out')]);

        $this->assertNull($this->makeService()->findByTitle('Celeste'));
    }
}
