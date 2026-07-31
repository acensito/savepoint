<?php

namespace Tests\Feature\Api;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_games_endpoints(): void
    {
        $game = Game::factory()->create();

        $this->getJson('/api/games')->assertStatus(401);
        $this->getJson("/api/games/{$game->id}")->assertStatus(401);
        $this->postJson('/api/games', [])->assertStatus(401);
    }

    public function test_index_only_returns_the_authenticated_users_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Game::factory()->for($user)->count(2)->create();
        Game::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/games')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_view_their_own_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/games/{$game->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $game->id);
    }

    public function test_user_cannot_view_another_users_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/games/{$game->id}")->assertStatus(403);
    }

    public function test_user_can_create_a_game(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/games', [
            'title' => 'Hollow Knight',
            'platform_id' => $platform->id,
        ])->assertCreated();

        $response->assertJsonPath('data.title', 'Hollow Knight');

        $this->assertDatabaseHas('games', [
            'title' => 'Hollow Knight',
            'user_id' => $user->id,
        ]);
    }

    public function test_creating_a_game_requires_title_and_platform(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/games', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'platform_id']);
    }

    public function test_user_can_update_their_own_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Old title']);

        Sanctum::actingAs($user);

        $this->putJson("/api/games/{$game->id}", ['title' => 'New title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New title');

        $this->assertDatabaseHas('games', ['id' => $game->id, 'title' => 'New title']);
    }

    public function test_user_cannot_update_another_users_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create(['title' => 'Untouched']);

        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/games/{$game->id}", ['title' => 'Hijacked'])
            ->assertStatus(403);

        $this->assertDatabaseHas('games', ['id' => $game->id, 'title' => 'Untouched']);
    }

    public function test_user_can_delete_their_own_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/games/{$game->id}")->assertOk();

        $this->assertSoftDeleted('games', ['id' => $game->id]);
    }

    public function test_user_cannot_delete_another_users_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/games/{$game->id}")->assertStatus(403);

        $this->assertDatabaseHas('games', ['id' => $game->id, 'deleted_at' => null]);
    }
}
