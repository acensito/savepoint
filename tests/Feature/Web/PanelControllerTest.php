<?php

namespace Tests\Feature\Web;

use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_panel(): void
    {
        $this->get('/panel')->assertRedirect('/login');
    }

    public function test_panel_links_to_import_export_trash_and_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertOk();
        $response->assertSee(route('web.games.import'), false);
        $response->assertSee(route('web.games.print'), false);
        $response->assertSee(route('web.games.trash'), false);
        $response->assertSee(route('web.profile.edit'), false);
    }

    public function test_panel_shows_the_trashed_games_count_for_the_authenticated_user_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Game::factory()->for($user)->create()->delete();
        Game::factory()->for($user)->create()->delete();
        Game::factory()->for($otherUser)->create()->delete();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertSee('2 juegos en la papelera');
    }

    public function test_panel_shows_an_empty_trash_message_when_there_is_nothing_to_restore(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertSee('Vacía por ahora');
    }

    public function test_guest_cannot_access_settings(): void
    {
        $this->get('/panel/settings')->assertRedirect('/login');
    }

    public function test_settings_shows_the_auto_igdb_background_checkbox_unchecked_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/panel/settings');

        $response->assertOk();
        $content = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringContainsString('name="auto_igdb_background" value="1"', $content);
        $this->assertStringNotContainsString('name="auto_igdb_background" value="1" checked', $content);
    }

    public function test_user_can_update_collection_and_new_game_defaults(): void
    {
        $edition = Edition::factory()->create(['name' => 'Coleccionista']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/panel/settings', [
            'default_sort' => 'title',
            'default_dir' => 'asc',
            'default_per_page' => '50',
            'default_region' => 'NTSC-U',
            'default_edition_id' => (string) $edition->id,
        ]);

        $response->assertRedirect(route('web.panel.settings'));

        $fresh = $user->fresh();
        $this->assertSame('title', $fresh->default_sort);
        $this->assertSame('asc', $fresh->default_dir);
        $this->assertSame(50, $fresh->default_per_page);
        $this->assertSame('NTSC-U', $fresh->default_region);
        $this->assertSame($edition->id, $fresh->default_edition_id);
    }

    public function test_user_can_update_the_navbar_color(): void
    {
        $user = User::factory()->create(['navbar_color' => 'indigo']);

        $response = $this->actingAs($user)->put('/panel/settings', [
            'navbar_color' => 'emerald',
        ]);

        $response->assertRedirect(route('web.panel.settings'));
        $this->assertSame('emerald', $user->fresh()->navbar_color);
    }

    public function test_updating_settings_without_a_navbar_color_falls_back_to_indigo(): void
    {
        $user = User::factory()->create(['navbar_color' => 'rose']);

        $this->actingAs($user)->put('/panel/settings', []);

        $this->assertSame('indigo', $user->fresh()->navbar_color);
    }

    public function test_updating_settings_rejects_a_navbar_color_outside_the_presets(): void
    {
        $user = User::factory()->create(['navbar_color' => 'indigo']);

        $response = $this->actingAs($user)->put('/panel/settings', [
            'navbar_color' => 'not-a-real-color',
        ]);

        $response->assertSessionHasErrors('navbar_color');
        $this->assertSame('indigo', $user->fresh()->navbar_color);
    }

    public function test_settings_shows_the_igdb_checkbox_unchecked_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/panel/settings');

        $response->assertOk();
        $content = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringContainsString('name="igdb_enabled" value="1"', $content);
        $this->assertStringNotContainsString('name="igdb_enabled" value="1" checked', $content);
    }

    public function test_settings_never_reprints_a_saved_igdb_client_secret(): void
    {
        $user = User::factory()->create([
            'igdb_enabled' => true,
            'igdb_client_id' => 'existing-client-id',
            'igdb_client_secret' => 'existing-secret',
        ]);

        $response = $this->actingAs($user)->get('/panel/settings');

        $response->assertOk();
        $response->assertSee('existing-client-id', false);
        $response->assertDontSee('existing-secret', false);
    }

    public function test_user_can_set_igdb_credentials_without_touching_whether_igdb_is_enabled(): void
    {
        $user = User::factory()->create(['igdb_enabled' => true]);

        $response = $this->actingAs($user)->put('/panel/settings', [
            'igdb_client_id' => 'my-client-id',
            'igdb_client_secret' => 'my-client-secret',
        ]);

        $response->assertRedirect(route('web.panel.settings'));

        $fresh = $user->fresh();
        $this->assertTrue($fresh->igdb_enabled);
        $this->assertSame('my-client-id', $fresh->igdb_client_id);
        $this->assertSame('my-client-secret', $fresh->igdb_client_secret);
    }

    public function test_leaving_the_igdb_client_secret_blank_keeps_the_previously_saved_secret(): void
    {
        $user = User::factory()->create([
            'igdb_enabled' => true,
            'igdb_client_id' => 'old-client-id',
            'igdb_client_secret' => 'old-secret',
        ]);

        $this->actingAs($user)->put('/panel/settings', [
            'igdb_client_id' => 'new-client-id',
            'igdb_client_secret' => '',
        ]);

        $fresh = $user->fresh();
        $this->assertSame('new-client-id', $fresh->igdb_client_id);
        $this->assertSame('old-secret', $fresh->igdb_client_secret);
    }

    public function test_updating_igdb_credentials_does_not_affect_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->put('/panel/settings', [
            'igdb_client_id' => 'id',
            'igdb_client_secret' => 'secret',
        ]);

        $this->assertNull($otherUser->fresh()->igdb_client_id);
    }

    public function test_settings_shows_the_hide_for_sale_checkbox_unchecked_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/panel/settings');

        $response->assertOk();
        $content = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringContainsString('name="hide_for_sale_from_collection" value="1"', $content);
        $this->assertStringNotContainsString('name="hide_for_sale_from_collection" value="1" checked', $content);
    }

    public function test_settings_shows_the_highlight_low_rating_checkbox_checked_by_default(): void
    {
        // #155: activado por defecto, mismo comportamiento que #152 tenía
        // antes de poder desactivarse.
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/panel/settings');

        $response->assertOk();
        $content = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringContainsString('name="highlight_low_rating" value="1" checked', $content);
    }

    public function test_updating_settings_with_blank_selects_clears_the_defaults(): void
    {
        $edition = Edition::factory()->create();
        $user = User::factory()->create([
            'default_sort' => 'title',
            'default_region' => 'PAL-ES',
            'default_edition_id' => $edition->id,
        ]);

        $this->actingAs($user)->put('/panel/settings', [
            'default_sort' => '',
            'default_region' => '',
            'default_edition_id' => '',
        ]);

        $fresh = $user->fresh();
        $this->assertNull($fresh->default_sort);
        $this->assertNull($fresh->default_region);
        $this->assertNull($fresh->default_edition_id);
    }

    public function test_guest_cannot_update_display_preferences(): void
    {
        $this->patchJson('/panel/settings/display', ['theme' => 'light'])->assertUnauthorized();
    }

    public function test_user_can_update_theme_and_games_view(): void
    {
        $user = User::factory()->create(['theme' => 'dark', 'games_view' => 'compact']);

        $response = $this->actingAs($user)->patchJson('/panel/settings/display', [
            'theme' => 'light',
            'games_view' => 'grid',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $fresh = $user->fresh();
        $this->assertSame('light', $fresh->theme);
        $this->assertSame('grid', $fresh->games_view);
    }

    public function test_user_can_switch_to_the_text_only_games_view(): void
    {
        // #139: cuarta vista, sin carátulas.
        $user = User::factory()->create(['games_view' => 'compact']);

        $response = $this->actingAs($user)->patchJson('/panel/settings/display', ['games_view' => 'text']);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('text', $user->fresh()->games_view);
    }

    public function test_updating_display_preferences_accepts_a_partial_payload(): void
    {
        $user = User::factory()->create(['theme' => 'dark', 'games_view' => 'grid']);

        $this->actingAs($user)->patchJson('/panel/settings/display', ['theme' => 'light']);

        $fresh = $user->fresh();
        $this->assertSame('light', $fresh->theme);
        $this->assertSame('grid', $fresh->games_view);
    }

    public function test_updating_display_preferences_does_not_affect_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create(['theme' => 'dark']);

        $this->actingAs($user)->patchJson('/panel/settings/display', ['theme' => 'light']);

        $this->assertSame('dark', $otherUser->fresh()->theme);
    }

    public function test_guest_cannot_update_a_setting_toggle(): void
    {
        $this->patchJson('/panel/settings/toggles', ['field' => 'auto_igdb_background', 'value' => true])
            ->assertUnauthorized();
    }

    public function test_updating_a_setting_toggle_rejects_a_field_outside_the_whitelist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/panel/settings/toggles', [
            'field' => 'is_admin',
            'value' => true,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('field');
    }

    #[DataProvider('toggleFieldProvider')]
    public function test_user_can_toggle_a_setting_and_it_takes_effect_immediately(string $field): void
    {
        $user = User::factory()->create([$field => false]);

        $response = $this->actingAs($user)->patchJson('/panel/settings/toggles', [
            'field' => $field,
            'value' => true,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertTrue($user->fresh()->$field);

        $this->actingAs($user)->patchJson('/panel/settings/toggles', [
            'field' => $field,
            'value' => false,
        ]);

        $this->assertFalse($user->fresh()->$field);
    }

    #[DataProvider('toggleFieldProvider')]
    public function test_toggling_a_setting_does_not_affect_other_users(string $field): void
    {
        $user = User::factory()->create([$field => false]);
        $otherUser = User::factory()->create([$field => false]);

        $this->actingAs($user)->patchJson('/panel/settings/toggles', [
            'field' => $field,
            'value' => true,
        ]);

        $this->assertFalse($otherUser->fresh()->$field);
    }

    /**
     * Regresión (#10): sin esto, un usuario ya activo (con juegos, de toda
     * la vida) que activa 2FA hoy desde Ajustes quedaría indistinguible de
     * un registro huérfano nunca completado — ver
     * User::hasAbandonedTwoFactorChallenge() y
     * App\Services\Users\AbandonedAccountPruner.
     */
    public function test_enabling_two_factor_from_settings_marks_it_as_verified_immediately(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => false]);
        Game::factory()->for($user)->create();

        $this->actingAs($user)->patchJson('/panel/settings/toggles', [
            'field' => 'two_factor_enabled',
            'value' => true,
        ]);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->two_factor_enabled);
        $this->assertNotNull($fresh->two_factor_verified_at);
        $this->assertFalse($fresh->hasAbandonedTwoFactorChallenge());
    }

    public function test_disabling_two_factor_from_settings_does_not_touch_verified_at(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_verified_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->patchJson('/panel/settings/toggles', [
            'field' => 'two_factor_enabled',
            'value' => false,
        ]);

        $this->assertNotNull($user->fresh()->two_factor_verified_at);
    }

    /**
     * #144: zona de peligro del panel, vaciar todos los juegos de una
     * plataforma. Requiere teclear el nombre exacto (ver
     * PanelController::clearPlatformGames) — comprobado aquí en servidor,
     * el JS del propio botón es solo el freno del cliente.
     */
    public function test_user_can_clear_all_their_games_of_a_platform_with_the_exact_name(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Nintendo Switch']);
        $game = Game::factory()->for($user)->create(['platform_id' => $platform->id]);

        $response = $this->actingAs($user)->delete('/panel/platforms/games', [
            'platform_id' => $platform->id,
            'confirm' => 'Nintendo Switch',
        ]);

        $response->assertRedirect(route('web.panel.danger-zone'));
        $this->assertSoftDeleted($game);
        $this->assertNotNull(Platform::find($platform->id));
    }

    /**
     * #144 (seguimiento): los juegos sin ninguna plataforma asignada no
     * tenían forma de vaciarse en bloque (el desplegable solo ofrecía
     * plataformas reales) — platform_id='none' es el mismo sentinela que ya
     * usa ?platform_id=none en el listado.
     */
    public function test_user_can_clear_all_their_games_without_any_platform(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['platform_id' => null]);

        $response = $this->actingAs($user)->delete('/panel/platforms/games', [
            'platform_id' => 'none',
            'confirm' => 'Sin plataforma',
        ]);

        $response->assertRedirect(route('web.panel.danger-zone'));
        $this->assertSoftDeleted($game);
    }

    public function test_clearing_a_platform_rejects_a_confirmation_that_does_not_match_the_name(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Nintendo Switch']);
        $game = Game::factory()->for($user)->create(['platform_id' => $platform->id]);

        $response = $this->actingAs($user)->delete('/panel/platforms/games', [
            'platform_id' => $platform->id,
            'confirm' => 'nintendo switch',
        ]);

        $response->assertSessionHasErrors('confirm');
        $this->assertDatabaseHas('games', ['id' => $game->id, 'deleted_at' => null]);
    }

    public function test_clearing_a_platform_rejects_an_unknown_platform_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete('/panel/platforms/games', [
            'platform_id' => '999999',
            'confirm' => 'da igual',
        ]);

        $response->assertSessionHasErrors('platform_id');
    }

    public function test_clearing_a_platform_does_not_affect_another_users_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Nintendo Switch']);
        Game::factory()->for($user)->create(['platform_id' => $platform->id]);
        $otherGame = Game::factory()->for($otherUser)->create(['platform_id' => $platform->id]);

        $this->actingAs($user)->delete('/panel/platforms/games', [
            'platform_id' => $platform->id,
            'confirm' => 'Nintendo Switch',
        ]);

        $this->assertDatabaseHas('games', ['id' => $otherGame->id, 'deleted_at' => null]);
    }

    public function test_guest_cannot_clear_a_platform(): void
    {
        $platform = Platform::factory()->create();

        $this->delete('/panel/platforms/games', ['platform_id' => $platform->id, 'confirm' => $platform->name])
            ->assertRedirect('/login');
    }

    public function test_guest_cannot_access_the_danger_zone(): void
    {
        $this->get('/panel/danger-zone')->assertRedirect('/login');
    }

    public function test_danger_zone_lists_platforms_with_the_users_own_game_count(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Nintendo Switch']);
        Game::factory()->for($user)->create(['platform_id' => $platform->id]);
        Game::factory()->for($otherUser)->create(['platform_id' => $platform->id]);

        $response = $this->actingAs($user)->get('/panel/danger-zone');

        $response->assertOk();
        $response->assertSee('Nintendo Switch (1 juego)');
    }

    /**
     * #144: vaciar toda la colección, mismo criterio de confirmación que
     * clearPlatformGames() pero con un texto fijo (no hay un nombre propio
     * que teclear para "todo") — ver PanelController::CLEAR_ALL_CONFIRM_TEXT.
     */
    public function test_user_can_clear_their_entire_collection_with_the_exact_confirmation_text(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete('/panel/games', ['confirm' => 'BORRAR']);

        $response->assertRedirect(route('web.panel.danger-zone'));
        $this->assertSoftDeleted($game);
    }

    public function test_clearing_the_entire_collection_rejects_a_confirmation_that_does_not_match(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete('/panel/games', ['confirm' => 'borrar']);

        $response->assertSessionHasErrors('confirm_all');
        $this->assertDatabaseHas('games', ['id' => $game->id, 'deleted_at' => null]);
    }

    public function test_clearing_the_entire_collection_does_not_affect_another_users_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Game::factory()->for($user)->create();
        $otherGame = Game::factory()->for($otherUser)->create();

        $this->actingAs($user)->delete('/panel/games', ['confirm' => 'BORRAR']);

        $this->assertDatabaseHas('games', ['id' => $otherGame->id, 'deleted_at' => null]);
    }

    public function test_guest_cannot_clear_the_entire_collection(): void
    {
        $this->delete('/panel/games', ['confirm' => 'BORRAR'])->assertRedirect('/login');
    }

    public static function toggleFieldProvider(): array
    {
        return [
            'auto_igdb_background' => ['auto_igdb_background'],
            'quick_search_exclude_wishlist' => ['quick_search_exclude_wishlist'],
            'hide_for_sale_from_collection' => ['hide_for_sale_from_collection'],
            'highlight_low_rating' => ['highlight_low_rating'],
            'igdb_enabled' => ['igdb_enabled'],
            'two_factor_enabled' => ['two_factor_enabled'],
            'section_wishlist_enabled' => ['section_wishlist_enabled'],
            'section_commissions_enabled' => ['section_commissions_enabled'],
            'section_for_sale_enabled' => ['section_for_sale_enabled'],
            'section_sales_enabled' => ['section_sales_enabled'],
            'section_stats_enabled' => ['section_stats_enabled'],
        ];
    }
}
