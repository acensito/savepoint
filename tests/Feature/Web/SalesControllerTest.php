<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesControllerTest extends TestCase
{
    use RefreshDatabase;

    private function sellGame(User $user, array $attributes, string $soldAt, float $salePrice): Game
    {
        $game = Game::factory()->for($user)->create(['status' => 'owned', ...$attributes]);

        $this->actingAs($user)->post("/games/{$game->id}/mark-sold", [
            'sale_price' => $salePrice,
            'sold_at' => $soldAt,
        ]);

        return $game->fresh();
    }

    public function test_guest_cannot_access_sales(): void
    {
        $this->get('/sales')->assertRedirect('/login');
    }

    public function test_index_only_lists_the_authenticated_users_sold_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->sellGame($user, ['title' => 'Mío'], '2026-02-01', 30);
        $this->sellGame($otherUser, ['title' => 'Ajeno'], '2026-02-01', 30);

        // No usamos assertSee/assertDontSee: el toast de la venta de "Ajeno"
        // (petición anterior, mismo cliente de test) sigue en el flash de la
        // sesión durante esta petición y aparecería en el HTML igualmente.
        $response = $this->actingAs($user)->get('/sales');

        $titles = $response->viewData('byYear')->flatMap(fn ($year) => $year['games'])->pluck('title');
        $this->assertSame(['Mío'], $titles->all());
    }

    public function test_index_groups_sales_by_year_with_totals_and_profit(): void
    {
        $user = User::factory()->create();

        $this->sellGame($user, ['title' => 'Uno 2025', 'price_paid' => 20], '2025-06-01', 30);
        $this->sellGame($user, ['title' => 'Dos 2025', 'price_paid' => 10], '2025-07-01', 5);
        $this->sellGame($user, ['title' => 'Uno 2026', 'price_paid' => 50], '2026-01-01', 40);

        $response = $this->actingAs($user)->get('/sales');

        $byYear = $response->viewData('byYear');

        // groupBy()/format('Y') produce claves numéricas: PHP las convierte a
        // int automáticamente en el array subyacente de la Collection.
        $this->assertSame([2026, 2025], $byYear->keys()->all());

        $this->assertSame(2, $byYear[2025]['count']);
        $this->assertSame(30.0, $byYear[2025]['paid']);
        $this->assertSame(35.0, $byYear[2025]['sold']);
        $this->assertSame(5.0, $byYear[2025]['profit']);

        $this->assertSame(1, $byYear[2026]['count']);
        $this->assertSame(-10.0, $byYear[2026]['profit']);
    }

    public function test_index_shows_an_empty_state_when_there_are_no_sales(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/sales');

        $response->assertSee('Todavía no has marcado ningún juego como vendido.');
    }

    public function test_restore_undoes_the_sale_and_the_game_reappears_in_the_collection(): void
    {
        $user = User::factory()->create();
        $game = $this->sellGame($user, ['title' => 'Recuperado'], '2026-02-01', 30);

        $response = $this->actingAs($user)->post("/sales/{$game->id}/restore");

        $response->assertRedirect(route('web.sales.index'));

        $game->refresh();
        $this->assertNull($game->deleted_at);
        $this->assertSame('owned', $game->status);
        $this->assertFalse($game->for_sale);
        $this->assertNull($game->sale_price);
        $this->assertNull($game->sold_at);

        $collection = $this->actingAs($user)->get('/');
        $collection->assertSee('Recuperado');
    }

    public function test_user_cannot_restore_another_users_sold_game(): void
    {
        $owner = User::factory()->create();
        $game = $this->sellGame($owner, [], '2026-02-01', 30);

        $response = $this->actingAs(User::factory()->create())->post("/sales/{$game->id}/restore");

        $response->assertForbidden();
        $this->assertSoftDeleted('games', ['id' => $game->id]);
    }

    public function test_user_can_mark_their_own_game_as_sold(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['status' => 'owned', 'for_sale' => true, 'price_paid' => 40]);

        $response = $this->actingAs($user)->post("/games/{$game->id}/mark-sold", [
            'sale_price' => 55,
            'sold_at' => '2026-03-10',
            'notes' => 'Vendido en mano',
        ]);

        $response->assertRedirect(route('web.games.index'));
        $response->assertSessionHas('undoUrl', route('web.sales.restore', $game->id));

        $this->assertSoftDeleted('games', ['id' => $game->id]);

        $game->refresh();
        $this->assertSame('sold', $game->status);
        $this->assertFalse($game->for_sale);
        $this->assertSame('55.00', $game->sale_price);
        $this->assertSame('2026-03-10', $game->sold_at->format('Y-m-d'));
        $this->assertSame('Vendido en mano', $game->notes);
    }

    public function test_marking_a_game_as_sold_requires_a_price_and_a_date(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['status' => 'owned']);

        $response = $this->actingAs($user)->post("/games/{$game->id}/mark-sold", []);

        $response->assertSessionHasErrors(['sale_price', 'sold_at']);
        $this->assertDatabaseHas('games', ['id' => $game->id, 'deleted_at' => null, 'status' => 'owned']);
    }

    public function test_user_cannot_mark_another_users_game_as_sold(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create(['status' => 'owned']);

        $response = $this->actingAs(User::factory()->create())->post("/games/{$game->id}/mark-sold", [
            'sale_price' => 10,
            'sold_at' => '2026-03-10',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('games', ['id' => $game->id, 'deleted_at' => null, 'status' => 'owned']);
    }
}
