<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameExportControllerTest extends TestCase
{
    use RefreshDatabase;

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
}
