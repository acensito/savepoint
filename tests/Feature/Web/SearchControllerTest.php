<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_shows_local_matches_without_querying_the_external_service(): void
    {
        Http::fake();
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Hollow Knight']);

        $response = $this->actingAs($user)->get(route('web.search.quick', ['q' => 'Hollow']));

        $response->assertOk();
        $response->assertSee('Hollow Knight');
        Http::assertNothingSent();
    }

    public function test_quick_does_not_query_the_external_service_for_very_short_queries(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('web.search.quick', ['q' => 'ho']));

        $response->assertOk();
        Http::assertNothingSent();
    }

    public function test_quick_shows_external_suggestions_when_there_is_no_local_match(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [[
                    'boxName' => 'Hollow Knight',
                    'boxId' => '5060146467315',
                    'imageUrls' => ['large' => 'https://es.static.webuy.com/hk_l.jpg'],
                ]],
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('web.search.quick', ['q' => 'Hollow Knight']));

        $response->assertOk();
        $response->assertSee('Sugerencias de CEX');
        $response->assertSee('Hollow Knight');
        $response->assertSee('5060146467315');
        $response->assertSee('data-cover="https://es.static.webuy.com/hk_l.jpg"', false);
    }

    public function test_quick_still_offers_the_manual_add_link_when_the_external_service_finds_nothing(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('web.search.quick', ['q' => 'Un juego inventado']));

        $response->assertOk();
        $response->assertDontSee('Sugerencias de CEX');
        $response->assertSee('Dar de alta «Un juego inventado» a mano');
    }

    public function test_quick_does_not_query_the_external_service_when_the_query_is_blank(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('web.search.quick'));

        $response->assertOk();
        Http::assertNothingSent();
    }

    public function test_quick_includes_wishlist_games_by_default(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Silksong', 'status' => 'wishlist']);

        $response = $this->actingAs($user)->get(route('web.search.quick', ['q' => 'Silksong']));

        $response->assertOk();
        $response->assertSee('Silksong');
    }

    public function test_quick_excludes_wishlist_games_when_the_setting_is_enabled(): void
    {
        // Sin match local (se excluye la wishlist a propósito), se
        // consultaría CEX: se falsea para no salir a la red desde el test.
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);
        $user = User::factory()->create(['quick_search_exclude_wishlist' => true]);
        $game = Game::factory()->for($user)->create(['title' => 'Silksong', 'status' => 'wishlist']);

        $response = $this->actingAs($user)->get(route('web.search.quick', ['q' => 'Silksong']));

        $response->assertOk();
        // No basta con assertDontSee('Silksong'): el enlace "Dar de alta a
        // mano" repite el texto de la búsqueda aunque no haya match local.
        $response->assertDontSee(route('web.games.show', $game->id), false);
        $response->assertSee('Sin resultados para');
    }
}
