<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GameTrashControllerTest extends TestCase
{
    use RefreshDatabase;

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
