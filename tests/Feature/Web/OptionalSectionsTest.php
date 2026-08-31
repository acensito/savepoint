<?php

namespace Tests\Feature\Web;

use App\Models\Commission;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Secciones opcionales (issue #32): Lista de deseos, Encargos, En venta,
 * Ventas y Estadísticas se pueden desactivar desde /panel/settings (toggles
 * ya cubiertos genéricamente en PanelControllerTest::toggleFieldProvider).
 * Aquí se cubre lo específico de cada sección: que desaparece del sidebar,
 * que su(s) ruta(s) devuelven 404 en vez de la página, que los datos ya
 * existentes no se tocan, y que las acciones que viven fuera de la sección
 * (marcar en venta/vendido desde la colección) siguen disponibles.
 */
class OptionalSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_optional_sections_are_enabled_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->section_wishlist_enabled);
        $this->assertTrue($user->section_commissions_enabled);
        $this->assertTrue($user->section_for_sale_enabled);
        $this->assertTrue($user->section_sales_enabled);
        $this->assertTrue($user->section_stats_enabled);
    }

    public function test_the_sidebar_hides_a_disabled_sections_link(): void
    {
        $user = User::factory()->create(['section_wishlist_enabled' => false]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertDontSee(route('web.wishlist.index'), false);
        // El resto de secciones, todavía activas por defecto, siguen ahí.
        $response->assertSee(route('web.commissions.index'), false);
        $response->assertSee(route('web.for-sale.index'), false);
        $response->assertSee(route('web.sales.index'), false);
        $response->assertSee(route('web.stats.index'), false);
    }

    public function test_the_sidebar_shows_all_sections_when_all_are_enabled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee(route('web.wishlist.index'), false);
        $response->assertSee(route('web.commissions.index'), false);
        $response->assertSee(route('web.for-sale.index'), false);
        $response->assertSee(route('web.sales.index'), false);
        $response->assertSee(route('web.stats.index'), false);
    }

    public function test_a_disabled_wishlist_section_returns_404_on_its_routes(): void
    {
        $user = User::factory()->create(['section_wishlist_enabled' => false]);

        $this->actingAs($user)->get('/wishlist')->assertNotFound();
        $this->actingAs($user)->get('/wishlist/create')->assertNotFound();
        $this->actingAs($user)->post('/wishlist', ['title' => 'Silksong'])->assertNotFound();
        $this->assertDatabaseCount('games', 0);
    }

    public function test_a_disabled_commissions_section_returns_404_on_all_its_routes(): void
    {
        $user = User::factory()->create(['section_commissions_enabled' => false]);
        $commission = Commission::factory()->for($user)->create();

        $this->actingAs($user)->get('/commissions')->assertNotFound();
        $this->actingAs($user)->get('/commissions/create')->assertNotFound();
        $this->actingAs($user)->post('/commissions', ['title' => 'Celeste'])->assertNotFound();
        $this->actingAs($user)->get("/commissions/{$commission->id}/edit")->assertNotFound();
        $this->actingAs($user)->put("/commissions/{$commission->id}", ['title' => 'Nuevo'])->assertNotFound();
        $this->actingAs($user)->post("/commissions/{$commission->id}/resolve")->assertNotFound();
        $this->actingAs($user)->delete("/commissions/{$commission->id}")->assertNotFound();
    }

    public function test_a_disabled_for_sale_section_returns_404_but_marking_a_game_for_sale_still_works(): void
    {
        $user = User::factory()->create(['section_for_sale_enabled' => false]);
        $game = Game::factory()->for($user)->create(['for_sale' => false]);

        $this->actingAs($user)->get('/for-sale')->assertNotFound();

        // Marcar un juego como "en venta" no vive bajo /for-sale (ver
        // GameController::quickUpdate): sigue disponible aunque la sección
        // de mantenimiento esté desactivada.
        $this->actingAs($user)->patchJson("/games/{$game->id}/quick-update", ['for_sale' => true])
            ->assertOk();
        $this->assertTrue($game->fresh()->for_sale);
    }

    public function test_a_disabled_sales_section_returns_404_but_marking_a_game_as_sold_still_works(): void
    {
        $user = User::factory()->create(['section_sales_enabled' => false]);
        $game = Game::factory()->for($user)->create(['status' => 'owned']);

        $this->actingAs($user)->get('/sales')->assertNotFound();
        $this->actingAs($user)->post("/sales/{$game->id}/restore")->assertNotFound();

        // Marcar como vendido no vive bajo /sales (ver SalesController::markAsSold,
        // registrada como /games/{game}/mark-sold): sigue disponible.
        $this->actingAs($user)->post("/games/{$game->id}/mark-sold", [
            'sale_price' => 50,
            'sold_at' => '2026-03-10',
        ])->assertRedirect(route('web.games.index'));
        $this->assertSame('sold', $game->fresh()->status);
    }

    public function test_a_disabled_stats_section_returns_404(): void
    {
        $user = User::factory()->create(['section_stats_enabled' => false]);

        $this->actingAs($user)->get('/stats')->assertNotFound();
    }

    public function test_disabling_a_section_does_not_delete_or_change_its_existing_data(): void
    {
        $user = User::factory()->create();
        $wishlistGame = Game::factory()->for($user)->create(['status' => 'wishlist']);
        $forSaleGame = Game::factory()->for($user)->create(['for_sale' => true]);
        $commission = Commission::factory()->for($user)->create();

        $this->actingAs($user)->patchJson('/panel/settings/toggles', [
            'field' => 'section_wishlist_enabled', 'value' => false,
        ]);
        $this->actingAs($user)->patchJson('/panel/settings/toggles', [
            'field' => 'section_for_sale_enabled', 'value' => false,
        ]);
        $this->actingAs($user)->patchJson('/panel/settings/toggles', [
            'field' => 'section_commissions_enabled', 'value' => false,
        ]);

        $this->assertSame('wishlist', $wishlistGame->fresh()->status);
        $this->assertTrue($forSaleGame->fresh()->for_sale);
        $this->assertDatabaseHas('commissions', ['id' => $commission->id]);
    }

    public function test_quick_search_excludes_wishlist_games_when_the_section_is_disabled(): void
    {
        $user = User::factory()->create([
            'section_wishlist_enabled' => false,
            'quick_search_exclude_wishlist' => false,
        ]);
        Game::factory()->for($user)->create(['title' => 'Silksong deseado', 'status' => 'wishlist']);

        $response = $this->actingAs($user)->get('/search/quick?q=Silksong');

        $response->assertOk();
        $response->assertDontSee('Silksong deseado');
    }

    public function test_quick_search_still_shows_wishlist_games_when_the_section_is_enabled(): void
    {
        $user = User::factory()->create([
            'section_wishlist_enabled' => true,
            'quick_search_exclude_wishlist' => false,
        ]);
        Game::factory()->for($user)->create(['title' => 'Silksong deseado', 'status' => 'wishlist']);

        $response = $this->actingAs($user)->get('/search/quick?q=Silksong');

        $response->assertOk();
        $response->assertSee('Silksong deseado');
    }
}
