<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_index_ignores_an_unknown_sort_column(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create();

        $this->actingAs($user)->get('/?sort=user_id')->assertOk();
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

    public function test_creating_a_game_requires_title_and_play_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', []);

        $response->assertSessionHasErrors(['title', 'play_status']);
        $this->assertDatabaseCount('games', 0);
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
