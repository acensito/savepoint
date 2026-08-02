<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stats(): void
    {
        $this->get('/stats')->assertRedirect('/login');
    }

    public function test_stats_only_consider_the_authenticated_users_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Game::factory()->for($user)->create(['price_paid' => 10, 'rating' => 4]);
        Game::factory()->for($user)->create(['price_paid' => 20, 'rating' => 2]);
        Game::factory()->for($otherUser)->create(['price_paid' => 1000, 'rating' => 5]);

        $response = $this->actingAs($user)->get('/stats');

        $response->assertOk();
        $response->assertViewHas('totalGames', 2);
        $response->assertViewHas('totalSpent', 30.0);
        $response->assertViewHas('averageRating', 3.0);
    }

    public function test_stats_breaks_down_games_by_platform(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Switch']);

        Game::factory()->for($user)->count(3)->create(['platform_id' => $platform->id]);

        $response = $this->actingAs($user)->get('/stats');

        $byPlatform = $response->viewData('byPlatform');

        $this->assertCount(1, $byPlatform);
        $this->assertSame(3, $byPlatform->first()['total']);
        $this->assertSame($platform->id, $byPlatform->first()['platform']->id);
    }

    public function test_stats_breaks_down_games_by_play_status_and_ownership(): void
    {
        $user = User::factory()->create();

        Game::factory()->for($user)->create(['play_status' => 'finished', 'status' => 'owned']);
        Game::factory()->for($user)->create(['play_status' => 'pending', 'status' => 'wishlist']);

        $response = $this->actingAs($user)->get('/stats');

        $byPlayStatus = collect($response->viewData('byPlayStatus'))->keyBy('label');
        $byStatus = collect($response->viewData('byStatus'))->keyBy('label');

        $this->assertSame(1, $byPlayStatus['Terminado']['total']);
        $this->assertSame(1, $byPlayStatus['Pendiente']['total']);
        $this->assertSame(1, $byStatus['En colección']['total']);
        $this->assertSame(1, $byStatus['Lista de deseos']['total']);
    }

    public function test_stats_breaks_down_spending_by_month(): void
    {
        $user = User::factory()->create();

        Game::factory()->for($user)->create(['purchase_date' => '2026-06-15', 'price_paid' => 10]);
        Game::factory()->for($user)->create(['purchase_date' => '2026-06-20', 'price_paid' => 15]);
        Game::factory()->for($user)->create(['purchase_date' => '2026-07-01', 'price_paid' => 5]);
        Game::factory()->for($user)->create(['purchase_date' => null, 'price_paid' => 100]);

        $response = $this->actingAs($user)->get('/stats');

        $byMonth = collect($response->viewData('spendingByMonth'))->keyBy('label');

        $this->assertSame(25.0, $byMonth['jun. 2026']['total']);
        $this->assertSame(5.0, $byMonth['jul. 2026']['total']);
    }

    public function test_stats_lists_top_genres(): void
    {
        $user = User::factory()->create();

        Game::factory()->for($user)->create(['genres' => ['Acción', 'Aventura']]);
        Game::factory()->for($user)->create(['genres' => ['Acción']]);
        Game::factory()->for($user)->create(['genres' => ['RPG']]);

        $response = $this->actingAs($user)->get('/stats');

        $topGenres = collect($response->viewData('topGenres'))->keyBy('genre');

        $this->assertSame(2, $topGenres['Acción']['total']);
        $this->assertSame(1, $topGenres['Aventura']['total']);
        $this->assertSame(1, $topGenres['RPG']['total']);
    }

    public function test_stats_highlights_the_most_expensive_and_top_rated_games(): void
    {
        $user = User::factory()->create();

        Game::factory()->for($user)->create(['title' => 'Barato', 'price_paid' => 5, 'rating' => 1]);
        $expensive = Game::factory()->for($user)->create(['title' => 'Caro', 'price_paid' => 90, 'rating' => 2]);
        $topRated = Game::factory()->for($user)->create(['title' => 'Mejor valorado', 'price_paid' => 20, 'rating' => 5]);

        $response = $this->actingAs($user)->get('/stats');

        $this->assertSame($expensive->id, $response->viewData('mostExpensive')->id);
        $this->assertSame($topRated->id, $response->viewData('topRated')->id);
    }
}
