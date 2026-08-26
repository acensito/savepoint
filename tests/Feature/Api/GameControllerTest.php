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

        $this->getJson('/api/games')
            ->assertStatus(401)
            ->assertJson(['message' => 'No autenticado.']);
        $this->getJson("/api/games/{$game->id}")
            ->assertStatus(401)
            ->assertJson(['message' => 'No autenticado.']);
        $this->postJson('/api/games', [])
            ->assertStatus(401)
            ->assertJson(['message' => 'No autenticado.']);
        $this->putJson("/api/games/{$game->id}", ['title' => 'Hijacked'])
            ->assertStatus(401)
            ->assertJson(['message' => 'No autenticado.']);
        $this->deleteJson("/api/games/{$game->id}")
            ->assertStatus(401)
            ->assertJson(['message' => 'No autenticado.']);
    }

    public function test_requesting_a_nonexistent_game_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/games/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Recurso no encontrado.']);
        $this->putJson('/api/games/999999', ['title' => 'x'])
            ->assertStatus(404)
            ->assertJson(['message' => 'Recurso no encontrado.']);
        $this->deleteJson('/api/games/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Recurso no encontrado.']);
    }

    public function test_requesting_an_unknown_api_route_returns_404(): void
    {
        $this->getJson('/api/nonexistent-route')
            ->assertStatus(404)
            ->assertJson(['message' => 'Recurso no encontrado.']);
    }

    public function test_index_treats_a_non_numeric_or_negative_per_page_as_the_default(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->count(3)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/games?per_page=-5')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 20);

        $this->getJson('/api/games?per_page=not-a-number')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_index_search_term_with_sql_wildcard_and_quote_characters_does_not_error_or_leak_other_users_games(
    ): void {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Hollow Knight']);
        Game::factory()->create(['title' => "Someone Else's Game"]); // otro usuario

        Sanctum::actingAs($user);

        foreach (["'; DROP TABLE games; --", "%' OR '1'='1", '___', '%%'] as $payload) {
            $response = $this->getJson('/api/games?q='.urlencode($payload))->assertOk();

            // Solo debe poder ver, como mucho, sus propios juegos: nunca los de otro usuario.
            foreach ($response->json('data') as $game) {
                $this->assertNotSame("Someone Else's Game", $game['title']);
            }
        }

        // La tabla sigue intacta tras el intento de inyección.
        $this->assertDatabaseCount('games', 2);
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

        $this->getJson("/api/games/{$game->id}")
            ->assertStatus(403)
            ->assertJson(['message' => 'No autorizado para realizar esta acción.']);
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

    public function test_creating_a_game_ignores_a_spoofed_user_id_and_always_assigns_the_authenticated_user(): void
    {
        // Mass assignment / IDOR: si un atacante mete su propio "user_id" en
        // el payload apuntando a otra cuenta, StoreGameRequest no lo valida
        // (no está en las reglas) y GameController::store() lo pisa siempre
        // con el usuario autenticado — pero lo comprobamos como regresión.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $platform = Platform::factory()->create();

        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/games', [
            'title' => 'Hollow Knight',
            'platform_id' => $platform->id,
            'user_id' => $victim->id,
        ])->assertCreated();

        $this->assertDatabaseHas('games', [
            'title' => 'Hollow Knight',
            'user_id' => $attacker->id,
        ]);
        $this->assertDatabaseMissing('games', [
            'title' => 'Hollow Knight',
            'user_id' => $victim->id,
        ]);
    }

    public function test_creating_a_game_requires_title_and_platform(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/games', [])
            ->assertStatus(422)
            ->assertJson(['message' => 'Los datos proporcionados no son válidos.'])
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

    public function test_updating_a_game_ignores_an_attempt_to_reassign_it_to_another_user(): void
    {
        // Mismo caso que en store(): UpdateGameRequest tampoco valida
        // "user_id", así que update() nunca lo toca aunque venga en el payload.
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->putJson("/api/games/{$game->id}", [
            'title' => 'Still mine',
            'user_id' => $attacker->id,
        ])->assertOk();

        $this->assertDatabaseHas('games', ['id' => $game->id, 'user_id' => $owner->id]);
    }

    public function test_updating_a_game_cannot_set_status_to_sold_which_is_a_derived_state(): void
    {
        // Ver Game::STATUSES: 'sold' se marca solo desde
        // SalesController::markAsSold() (venta con precio/fecha), nunca
        // asignable directamente ni desde el formulario web ni desde la API.
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['status' => 'owned']);

        Sanctum::actingAs($user);

        $this->putJson("/api/games/{$game->id}", ['status' => 'sold'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'owned']);
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
            ->assertStatus(403)
            ->assertJson(['message' => 'No autorizado para realizar esta acción.']);

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

        $this->deleteJson("/api/games/{$game->id}")
            ->assertStatus(403)
            ->assertJson(['message' => 'No autorizado para realizar esta acción.']);

        $this->assertDatabaseHas('games', ['id' => $game->id, 'deleted_at' => null]);
    }
}
