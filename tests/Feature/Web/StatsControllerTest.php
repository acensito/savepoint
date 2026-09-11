<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\StatsController;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
        $platform = Platform::factory()->for($user)->create(['name' => 'Switch']);

        Game::factory()->for($user)->count(3)->create(['platform_id' => $platform->id]);

        $response = $this->actingAs($user)->get('/stats');

        $byPlatform = $response->viewData('byPlatform');

        $this->assertCount(1, $byPlatform);
        $this->assertSame(3, $byPlatform[0]['total']);
        $this->assertSame($platform->id, $byPlatform[0]['platform']->id);
    }

    public function test_stats_breaks_down_spending_by_platform(): void
    {
        $user = User::factory()->create();
        $switch = Platform::factory()->for($user)->create(['name' => 'Switch']);
        $ps4 = Platform::factory()->for($user)->create(['name' => 'PS4']);

        Game::factory()->for($user)->create(['platform_id' => $switch->id, 'price_paid' => 10]);
        Game::factory()->for($user)->create(['platform_id' => $switch->id, 'price_paid' => 15]);
        Game::factory()->for($user)->create(['platform_id' => $ps4->id, 'price_paid' => 5]);

        $response = $this->actingAs($user)->get('/stats');

        $byPlatformSpending = collect($response->viewData('byPlatformSpending'))
            ->keyBy(fn ($row) => $row['platform']->id);

        $this->assertSame(25.0, $byPlatformSpending[$switch->id]['total']);
        $this->assertSame(100.0, $byPlatformSpending[$switch->id]['percent']);
        $this->assertSame(5.0, $byPlatformSpending[$ps4->id]['total']);
        $this->assertSame(20.0, $byPlatformSpending[$ps4->id]['percent']);
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

    public function test_stats_breaks_down_games_by_rating(): void
    {
        $user = User::factory()->create();

        Game::factory()->for($user)->create(['rating' => 5]);
        Game::factory()->for($user)->create(['rating' => 5]);
        Game::factory()->for($user)->create(['rating' => 1]);
        Game::factory()->for($user)->create(['rating' => null]);

        $response = $this->actingAs($user)->get('/stats');

        $byRating = collect($response->viewData('byRating'))->keyBy('label');

        $this->assertSame(2, $byRating['Nuevo / precintado']['total']);
        $this->assertSame(50.0, $byRating['Nuevo / precintado']['percent']);
        $this->assertSame(1, $byRating['Malo']['total']);
        $this->assertSame(0, $byRating['Regular']['total']);
        $this->assertSame(1, $byRating['Sin valorar']['total']);
        $this->assertSame(25.0, $byRating['Sin valorar']['percent']);
    }

    /**
     * (2026-09-09): además del reparto de Conservación del total, uno
     * acotado a cada plataforma con su propio mini-resumen (total/gasto/
     * media) — para no tener que cruzar "Juegos por plataforma" a mano.
     */
    public function test_stats_breaks_down_rating_and_summary_per_platform(): void
    {
        $user = User::factory()->create();
        $switch = Platform::factory()->for($user)->create(['name' => 'Switch']);
        $ps4 = Platform::factory()->for($user)->create(['name' => 'PS4']);

        Game::factory()->for($user)->create(['platform_id' => $switch->id, 'rating' => 5, 'price_paid' => 10]);
        Game::factory()->for($user)->create(['platform_id' => $switch->id, 'rating' => 3, 'price_paid' => 20]);
        Game::factory()->for($user)->create(['platform_id' => $ps4->id, 'rating' => 1, 'price_paid' => 5]);

        $response = $this->actingAs($user)->get('/stats');

        $byPlatformRating = collect($response->viewData('byPlatformRating'))->keyBy(fn ($row) => $row['platform']->id);

        $switchRow = $byPlatformRating[$switch->id];
        $this->assertSame(2, $switchRow['total']);
        $this->assertSame(30.0, $switchRow['spent']);
        $this->assertSame(4.0, $switchRow['averageRating']);
        $this->assertSame(1, collect($switchRow['byRating'])->firstWhere('label', 'Nuevo / precintado')['total']);
        $this->assertSame(1, collect($switchRow['byRating'])->firstWhere('label', 'Bueno')['total']);

        $ps4Row = $byPlatformRating[$ps4->id];
        $this->assertSame(1, $ps4Row['total']);
        $this->assertSame(5.0, $ps4Row['spent']);
        $this->assertSame(1.0, $ps4Row['averageRating']);
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
        // int automáticamente en el array.
        $this->assertSame([2026], array_keys($salesByYear));
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

        $byDecade = collect($response->viewData('byDecade'));

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

    /**
     * Auditoría de rendimiento del 2026-09-10: antes cualquier saved()
     * invalidaba la caché, aunque el campo cambiado no entrara en ningún
     * cálculo de estadísticas — solo abrir la wishlist ya dispara hasta 20
     * guardados de cex_current_price/cex_checked_at (ver
     * Jobs\FetchCexWishlistPrice), tirando la caché de 15 min sin motivo.
     */
    public function test_stats_cache_is_not_invalidated_by_a_change_to_an_irrelevant_field(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['notes' => 'Notas originales']);

        $this->actingAs($user)->get('/stats');
        $this->assertTrue(Cache::has(StatsController::cacheKey($user->id)));

        // Recargado, no la misma instancia de creación: wasRecentlyCreated
        // se queda a true en el objeto en memoria aunque se le haga un
        // update() después — igual que Jobs\FetchCexWishlistPrice, que
        // siempre recarga el modelo por id en vez de reusar uno ya creado.
        Game::find($game->id)->update(['notes' => 'Notas nuevas', 'cex_current_price' => 9.99, 'cex_checked_at' => now()]);

        $this->assertTrue(Cache::has(StatsController::cacheKey($user->id)));
    }

    public function test_stats_cache_is_invalidated_by_a_change_to_a_stats_relevant_field(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['price_paid' => 10]);

        $this->actingAs($user)->get('/stats');
        $this->assertTrue(Cache::has(StatsController::cacheKey($user->id)));

        $game->update(['price_paid' => 20]);

        $this->assertFalse(Cache::has(StatsController::cacheKey($user->id)));
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
