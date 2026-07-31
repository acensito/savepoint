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
}
