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
}
