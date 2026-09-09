<?php

namespace Tests\Feature\Jobs;

use App\Jobs\FetchCexWishlistPrice;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use App\Services\GameLookup\CexGameLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchCexWishlistPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_stores_the_current_cex_price(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create([
            'status' => 'wishlist', 'title' => 'Hollow Knight', 'wishlist_estimated_price' => 20,
        ]);

        Http::fake(['search.webuy.io/*' => Http::response([
            'hits' => [['boxName' => 'Hollow Knight', 'sellPrice' => 18]],
        ], 200)]);

        (new FetchCexWishlistPrice($game->id))->handle(app(CexGameLookupService::class));

        $fresh = $game->fresh();
        $this->assertEquals(18, $fresh->cex_current_price);
        $this->assertNotNull($fresh->cex_checked_at);
    }

    public function test_handle_uses_the_games_platform_to_disambiguate(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Switch']);
        $game = Game::factory()->for($user)->create([
            'status' => 'wishlist', 'title' => 'Hollow Knight', 'platform_id' => $platform->id,
        ]);

        Http::fake(['search.webuy.io/*' => Http::response(['hits' => [
            ['boxName' => 'Hollow Knight', 'sellPrice' => 30, 'categoryFriendlyName' => 'PS4 Juegos'],
            ['boxName' => 'Hollow Knight', 'sellPrice' => 20, 'categoryFriendlyName' => 'Switch Juegos'],
        ]], 200)]);

        (new FetchCexWishlistPrice($game->id))->handle(app(CexGameLookupService::class));

        $this->assertEquals(20, $game->fresh()->cex_current_price);
    }

    public function test_handle_marks_as_checked_without_a_match_so_it_is_not_retried_every_visit(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['status' => 'wishlist']);

        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);

        (new FetchCexWishlistPrice($game->id))->handle(app(CexGameLookupService::class));

        $fresh = $game->fresh();
        $this->assertNull($fresh->cex_current_price);
        $this->assertNotNull($fresh->cex_checked_at);
    }

    public function test_handle_does_nothing_when_the_game_no_longer_exists(): void
    {
        Http::fake();

        (new FetchCexWishlistPrice(999999))->handle(app(CexGameLookupService::class));

        Http::assertNothingSent();
    }
}
