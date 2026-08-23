<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameCoverLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_cover_lookup_searches_cex_by_the_games_ean_and_returns_its_platform(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [[
                    'boxName' => 'Hollow Knight',
                    'boxId' => '5060146467315',
                    'imageUrls' => ['large' => 'https://es.static.webuy.com/hk_l.jpg'],
                    'categoryFriendlyName' => 'Switch Juegos',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Hollow Knight', 'ean' => '5060146467315']);

        $response = $this->actingAs($user)->getJson("/games/{$game->id}/cover-lookup");

        $response->assertOk();
        $response->assertJson(['results' => [[
            'title' => 'Hollow Knight',
            'ean' => '5060146467315',
            'cover_url' => 'https://es.static.webuy.com/hk_l.jpg',
            'platform' => 'Switch',
        ]]]);
        Http::assertSent(fn ($request) => str_contains($request['params'], 'query=5060146467315'));
    }

    public function test_cover_lookup_falls_back_to_the_title_when_the_game_has_no_ean(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);

        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Celeste', 'ean' => null]);

        $this->actingAs($user)->getJson("/games/{$game->id}/cover-lookup")->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['params'], 'query=Celeste'));
    }

    public function test_cover_lookup_uses_the_manual_query_instead_of_the_games_ean_when_given(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);

        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Zelda mal escrito', 'ean' => '5060146467315']);

        $this->actingAs($user)->getJson("/games/{$game->id}/cover-lookup?q=Breath+of+the+Wild")->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['params'], 'query=Breath+of+the+Wild'));
    }

    public function test_cover_lookup_is_forbidden_for_another_users_game(): void
    {
        Http::fake();
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        $response = $this->actingAs(User::factory()->create())->getJson("/games/{$game->id}/cover-lookup");

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_cover_lookup_for_new_searches_cex_without_a_saved_game(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [[
                    'boxName' => 'Hollow Knight',
                    'boxId' => '5060146467315',
                    'imageUrls' => ['large' => 'https://es.static.webuy.com/hk_l.jpg'],
                    'categoryFriendlyName' => 'Switch Juegos',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/games/cover-lookup?q=Hollow+Knight');

        $response->assertOk();
        $response->assertJson(['results' => [[
            'title' => 'Hollow Knight',
            'ean' => '5060146467315',
            'cover_url' => 'https://es.static.webuy.com/hk_l.jpg',
            'platform' => 'Switch',
        ]]]);
        Http::assertSent(fn ($request) => str_contains($request['params'], 'query=Hollow+Knight'));
    }

    public function test_cover_lookup_for_new_without_a_query_returns_no_results_without_calling_cex(): void
    {
        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/games/cover-lookup')
            ->assertOk()
            ->assertJson(['results' => []]);

        Http::assertNothingSent();
    }

    public function test_cover_lookup_for_new_requires_authentication(): void
    {
        Http::fake();

        $this->getJson('/games/cover-lookup?q=Hollow+Knight')->assertUnauthorized();

        Http::assertNothingSent();
    }
}
