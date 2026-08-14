<?php

namespace Tests\Feature\Web;

use App\Models\Edition;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_user_can_enable_auto_igdb_background(): void
    {
        $user = User::factory()->create(['auto_igdb_background' => false]);

        $response = $this->actingAs($user)->put('/panel/settings', [
            'auto_igdb_background' => '1',
        ]);

        $response->assertRedirect(route('web.panel.settings'));
        $this->assertTrue($user->fresh()->auto_igdb_background);
    }

    public function test_user_can_disable_auto_igdb_background_by_omitting_the_checkbox(): void
    {
        $user = User::factory()->create(['auto_igdb_background' => true]);

        $response = $this->actingAs($user)->put('/panel/settings', []);

        $response->assertRedirect(route('web.panel.settings'));
        $this->assertFalse($user->fresh()->auto_igdb_background);
    }

    public function test_updating_settings_does_not_affect_other_users(): void
    {
        $user = User::factory()->create(['auto_igdb_background' => false]);
        $otherUser = User::factory()->create(['auto_igdb_background' => false]);

        $this->actingAs($user)->put('/panel/settings', ['auto_igdb_background' => '1']);

        $this->assertFalse($otherUser->fresh()->auto_igdb_background);
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
            'quick_search_exclude_wishlist' => '1',
        ]);

        $response->assertRedirect(route('web.panel.settings'));

        $fresh = $user->fresh();
        $this->assertSame('title', $fresh->default_sort);
        $this->assertSame('asc', $fresh->default_dir);
        $this->assertSame(50, $fresh->default_per_page);
        $this->assertSame('NTSC-U', $fresh->default_region);
        $this->assertSame($edition->id, $fresh->default_edition_id);
        $this->assertTrue($fresh->quick_search_exclude_wishlist);
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
}
