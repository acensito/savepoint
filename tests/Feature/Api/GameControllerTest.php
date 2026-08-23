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

    public function test_index_paginates_the_results(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->count(25)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/games')->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertSame(25, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }

    public function test_index_accepts_a_custom_per_page(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->count(10)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/games?per_page=5')->assertOk();

        $this->assertCount(5, $response->json('data'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }

    public function test_index_caps_per_page_at_100(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->count(5)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/games?per_page=500')->assertOk();

        $this->assertSame(100, $response->json('meta.per_page'));
    }

    public function test_index_filters_by_title_or_ean(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Hollow Knight', 'ean' => '111']);
        Game::factory()->for($user)->create(['title' => 'Celeste', 'ean' => '222']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/games?q=hollow')->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Hollow Knight', $response->json('data.0.title'));

        $response = $this->getJson('/api/games?q=222')->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Celeste', $response->json('data.0.title'));
    }

    public function test_index_filters_by_platform_play_status_and_status(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        Game::factory()->for($user)->create([
            'platform_id' => $platform->id,
            'play_status' => 'finished',
            'status' => 'owned',
        ]);
        Game::factory()->for($user)->create([
            'platform_id' => Platform::factory()->create()->id,
            'play_status' => 'pending',
            'status' => 'wishlist',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/games?platform_id={$platform->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/games?play_status=finished')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/games?status=wishlist')
            ->assertOk()
            ->assertJsonCount(1, 'data');
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

    public function test_creating_a_game_rejects_a_rating_outside_the_webs_1_to_5_range(): void
    {
        // Regresión: la API aceptaba rating 1-10 mientras el formulario web
        // lo restringe a 1-5 (ver Game::RATING_MIN/MAX) — un alta por API
        // fuera de ese rango rendía raro en la web.
        Sanctum::actingAs(User::factory()->create());
        $platform = Platform::factory()->create();

        $this->postJson('/api/games', [
            'title' => 'Celeste',
            'platform_id' => $platform->id,
            'rating' => 8,
        ])->assertStatus(422)->assertJsonValidationErrors('rating');
    }

    public function test_creating_a_game_rejects_a_status_outside_the_webs_closed_enum(): void
    {
        // Regresión: la API aceptaba cualquier string para status (ver
        // Game::STATUSES) — incluido 'sold', que ni el propio formulario web
        // permite asignar directamente (solo vía SalesController::markAsSold()).
        Sanctum::actingAs(User::factory()->create());
        $platform = Platform::factory()->create();

        $this->postJson('/api/games', [
            'title' => 'Celeste',
            'platform_id' => $platform->id,
            'status' => 'sold',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_creating_a_game_rejects_a_play_status_outside_the_webs_closed_enum(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $platform = Platform::factory()->create();

        $this->postJson('/api/games', [
            'title' => 'Celeste',
            'platform_id' => $platform->id,
            'play_status' => 'not-a-status',
        ])->assertStatus(422)->assertJsonValidationErrors('play_status');
    }

    public function test_creating_a_game_accepts_status_play_status_and_rating_within_the_webs_ranges(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $platform = Platform::factory()->create();

        $this->postJson('/api/games', [
            'title' => 'Celeste',
            'platform_id' => $platform->id,
            'status' => 'wishlist',
            'play_status' => 'playing',
            'rating' => 5,
        ])->assertCreated();

        $this->assertDatabaseHas('games', [
            'title' => 'Celeste',
            'status' => 'wishlist',
            'play_status' => 'playing',
            'rating' => 5,
        ]);
    }

    public function test_updating_a_game_rejects_a_rating_outside_the_webs_1_to_5_range(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/games/{$game->id}", ['rating' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
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
