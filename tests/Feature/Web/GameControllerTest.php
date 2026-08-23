<?php

namespace Tests\Feature\Web;

use App\Jobs\MatchGameWithIgdb;
use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use App\Services\GameLookup\IgdbGameMatch;
use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_lists_the_authenticated_users_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Game::factory()->for($user)->create(['title' => 'Mi juego']);
        Game::factory()->for($otherUser)->create(['title' => 'Juego ajeno']);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Mi juego');
        $response->assertDontSee('Juego ajeno');
    }

    public function test_index_never_lists_wishlist_games_even_when_filtered_by_status(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Deseado', 'status' => 'wishlist']);
        Game::factory()->for($user)->create(['title' => 'Mío', 'status' => 'owned']);

        $response = $this->actingAs($user)->get('/?status=wishlist');

        $response->assertSee('No hay juegos', false);
        $response->assertDontSee('Deseado');
    }

    public function test_collection_totals_exclude_wishlist_games(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['status' => 'owned', 'price_paid' => 20]);
        Game::factory()->for($user)->create(['status' => 'wishlist', 'price_paid' => null]);

        $response = $this->actingAs($user)->get('/');

        $this->assertSame(1, $response->viewData('collectionTotals')['count']);
        $this->assertSame(20.0, $response->viewData('collectionTotals')['spent']);
    }

    public function test_index_filters_by_for_sale(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'En venta', 'for_sale' => true]);
        Game::factory()->for($user)->create(['title' => 'No en venta', 'for_sale' => false]);

        $response = $this->actingAs($user)->get('/?for_sale=1');

        $games = $response->viewData('games');
        $this->assertCount(1, $games);
        $this->assertSame('En venta', $games->first()->title);
    }

    public function test_index_shows_for_sale_games_by_default(): void
    {
        $user = User::factory()->create(['hide_for_sale_from_collection' => false]);
        Game::factory()->for($user)->create(['title' => 'En venta', 'for_sale' => true]);

        $response = $this->actingAs($user)->get('/');

        $this->assertCount(1, $response->viewData('games'));
    }

    public function test_index_hides_for_sale_games_when_the_setting_is_enabled(): void
    {
        $user = User::factory()->create(['hide_for_sale_from_collection' => true]);
        Game::factory()->for($user)->create(['title' => 'En venta', 'for_sale' => true]);
        Game::factory()->for($user)->create(['title' => 'No en venta', 'for_sale' => false]);

        $response = $this->actingAs($user)->get('/');

        $games = $response->viewData('games');
        $this->assertCount(1, $games);
        $this->assertSame('No en venta', $games->first()->title);
    }

    public function test_index_for_sale_filter_still_works_when_the_setting_hides_them_by_default(): void
    {
        $user = User::factory()->create(['hide_for_sale_from_collection' => true]);
        Game::factory()->for($user)->create(['title' => 'En venta', 'for_sale' => true]);
        Game::factory()->for($user)->create(['title' => 'No en venta', 'for_sale' => false]);

        $response = $this->actingAs($user)->get('/?for_sale=1');

        $games = $response->viewData('games');
        $this->assertCount(1, $games);
        $this->assertSame('En venta', $games->first()->title);
    }

    public function test_editing_with_convert_to_owned_preselects_owned_status_and_todays_purchase_date(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['status' => 'wishlist', 'purchase_date' => null]);

        $response = $this->actingAs($user)->get(route('web.games.edit', ['game' => $game->id, 'convert_to_owned' => 1]));

        $response->assertOk();
        $response->assertSee('value="owned" selected', false);
        $response->assertSee('value="'.now()->format('Y-m-d').'"', false);
    }

    public function test_editing_normally_keeps_the_games_current_status(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['status' => 'wishlist']);

        $response = $this->actingAs($user)->get(route('web.games.edit', $game->id));

        $response->assertOk();
        $response->assertSee('value="wishlist" selected', false);
    }

    public function test_creating_a_game_preselects_the_users_default_edition(): void
    {
        $edition = Edition::factory()->create(['name' => 'Coleccionista']);
        $user = User::factory()->create(['default_edition_id' => $edition->id]);

        $response = $this->actingAs($user)->get(route('web.games.create'));

        $response->assertOk();
        $content = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringContainsString('value="'.$edition->id.'" data-platforms="" selected', $content);
    }

    public function test_creating_a_game_does_not_preselect_an_edition_without_a_default_configured(): void
    {
        // User::factory() no fija default_edition_id (null salvo backfill de
        // usuarios ya existentes en la migración, que no aplica a un usuario
        // creado aquí mismo): sin ajuste configurado, no se preselecciona nada.
        $user = User::factory()->create();
        $normal = Edition::where('name', 'Normal')->firstOrFail();

        $response = $this->actingAs($user)->get(route('web.games.create'));

        $response->assertOk();
        $content = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringNotContainsString('value="'.$normal->id.'" data-platforms="" selected', $content);
    }

    public function test_creating_a_game_preselects_the_users_default_region(): void
    {
        $user = User::factory()->create(['default_region' => 'NTSC-U']);

        $response = $this->actingAs($user)->get(route('web.games.create'));

        $response->assertOk();
        $content = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringContainsString('value="NTSC-U" selected', $content);
    }

    public function test_editing_a_game_keeps_its_own_edition_instead_of_normal(): void
    {
        $user = User::factory()->create();
        $edition = Edition::factory()->create(['name' => 'Coleccionista']);
        $game = Game::factory()->for($user)->create(['edition_id' => $edition->id]);

        $response = $this->actingAs($user)->get(route('web.games.edit', $game->id));

        $response->assertOk();
        $content = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringContainsString('value="'.$edition->id.'" data-platforms="" selected', $content);
    }

    public function test_editing_a_game_without_an_edition_does_not_default_to_normal(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['edition_id' => null]);

        $response = $this->actingAs($user)->get(route('web.games.edit', $game->id));

        $response->assertOk();
        $normal = Edition::where('name', 'Normal')->firstOrFail();
        $content = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringNotContainsString('value="'.$normal->id.'" data-platforms="" selected', $content);
    }

    public function test_index_sorts_by_title_ascending(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Zelda']);
        Game::factory()->for($user)->create(['title' => 'Celeste']);

        $response = $this->actingAs($user)->get('/?sort=title&dir=asc');

        $titles = $response->viewData('games')->pluck('title')->all();
        $this->assertSame(['Celeste', 'Zelda'], $titles);
    }

    public function test_index_sorts_by_price_descending_by_default(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Barato', 'price_paid' => 5]);
        Game::factory()->for($user)->create(['title' => 'Caro', 'price_paid' => 50]);

        $response = $this->actingAs($user)->get('/?sort=price_paid');

        $titles = $response->viewData('games')->pluck('title')->all();
        $this->assertSame(['Caro', 'Barato'], $titles);
    }

    public function test_index_returns_a_partial_fragment_for_ajax_requests(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Hollow Knight']);

        $response = $this->actingAs($user)->get('/', ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertSee('Hollow Knight');
        // El fragmento AJAX no lleva el layout completo (sidebar, cabecera...).
        $response->assertDontSee('SavePoint - Mi Colección');
        $response->assertSee('games-results-meta', false);
    }

    public function test_index_shows_the_real_format_icon_of_the_games_edition(): void
    {
        $user = User::factory()->create();
        $digital = Edition::factory()->create(['name' => 'Edición digital', 'format' => Edition::FORMAT_DIGITAL]);
        Game::factory()->for($user)->create(['title' => 'Juego digital', 'edition_id' => $digital->id]);

        $response = $this->actingAs($user)->get('/');

        // Regresión: index() cargaba la relación edition sin la columna
        // format, así que Edition::formatIcon() caía siempre al icono de
        // físico (album) sin importar el formato real de la edición.
        $response->assertSee(Edition::FORMATS[Edition::FORMAT_DIGITAL]['icon']);
    }

    public function test_index_ignores_an_unknown_sort_column(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create();

        $this->actingAs($user)->get('/?sort=user_id')->assertOk();
    }

    public function test_index_paginates_using_the_requested_per_page(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->count(15)->create();

        $response = $this->actingAs($user)->get('/?per_page=10');

        $this->assertCount(10, $response->viewData('games')->items());
    }

    public function test_index_ignores_an_invalid_per_page_and_falls_back_to_20(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->count(25)->create();

        $response = $this->actingAs($user)->get('/?per_page=999');

        $this->assertCount(20, $response->viewData('games')->items());
    }

    public function test_index_uses_the_users_default_per_page_when_the_url_has_none(): void
    {
        $user = User::factory()->create(['default_per_page' => 10]);
        Game::factory()->for($user)->count(15)->create();

        $response = $this->actingAs($user)->get('/');

        $this->assertCount(10, $response->viewData('games')->items());
    }

    public function test_index_ignores_an_invalid_per_page_and_falls_back_to_the_users_default(): void
    {
        $user = User::factory()->create(['default_per_page' => 10]);
        Game::factory()->for($user)->count(15)->create();

        $response = $this->actingAs($user)->get('/?per_page=999');

        $this->assertCount(10, $response->viewData('games')->items());
    }

    public function test_index_explicit_per_page_in_the_url_overrides_the_users_default(): void
    {
        $user = User::factory()->create(['default_per_page' => 10]);
        Game::factory()->for($user)->count(15)->create();

        $response = $this->actingAs($user)->get('/?per_page=20');

        $this->assertCount(15, $response->viewData('games')->items());
    }

    public function test_index_uses_the_users_default_sort_when_the_url_has_none(): void
    {
        $user = User::factory()->create(['default_sort' => 'title', 'default_dir' => 'asc']);
        // Alpha creado antes (más antiguo) que Bravo (más reciente): así el
        // orden alfabético ("título") y el de "más recientes" dan resultados
        // opuestos, y el test distingue de verdad cuál se está aplicando.
        Game::factory()->for($user)->create(['title' => 'Alpha']);
        Game::factory()->for($user)->create(['title' => 'Bravo']);

        $response = $this->actingAs($user)->get('/');

        $titles = $response->viewData('games')->pluck('title')->all();
        $this->assertSame(['Alpha', 'Bravo'], $titles);
    }

    public function test_index_explicit_sort_in_the_url_overrides_the_users_default(): void
    {
        $user = User::factory()->create(['default_sort' => 'title', 'default_dir' => 'asc']);
        Game::factory()->for($user)->create(['title' => 'Alpha']);
        Game::factory()->for($user)->create(['title' => 'Bravo']);

        // ?sort= vacío es una elección válida ("más recientes"), no "ausente":
        // debe ganar sobre el default del usuario (que ordenaría alfabéticamente).
        $response = $this->actingAs($user)->get('/?sort=');

        $titles = $response->viewData('games')->pluck('title')->all();
        $this->assertSame(['Bravo', 'Alpha'], $titles);
    }

    public function test_index_shows_totals_for_the_whole_collection_regardless_of_filters(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Match', 'price_paid' => 10]);
        Game::factory()->for($user)->create(['title' => 'Other', 'price_paid' => 5]);

        $response = $this->actingAs($user)->get('/?q=Match');

        $this->assertSame(1, $response->viewData('games')->total());
        $this->assertSame(2, $response->viewData('collectionTotals')['count']);
        $this->assertSame(15.0, $response->viewData('collectionTotals')['spent']);
    }

    public function test_print_lists_all_matching_games_ignoring_pagination(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->count(25)->create();

        $response = $this->actingAs($user)->get('/games/print');

        $response->assertOk();
        $this->assertCount(25, $response->viewData('games'));
    }

    public function test_print_applies_the_same_filters_as_the_collection_listing(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Match', 'platform_id' => $platform->id]);
        Game::factory()->for($user)->create(['title' => 'Other']);
        Game::factory()->for($user)->create(['title' => 'Deseado', 'status' => 'wishlist']);

        $response = $this->actingAs($user)->get('/games/print?q=Match');

        $games = $response->viewData('games');
        $this->assertCount(1, $games);
        $this->assertSame('Match', $games->first()->title);
    }

    public function test_print_only_lists_the_authenticated_users_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Game::factory()->for($user)->create();
        Game::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get('/games/print');

        $this->assertCount(1, $response->viewData('games'));
    }

    /**
     * Regresión: games/print-collection.blade.php es una vista independiente
     * a propósito, sin `layouts.app` (ver comentario en el propio fichero).
     * `viewData('games')` no lo habría pillado nunca: el bug real estaba en
     * el HTML compilado, no en los datos pasados a la vista — el comentario
     * CSS que explicaba "no usa @extends(...)" contenía el texto literal de
     * la directiva sin escapar, y Blade la compilaba igual aunque solo
     * apareciera mencionada dentro de un comentario, pegando el layout
     * completo de la app (sidebar, header, diálogos) al final del HTML como
     * una "página" extra sin ningún juego al exportar a PDF.
     */
    public function test_print_view_is_self_contained_without_the_app_layout(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Chrono Trigger']);

        $response = $this->actingAs($user)->get('/games/print');

        $response->assertOk();
        $response->assertSee('Chrono Trigger');
        $response->assertDontSee('id="sidebar"', false);
        $response->assertDontSee('quick-search-dialog', false);
        $this->assertSame(1, substr_count(strtolower($response->getContent()), '<!doctype html'));
    }

    public function test_export_returns_a_csv_with_the_same_headers_the_importer_expects(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Nintendo Switch']);
        Game::factory()->for($user)->create([
            'title' => 'Celeste',
            'ean' => '0812872018012',
            'platform_id' => $platform->id,
            'genres' => ['Plataformas', 'Indie'],
            'status' => 'owned',
            'play_status' => 'finished',
            'rating' => 5,
            'manual_status' => 'missing',
        ]);

        $response = $this->actingAs($user)->get('/games/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Título,EAN,Desarrollador,Plataforma,Edición', $csv);
        $this->assertStringContainsString('Celeste,0812872018012,', $csv);
        $this->assertStringContainsString('Nintendo Switch', $csv);
        $this->assertStringContainsString('"Plataformas, Indie"', $csv);
        $this->assertStringContainsString('En colección', $csv);
        $this->assertStringContainsString('Terminado', $csv);
        $this->assertStringContainsString('Sin Manual', $csv);
    }

    public function test_export_applies_the_same_filters_as_the_collection_listing(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Match']);
        Game::factory()->for($user)->create(['title' => 'Other']);
        Game::factory()->for($user)->create(['title' => 'Deseado', 'status' => 'wishlist']);

        $response = $this->actingAs($user)->get('/games/export?q=Match');

        $csv = $response->getContent();
        $this->assertStringContainsString('Match', $csv);
        $this->assertStringNotContainsString('Other', $csv);
        $this->assertStringNotContainsString('Deseado', $csv);
    }

    public function test_export_only_lists_the_authenticated_users_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Mine']);
        Game::factory()->for($otherUser)->create(['title' => 'NotMine']);

        $response = $this->actingAs($user)->get('/games/export');

        $csv = $response->getContent();
        $this->assertStringContainsString('Mine', $csv);
        $this->assertStringNotContainsString('NotMine', $csv);
    }

    public function test_user_can_bulk_delete_their_own_games(): void
    {
        $user = User::factory()->create();
        $keep = Game::factory()->for($user)->create();
        $trash1 = Game::factory()->for($user)->create();
        $trash2 = Game::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/games/bulk-delete', [
            'game_ids' => [$trash1->id, $trash2->id],
        ]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertSoftDeleted('games', ['id' => $trash1->id]);
        $this->assertSoftDeleted('games', ['id' => $trash2->id]);
        $this->assertDatabaseHas('games', ['id' => $keep->id, 'deleted_at' => null]);
    }

    public function test_bulk_delete_ignores_games_belonging_to_other_users(): void
    {
        $user = User::factory()->create();
        $otherUsersGame = Game::factory()->for(User::factory())->create();

        $this->actingAs($user)->post('/games/bulk-delete', [
            'game_ids' => [$otherUsersGame->id],
        ])->assertRedirect(route('web.games.index'));

        $this->assertDatabaseHas('games', ['id' => $otherUsersGame->id, 'deleted_at' => null]);
    }

    public function test_bulk_delete_requires_at_least_one_selected_game(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/games/bulk-delete', [])
            ->assertSessionHasErrors('game_ids');
    }

    public function test_user_can_bulk_update_the_play_status_of_their_own_games(): void
    {
        $user = User::factory()->create();
        $game1 = Game::factory()->for($user)->create(['play_status' => 'pending']);
        $game2 = Game::factory()->for($user)->create(['play_status' => 'pending']);

        $response = $this->actingAs($user)->post('/games/bulk-play-status', [
            'game_ids' => [$game1->id, $game2->id],
            'play_status' => 'finished',
        ]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['id' => $game1->id, 'play_status' => 'finished']);
        $this->assertDatabaseHas('games', ['id' => $game2->id, 'play_status' => 'finished']);
    }

    public function test_bulk_update_play_status_ignores_games_belonging_to_other_users(): void
    {
        $user = User::factory()->create();
        $otherUsersGame = Game::factory()->for(User::factory())->create(['play_status' => 'pending']);

        $this->actingAs($user)->post('/games/bulk-play-status', [
            'game_ids' => [$otherUsersGame->id],
            'play_status' => 'finished',
        ])->assertRedirect(route('web.games.index'));

        $this->assertDatabaseHas('games', ['id' => $otherUsersGame->id, 'play_status' => 'pending']);
    }

    public function test_bulk_update_play_status_requires_a_valid_value(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        $this->actingAs($user)->post('/games/bulk-play-status', [
            'game_ids' => [$game->id],
            'play_status' => 'not-a-real-status',
        ])->assertSessionHasErrors('play_status');
    }

    public function test_user_can_create_a_game_with_a_cover(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        $cover = UploadedFile::fake()->image('cover.jpg');

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Celeste',
            'platform_id' => $platform->id,
            'play_status' => 'playing',
            'cover' => $cover,
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game = Game::where('title', 'Celeste')->firstOrFail();

        $this->assertSame($user->id, $game->user_id);
        $this->assertNotNull($game->cover);
        Storage::disk('public')->assertExists($game->cover);
    }

    public function test_creating_a_game_downloads_the_suggested_cover_from_an_allowed_host(): void
    {
        Storage::fake('public');
        Http::fake(['es.static.webuy.com/*' => Http::response('fake-jpeg-bytes', 200, ['Content-Type' => 'image/jpeg'])]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Hollow Knight',
            'play_status' => 'pending',
            'cover_url' => 'https://es.static.webuy.com/product_images/hollow_knight_l.jpg',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game = Game::where('title', 'Hollow Knight')->firstOrFail();
        $this->assertNotNull($game->cover);
        $this->assertStringEndsWith('.jpg', $game->cover);
        Storage::disk('public')->assertExists($game->cover);
    }

    public function test_creating_a_game_ignores_a_cover_url_from_a_host_that_is_not_allowlisted(): void
    {
        Storage::fake('public');
        Http::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Juego SSRF',
            'play_status' => 'pending',
            // Un host cualquiera que no sea el CDN de CEX: ni siquiera debe
            // intentarse la petición (protección SSRF en downloadExternalCover).
            'cover_url' => 'https://169.254.169.254/latest/meta-data/',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game = Game::where('title', 'Juego SSRF')->firstOrFail();
        $this->assertNull($game->cover);
        Http::assertNothingSent();
    }

    public function test_creating_a_game_ignores_cover_url_when_remove_cover_is_checked(): void
    {
        Storage::fake('public');
        Http::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Sin carátula',
            'play_status' => 'pending',
            'cover_url' => 'https://es.static.webuy.com/product_images/x_l.jpg',
            'remove_cover' => '1',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game = Game::where('title', 'Sin carátula')->firstOrFail();
        $this->assertNull($game->cover);
        Http::assertNothingSent();
    }

    public function test_creating_a_game_with_auto_igdb_background_enabled_sets_the_first_artwork(): void
    {
        $user = User::factory()->create(['auto_igdb_background' => true]);
        $platform = Platform::factory()->create(['name' => 'Nintendo Switch']);

        $this->mock(IgdbLookupService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->once()
                ->with('Celeste', 'Nintendo Switch', 10)
                ->andReturn([new IgdbGameMatch(
                    igdbId: 305,
                    title: 'Celeste',
                    platforms: 'Nintendo Switch',
                    developer: 'Maddy Makes Games',
                    releaseDate: '2018-01-25',
                    genres: ['Platform', 'Indie'],
                    rating: 87.65,
                )]);
            $mock->shouldReceive('artworks')->once()->with(305)->andReturn(['ar1abc', 'ar2def']);
        });

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Celeste',
            'platform_id' => $platform->id,
            'play_status' => 'pending',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game = Game::where('title', 'Celeste')->firstOrFail();
        $this->assertSame(305, $game->igdb_id);
        $this->assertSame('ar1abc', $game->igdb_background);
    }

    public function test_creating_a_game_with_auto_igdb_background_disabled_leaves_the_background_empty(): void
    {
        $user = User::factory()->create(['auto_igdb_background' => false]);

        $this->mock(IgdbLookupService::class, function ($mock) {
            $mock->shouldNotReceive('search');
            $mock->shouldNotReceive('artworks');
        });

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Celeste',
            'play_status' => 'pending',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game = Game::where('title', 'Celeste')->firstOrFail();
        $this->assertNull($game->igdb_matched_at);
        $this->assertNull($game->igdb_background);
    }

    public function test_creating_a_game_with_auto_igdb_background_enabled_but_no_igdb_match_leaves_the_background_empty(): void
    {
        $user = User::factory()->create(['auto_igdb_background' => true]);

        $this->mock(IgdbLookupService::class, function ($mock) {
            $mock->shouldReceive('search')->once()->andReturn([]);
            $mock->shouldNotReceive('artworks');
        });

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Un juego sin match',
            'play_status' => 'pending',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game = Game::where('title', 'Un juego sin match')->firstOrFail();
        $this->assertNotNull($game->igdb_matched_at);
        $this->assertNull($game->igdb_id);
        $this->assertNull($game->igdb_background);
    }

    public function test_creating_a_game_with_auto_igdb_background_enabled_but_no_artworks_leaves_the_background_empty(): void
    {
        $user = User::factory()->create(['auto_igdb_background' => true]);

        $this->mock(IgdbLookupService::class, function ($mock) {
            $mock->shouldReceive('search')->once()->andReturn([new IgdbGameMatch(
                igdbId: 42,
                title: 'Sin arte',
                platforms: null,
                developer: null,
                releaseDate: null,
                genres: null,
                rating: null,
            )]);
            $mock->shouldReceive('artworks')->once()->with(42)->andReturn([]);
        });

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Sin arte',
            'play_status' => 'pending',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game = Game::where('title', 'Sin arte')->firstOrFail();
        $this->assertSame(42, $game->igdb_id);
        $this->assertNull($game->igdb_background);
    }

    public function test_creating_a_wishlist_game_saves_priority_estimated_price_and_store(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Hollow Knight: Silksong',
            'play_status' => 'pending',
            'status' => 'wishlist',
            'wishlist_priority' => 1,
            'wishlist_estimated_price' => 39.99,
            'wishlist_store' => 'Tienda X',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game = Game::where('title', 'Hollow Knight: Silksong')->firstOrFail();

        $this->assertSame('wishlist', $game->status);
        $this->assertSame(1, $game->wishlist_priority);
        $this->assertEquals(39.99, $game->wishlist_estimated_price);
        $this->assertSame('Tienda X', $game->wishlist_store);
    }

    public function test_creating_a_game_requires_title_and_play_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', []);

        $response->assertSessionHasErrors(['title', 'play_status']);
        $this->assertDatabaseCount('games', 0);
    }

    public function test_creating_a_game_with_a_duplicate_ean_warns_instead_of_saving(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Original', 'ean' => '1234567890123']);

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Copia',
            'ean' => '1234567890123',
            'play_status' => 'pending',
        ]);

        $response->assertSessionHasErrors('ean');
        $this->assertDatabaseMissing('games', ['title' => 'Copia']);
    }

    public function test_creating_a_game_with_a_duplicate_ean_saves_when_confirmed(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Original', 'ean' => '1234567890123']);

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Copia',
            'ean' => '1234567890123',
            'play_status' => 'pending',
            'confirm_duplicate' => '1',
        ]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['title' => 'Copia', 'ean' => '1234567890123']);
    }

    public function test_creating_a_game_with_a_blank_ean_never_triggers_the_duplicate_warning(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Sin EAN 1', 'ean' => null]);

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Sin EAN 2',
            'play_status' => 'pending',
        ]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['title' => 'Sin EAN 2']);
    }

    public function test_duplicate_ean_check_only_considers_the_authenticated_users_games(): void
    {
        $otherUser = User::factory()->create();
        Game::factory()->for($otherUser)->create(['ean' => '1234567890123']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Mi copia',
            'ean' => '1234567890123',
            'play_status' => 'pending',
        ]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['title' => 'Mi copia', 'user_id' => $user->id]);
    }

    public function test_user_can_view_their_own_games_detail_page(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Hollow Knight', 'notes' => 'Platinado']);

        $response = $this->actingAs($user)->get("/games/{$game->id}");

        $response->assertOk();
        $response->assertSee('Hollow Knight');
        $response->assertSee('Platinado');
    }

    public function test_user_cannot_view_another_users_games_detail_page(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        $response = $this->actingAs(User::factory()->create())->get("/games/{$game->id}");

        $response->assertForbidden();
    }

    public function test_show_dispatches_the_igdb_match_job_when_the_game_has_not_been_matched_yet(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['igdb_matched_at' => null]);

        $this->actingAs($user)->get("/games/{$game->id}")->assertOk();

        // GameController::show() ya no espera a IGDB en línea (ver
        // Jobs\MatchGameWithIgdb): la ficha se sirve de inmediato y el match
        // se ve la próxima vez que se abra, una vez el worker de cola lo
        // procese.
        Bus::assertDispatched(MatchGameWithIgdb::class, fn (MatchGameWithIgdb $job) => $job->gameId === $game->id);
    }

    public function test_show_does_not_dispatch_the_igdb_match_job_once_already_matched(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['igdb_matched_at' => now()]);

        $this->actingAs($user)->get("/games/{$game->id}")->assertOk();

        Bus::assertNotDispatched(MatchGameWithIgdb::class);
    }

    public function test_show_matches_with_igdb_using_the_games_owner_credentials(): void
    {
        // El entorno de test fuerza QUEUE_CONNECTION=sync (ver phpunit.xml),
        // así que el job se procesa en línea antes de que termine la
        // petición: sirve para probar el flujo completo (ver también
        // Tests\Feature\Jobs\MatchGameWithIgdbTest, que prueba el job
        // aislado). Sin mockear IgdbLookupService: el job la construye a
        // mano con IgdbLookupService::forUser(), no vía inyección de
        // dependencias (ver Jobs\MatchGameWithIgdb).
        $user = User::factory()->create([
            'igdb_enabled' => true,
            'igdb_client_id' => 'user-client-id',
            'igdb_client_secret' => 'user-client-secret',
        ]);
        $game = Game::factory()->for($user)->create(['title' => 'Celeste', 'developer' => null]);

        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'user-token', 'expires_in' => 5184000], 200),
            'api.igdb.com/v4/games' => Http::response([[
                'id' => 305,
                'name' => 'Celeste',
                'involved_companies' => [['developer' => true, 'company' => ['name' => 'Maddy Makes Games']]],
            ]], 200),
        ]);

        $this->actingAs($user)->get("/games/{$game->id}")->assertOk();

        $this->assertSame('Maddy Makes Games', $game->fresh()->developer);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.igdb.com/v4/games'
            && $request->hasHeader('Client-ID', 'user-client-id'));
    }

    public function test_show_does_not_contact_igdb_when_the_user_has_not_enabled_it(): void
    {
        // igdb_enabled=false es el valor por defecto de la factory (ver
        // UserFactory), igual que antes lo era no tener IGDB_CLIENT_ID/
        // SECRET en .env.
        $user = User::factory()->create(['igdb_enabled' => false]);
        $game = Game::factory()->for($user)->create();
        Http::fake();

        $this->actingAs($user)->get("/games/{$game->id}")->assertOk();

        Http::assertNothingSent();
    }

    public function test_quick_update_no_longer_accepts_rating(): void
    {
        // La conservación se editaba al vuelo tocando una estrella en el
        // listado (tarjetas/estantería), pero se quitó (ver CHANGELOG) para
        // no arriesgar cambios sin querer al hacer scroll — ahora solo se
        // cambia desde la ficha de edición completa, nunca desde aquí.
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['rating' => 2]);

        $this->actingAs($user)->patchJson("/games/{$game->id}/quick-update", ['rating' => 5])
            ->assertStatus(422);

        $this->assertSame(2, $game->fresh()->rating);
    }

    public function test_user_can_quick_update_the_play_status_of_their_own_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['play_status' => 'pending']);

        $response = $this->actingAs($user)->patchJson("/games/{$game->id}/quick-update", ['play_status' => 'finished']);

        $response->assertOk()->assertJson(['play_status' => 'finished']);
        $this->assertSame('finished', $game->fresh()->play_status);
    }

    public function test_user_can_quick_update_the_for_sale_flag_of_their_own_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['for_sale' => false]);

        $response = $this->actingAs($user)->patchJson("/games/{$game->id}/quick-update", ['for_sale' => true]);

        $response->assertOk()->assertJson(['for_sale' => true]);
        $this->assertTrue($game->fresh()->for_sale);
    }

    public function test_quick_update_toggles_for_sale_from_a_plain_form_and_redirects_back(): void
    {
        // El botón "Marcar en venta" de la ficha de detalle no usa JS/fetch: es
        // un <form> normal, así que quick-update debe redirigir en vez de
        // devolver JSON cuando la petición no pide explícitamente JSON.
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['for_sale' => false]);

        $response = $this->actingAs($user)->from("/games/{$game->id}")
            ->patch("/games/{$game->id}/quick-update", ['for_sale' => 1]);

        $response->assertRedirect("/games/{$game->id}");
        $this->assertTrue($game->fresh()->for_sale);
    }

    public function test_quick_update_requires_a_valid_play_status(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['play_status' => 'pending']);

        $this->actingAs($user)->patchJson("/games/{$game->id}/quick-update", ['play_status' => 'not-a-status'])
            ->assertStatus(422);

        $this->assertSame('pending', $game->fresh()->play_status);
    }

    public function test_user_cannot_quick_update_another_users_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create(['play_status' => 'pending']);

        $this->actingAs(User::factory()->create())->patchJson("/games/{$game->id}/quick-update", ['play_status' => 'finished'])
            ->assertForbidden();

        $this->assertSame('pending', $game->fresh()->play_status);
    }

    public function test_user_cannot_view_the_edit_form_of_another_users_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        $response = $this->actingAs(User::factory()->create())->get("/games/{$game->id}/edit");

        $response->assertForbidden();
    }

    public function test_user_can_update_their_own_game_and_replace_the_cover(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        $oldCover = UploadedFile::fake()->image('old.jpg')->store('covers', 'public');
        $game = Game::factory()->for($user)->create([
            'title' => 'Old title',
            'platform_id' => $platform->id,
            'cover' => $oldCover,
        ]);

        $newCover = UploadedFile::fake()->image('new.jpg');

        $response = $this->actingAs($user)->put("/games/{$game->id}", [
            'title' => 'New title',
            'platform_id' => $platform->id,
            'play_status' => 'finished',
            'cover' => $newCover,
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game->refresh();

        $this->assertSame('New title', $game->title);
        Storage::disk('public')->assertMissing($oldCover);
        Storage::disk('public')->assertExists($game->cover);
    }

    public function test_cover_lookup_searches_cex_by_the_games_ean_and_returns_its_platform(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [[
                    'boxName' => 'Hollow Knight',
                    'boxId' => '5060146467315',
                    'imageUrls' => ['large' => 'https://es.static.webuy.com/hk_l.jpg'],
                    'categoryFriendlyName' => 'Switch Juegos',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Hollow Knight', 'ean' => '5060146467315']);

        $response = $this->actingAs($user)->getJson("/games/{$game->id}/cover-lookup");

        $response->assertOk();
        $response->assertJson(['results' => [[
            'title' => 'Hollow Knight',
            'ean' => '5060146467315',
            'cover_url' => 'https://es.static.webuy.com/hk_l.jpg',
            'platform' => 'Switch',
        ]]]);
        Http::assertSent(fn ($request) => str_contains($request['params'], 'query=5060146467315'));
    }

    public function test_cover_lookup_falls_back_to_the_title_when_the_game_has_no_ean(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);

        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Celeste', 'ean' => null]);

        $this->actingAs($user)->getJson("/games/{$game->id}/cover-lookup")->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['params'], 'query=Celeste'));
    }

    public function test_cover_lookup_uses_the_manual_query_instead_of_the_games_ean_when_given(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);

        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Zelda mal escrito', 'ean' => '5060146467315']);

        $this->actingAs($user)->getJson("/games/{$game->id}/cover-lookup?q=Breath+of+the+Wild")->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['params'], 'query=Breath+of+the+Wild'));
    }

    public function test_cover_lookup_is_forbidden_for_another_users_game(): void
    {
        Http::fake();
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        $response = $this->actingAs(User::factory()->create())->getJson("/games/{$game->id}/cover-lookup");

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_cover_lookup_for_new_searches_cex_without_a_saved_game(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [[
                    'boxName' => 'Hollow Knight',
                    'boxId' => '5060146467315',
                    'imageUrls' => ['large' => 'https://es.static.webuy.com/hk_l.jpg'],
                    'categoryFriendlyName' => 'Switch Juegos',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/games/cover-lookup?q=Hollow+Knight');

        $response->assertOk();
        $response->assertJson(['results' => [[
            'title' => 'Hollow Knight',
            'ean' => '5060146467315',
            'cover_url' => 'https://es.static.webuy.com/hk_l.jpg',
            'platform' => 'Switch',
        ]]]);
        Http::assertSent(fn ($request) => str_contains($request['params'], 'query=Hollow+Knight'));
    }

    public function test_cover_lookup_for_new_without_a_query_returns_no_results_without_calling_cex(): void
    {
        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/games/cover-lookup')
            ->assertOk()
            ->assertJson(['results' => []]);

        Http::assertNothingSent();
    }

    public function test_cover_lookup_for_new_requires_authentication(): void
    {
        Http::fake();

        $this->getJson('/games/cover-lookup?q=Hollow+Knight')->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_updating_a_game_downloads_the_chosen_cex_cover_and_replaces_the_old_one(): void
    {
        Storage::fake('public');
        Http::fake(['es.static.webuy.com/*' => Http::response('fake-jpeg-bytes', 200, ['Content-Type' => 'image/jpeg'])]);

        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        $oldCover = UploadedFile::fake()->image('old.jpg')->store('covers', 'public');
        $game = Game::factory()->for($user)->create(['platform_id' => $platform->id, 'cover' => $oldCover]);

        $response = $this->actingAs($user)->put("/games/{$game->id}", [
            'title' => $game->title,
            'platform_id' => $platform->id,
            'play_status' => 'pending',
            'cover_url' => 'https://es.static.webuy.com/product_images/hk_l.jpg',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game->refresh();
        $this->assertStringEndsWith('.jpg', $game->cover);
        Storage::disk('public')->assertMissing($oldCover);
        Storage::disk('public')->assertExists($game->cover);
    }

    public function test_updating_a_game_keeps_the_existing_cover_when_the_cover_url_host_is_not_allowlisted(): void
    {
        Storage::fake('public');
        Http::fake();

        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        $oldCover = UploadedFile::fake()->image('old.jpg')->store('covers', 'public');
        $game = Game::factory()->for($user)->create(['platform_id' => $platform->id, 'cover' => $oldCover]);

        $response = $this->actingAs($user)->put("/games/{$game->id}", [
            'title' => $game->title,
            'platform_id' => $platform->id,
            'play_status' => 'pending',
            // SSRF: no está en la lista blanca de hosts, no debe ni intentarse.
            'cover_url' => 'https://169.254.169.254/latest/meta-data/',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $game->refresh();
        $this->assertSame($oldCover, $game->cover);
        Storage::disk('public')->assertExists($oldCover);
        Http::assertNothingSent();
    }

    public function test_updating_a_game_to_a_duplicate_ean_warns_instead_of_saving(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Otro juego', 'ean' => '1234567890123']);
        $game = Game::factory()->for($user)->create(['title' => 'Este juego', 'ean' => null, 'play_status' => 'pending']);

        $response = $this->actingAs($user)->put("/games/{$game->id}", [
            'title' => 'Este juego',
            'ean' => '1234567890123',
            'play_status' => 'pending',
        ]);

        $response->assertSessionHasErrors('ean');
        $this->assertSame(null, $game->fresh()->ean);
    }

    public function test_updating_a_game_does_not_flag_its_own_unchanged_ean_as_a_duplicate(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Mi juego', 'ean' => '1234567890123', 'play_status' => 'pending']);

        $response = $this->actingAs($user)->put("/games/{$game->id}", [
            'title' => 'Mi juego actualizado',
            'ean' => '1234567890123',
            'play_status' => 'pending',
        ]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertSame('Mi juego actualizado', $game->fresh()->title);
    }

    public function test_user_cannot_update_another_users_game(): void
    {
        $owner = User::factory()->create();
        $platform = Platform::factory()->create();
        $game = Game::factory()->for($owner)->create(['title' => 'Untouched', 'platform_id' => $platform->id]);

        $response = $this->actingAs(User::factory()->create())->put("/games/{$game->id}", [
            'title' => 'Hijacked',
            'platform_id' => $platform->id,
            'play_status' => 'finished',
        ]);

        $response->assertForbidden();
        $this->assertSame('Untouched', $game->fresh()->title);
    }

    public function test_user_can_delete_their_own_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/games/{$game->id}");

        $response->assertRedirect(route('web.games.index'));
        $this->assertSoftDeleted('games', ['id' => $game->id]);
    }

    public function test_deleting_a_game_flashes_an_undo_url_to_restore_it(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/games/{$game->id}");

        $response->assertSessionHas('undoUrl', route('web.games.restore', $game->id));
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

    public function test_trash_excludes_sold_games(): void
    {
        $user = User::factory()->create();
        $sold = Game::factory()->for($user)->create(['title' => 'Juego que se vendió', 'status' => 'owned']);
        $this->actingAs($user)->post("/games/{$sold->id}/mark-sold", ['sale_price' => 10, 'sold_at' => '2026-01-01']);

        $deleted = Game::factory()->for($user)->create(['title' => 'Borrado sin más']);
        $deleted->delete();

        // No usamos assertSee/assertDontSee con el título del juego: el toast
        // "«Juego que se vendió» marcado como vendido." de la petición
        // anterior sigue en el flash de la sesión de test durante esta
        // petición y aparecería igualmente en el HTML aunque la fila de la
        // tabla no esté.
        $response = $this->actingAs($user)->get('/games/trash');

        $titles = $response->viewData('games')->pluck('title');
        $this->assertNotContains('Juego que se vendió', $titles);
        $this->assertContains('Borrado sin más', $titles);
    }

    public function test_user_cannot_delete_another_users_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        $response = $this->actingAs(User::factory()->create())->delete("/games/{$game->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('games', ['id' => $game->id, 'deleted_at' => null]);
    }

    public function test_trash_only_lists_the_authenticated_users_deleted_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $mine = Game::factory()->for($user)->create(['title' => 'Mi juego borrado']);
        $mine->delete();

        $theirs = Game::factory()->for($otherUser)->create(['title' => 'Juego ajeno borrado']);
        $theirs->delete();

        $stillActive = Game::factory()->for($user)->create(['title' => 'Juego activo']);

        $response = $this->actingAs($user)->get('/games/trash');

        $response->assertOk();
        $response->assertSee('Mi juego borrado');
        $response->assertDontSee('Juego ajeno borrado');
        $response->assertDontSee('Juego activo');
    }

    public function test_trash_filters_by_title_or_ean(): void
    {
        $user = User::factory()->create();

        $hollow = Game::factory()->for($user)->create(['title' => 'Hollow Knight', 'ean' => '111']);
        $hollow->delete();

        $celeste = Game::factory()->for($user)->create(['title' => 'Celeste', 'ean' => '222']);
        $celeste->delete();

        $response = $this->actingAs($user)->get('/games/trash?q=hollow');

        $response->assertOk();
        $response->assertSee('Hollow Knight');
        $response->assertDontSee('Celeste');
    }

    public function test_trash_filters_by_platform(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        $onPlatform = Game::factory()->for($user)->create(['title' => 'En esa plataforma', 'platform_id' => $platform->id]);
        $onPlatform->delete();

        $elsewhere = Game::factory()->for($user)->create(['title' => 'En otra plataforma']);
        $elsewhere->delete();

        $response = $this->actingAs($user)->get("/games/trash?platform_id={$platform->id}");

        $response->assertOk();
        $response->assertSee('En esa plataforma');
        $response->assertDontSee('En otra plataforma');
    }

    public function test_user_can_restore_their_own_deleted_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();
        $game->delete();

        $response = $this->actingAs($user)->post("/games/{$game->id}/restore");

        $response->assertRedirect(route('web.games.trash'));
        $this->assertDatabaseHas('games', ['id' => $game->id, 'deleted_at' => null]);
    }

    public function test_user_cannot_restore_another_users_deleted_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();
        $game->delete();

        $response = $this->actingAs(User::factory()->create())->post("/games/{$game->id}/restore");

        $response->assertForbidden();
        $this->assertSoftDeleted('games', ['id' => $game->id]);
    }

    public function test_user_can_permanently_delete_their_own_trashed_game(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $cover = UploadedFile::fake()->image('cover.jpg')->store('covers', 'public');
        $game = Game::factory()->for($user)->create(['cover' => $cover]);
        $game->delete();

        $response = $this->actingAs($user)->delete("/games/{$game->id}/force-delete");

        $response->assertRedirect(route('web.games.trash'));
        $this->assertDatabaseMissing('games', ['id' => $game->id]);
        Storage::disk('public')->assertMissing($cover);
    }

    public function test_user_cannot_permanently_delete_another_users_trashed_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();
        $game->delete();

        $response = $this->actingAs(User::factory()->create())->delete("/games/{$game->id}/force-delete");

        $response->assertForbidden();
        $this->assertDatabaseHas('games', ['id' => $game->id]);
    }
}
