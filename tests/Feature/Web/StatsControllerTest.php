<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_stats_breaks_down_sales_by_year(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $mine = Game::factory()->for($user)->create(['status' => 'owned', 'price_paid' => 20]);
        $this->actingAs($user)->post("/games/{$mine->id}/mark-sold", ['sale_price' => 35, 'sold_at' => '2026-03-01']);

        $notMine = Game::factory()->for($otherUser)->create(['status' => 'owned', 'price_paid' => 20]);
        $this->actingAs($otherUser)->post("/games/{$notMine->id}/mark-sold",
            ['sale_price' => 35, 'sold_at' => '2026-03-01']);

        $response = $this->actingAs($user)->get('/stats');

        $salesByYear = $response->viewData('salesByYear');

        // groupBy()/format('Y') produce claves numéricas: PHP las convierte a
        // int automáticamente en el array subyacente de la Collection.
        $this->assertSame([2026], $salesByYear->keys()->all());
        $this->assertSame(1, $salesByYear[2026]['count']);
        $this->assertSame(20.0, $salesByYear[2026]['paid']);
        $this->assertSame(35.0, $salesByYear[2026]['sold']);
        $this->assertSame(15.0, $salesByYear[2026]['profit']);
        $this->assertSame(75.0, $salesByYear[2026]['profit_percent']);
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

    public function test_stats_keeps_month_label_when_today_is_the_thirty_first(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 31));

        try {
            $user = User::factory()->create();
            Game::factory()->for($user)->create(['purchase_date' => '2026-06-15', 'price_paid' => 10]);

            $byMonth = collect($this->actingAs($user)->get('/stats')->viewData('spendingByMonth'))->keyBy('label');

            $this->assertArrayHasKey('jun. 2026', $byMonth->all());
            $this->assertSame(10.0, $byMonth['jun. 2026']['total']);
        } finally {
            Carbon::setTestNow();
        }
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

    public function test_stats_breaks_down_games_by_decade_of_release_in_chronological_order(): void
    {
        $user = User::factory()->create();

        Game::factory()->for($user)->create(['release_date' => '2015-06-01']);
        Game::factory()->for($user)->create(['release_date' => '1998-01-01']);
        Game::factory()->for($user)->create(['release_date' => '2012-01-01']);
        Game::factory()->for($user)->create(['release_date' => null]);

        $response = $this->actingAs($user)->get('/stats');

        $byDecade = $response->viewData('byDecade');

        $this->assertSame(['Años 1990', 'Años 2010'], $byDecade->pluck('decade')->all());
        $this->assertSame(1, $byDecade->firstWhere('decade', 'Años 1990')['total']);
        $this->assertSame(2, $byDecade->firstWhere('decade', 'Años 2010')['total']);
    }

    public function test_stats_reflect_a_game_created_after_the_stats_were_first_cached(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create();

        $this->actingAs($user)->get('/stats')->assertViewHas('totalGames', 1);

        Game::factory()->for($user)->create();

        // Regresión: /stats se cachea por usuario (ver
        // StatsController::cacheKey()); sin invalidarla al crear un juego
        // (GameObserver::saved()), esta segunda carga seguiría devolviendo
        // el total de antes.
        $this->actingAs($user)->get('/stats')->assertViewHas('totalGames', 2);
    }

    public function test_stats_reflect_a_bulk_play_status_update(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['play_status' => 'pending']);

        $this->actingAs($user)->get('/stats');

        $this->actingAs($user)->post('/games/bulk-play-status', [
            'game_ids' => [$game->id],
            'play_status' => 'finished',
        ]);

        // Regresión: bulkUpdatePlayStatus() muta con Game::whereIn(...)->update(),
        // una query directa que no dispara el evento 'saved' de Eloquent, así
        // que GameObserver no la ve — GameController debe invalidar la caché
        // de estadísticas a mano ahí.
        $byPlayStatus = collect($this->actingAs($user)->get('/stats')->viewData('byPlayStatus'))->keyBy('label');
        $this->assertSame(1, $byPlayStatus['Terminado']['total']);
        $this->assertSame(0, $byPlayStatus['Pendiente']['total']);
    }

    public function test_stats_reflect_a_bulk_delete(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        $this->actingAs($user)->get('/stats')->assertViewHas('totalGames', 1);

        $this->actingAs($user)->post('/games/bulk-delete', ['game_ids' => [$game->id]]);

        // Regresión: bulkDestroy() muta con Game::whereIn(...)->delete(), que
        // tampoco dispara eventos de Eloquent.
        $this->actingAs($user)->get('/stats')->assertViewHas('totalGames', 0);
    }

    public function test_stats_highlights_the_most_expensive_and_top_rated_games(): void
    {
        $user = User::factory()->create();

        Game::factory()->for($user)->create(['title' => 'Barato', 'price_paid' => 5, 'rating' => 1]);
        $expensive = Game::factory()->for($user)->create(['title' => 'Caro', 'price_paid' => 90, 'rating' => 2]);
        $topRated = Game::factory()->for($user)->create([
            'title' => 'Mejor valorado', 'price_paid' => 20, 'rating' => 5,
        ]);

        $response = $this->actingAs($user)->get('/stats');

        $this->assertSame($expensive->id, $response->viewData('mostExpensive')->id);
        $this->assertSame($topRated->id, $response->viewData('topRated')->id);
    }
}
