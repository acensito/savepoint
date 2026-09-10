<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameBulkActionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_every_bulk_action_route(): void
    {
        $this->post('/games/bulk-delete')->assertRedirect('/login');
        $this->post('/games/bulk-play-status')->assertRedirect('/login');
        $this->post('/games/bulk-for-sale')->assertRedirect('/login');
        $this->post('/games/bulk-unmark-for-sale')->assertRedirect('/login');
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

    public function test_user_can_bulk_mark_their_own_games_as_for_sale(): void
    {
        $user = User::factory()->create();
        $game1 = Game::factory()->for($user)->create(['for_sale' => false]);
        $game2 = Game::factory()->for($user)->create(['for_sale' => false]);

        $response = $this->actingAs($user)->post('/games/bulk-for-sale', [
            'game_ids' => [$game1->id, $game2->id],
        ]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['id' => $game1->id, 'for_sale' => true]);
        $this->assertDatabaseHas('games', ['id' => $game2->id, 'for_sale' => true]);
    }

    public function test_bulk_mark_for_sale_ignores_games_belonging_to_other_users(): void
    {
        $user = User::factory()->create();
        $otherUsersGame = Game::factory()->for(User::factory())->create(['for_sale' => false]);

        $this->actingAs($user)->post('/games/bulk-for-sale', [
            'game_ids' => [$otherUsersGame->id],
        ])->assertRedirect(route('web.games.index'));

        $this->assertDatabaseHas('games', ['id' => $otherUsersGame->id, 'for_sale' => false]);
    }

    public function test_bulk_mark_for_sale_requires_at_least_one_selected_game(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/games/bulk-for-sale', [])
            ->assertSessionHasErrors('game_ids');
    }

    public function test_user_can_bulk_unmark_their_own_games_as_for_sale(): void
    {
        $user = User::factory()->create();
        $game1 = Game::factory()->for($user)->create(['for_sale' => true]);
        $game2 = Game::factory()->for($user)->create(['for_sale' => true]);

        $response = $this->actingAs($user)->post('/games/bulk-unmark-for-sale', [
            'game_ids' => [$game1->id, $game2->id],
        ]);

        $response->assertRedirect(route('web.for-sale.index'));
        $this->assertDatabaseHas('games', ['id' => $game1->id, 'for_sale' => false]);
        $this->assertDatabaseHas('games', ['id' => $game2->id, 'for_sale' => false]);
    }

    public function test_bulk_unmark_for_sale_ignores_games_belonging_to_other_users(): void
    {
        $user = User::factory()->create();
        $otherUsersGame = Game::factory()->for(User::factory())->create(['for_sale' => true]);

        $this->actingAs($user)->post('/games/bulk-unmark-for-sale', [
            'game_ids' => [$otherUsersGame->id],
        ])->assertRedirect(route('web.for-sale.index'));

        $this->assertDatabaseHas('games', ['id' => $otherUsersGame->id, 'for_sale' => true]);
    }

    public function test_bulk_unmark_for_sale_requires_at_least_one_selected_game(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/games/bulk-unmark-for-sale', [])
            ->assertSessionHasErrors('game_ids');
    }
}
