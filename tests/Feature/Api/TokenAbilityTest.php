<?php

namespace Tests\Feature\Api;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use App\Services\Users\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Auditoría de seguridad del 2026-09-10, "ability-scoping de tokens
 * Sanctum": el único token que existe hoy (AuthController::
 * issueTokenResponse(), "MobileApp") recibe todas las abilities, así que
 * estos escenarios de restricción no ocurren todavía en producción — pero
 * comprueban que el middleware 'ability:' de cada ruta (ver routes/api.php)
 * de verdad hace cumplir lo que declara, no solo que exista.
 */
class TokenAbilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_without_games_read_cannot_list_games(): void
    {
        Sanctum::actingAs(User::factory()->create(), [TokenAbility::GAMES_WRITE->value]);

        $this->getJson('/api/games')->assertForbidden();
    }

    public function test_a_token_without_games_read_cannot_view_a_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();
        Sanctum::actingAs($user, [TokenAbility::GAMES_WRITE->value]);

        $this->getJson("/api/games/{$game->id}")->assertForbidden();
    }

    public function test_a_token_without_games_write_cannot_create_a_game(): void
    {
        $platform = Platform::factory()->create();
        Sanctum::actingAs(User::factory()->create(), [TokenAbility::GAMES_READ->value]);

        $this->postJson('/api/games', ['title' => 'Celeste', 'platform_id' => $platform->id])->assertForbidden();
    }

    public function test_a_token_without_games_write_cannot_update_a_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();
        Sanctum::actingAs($user, [TokenAbility::GAMES_READ->value]);

        $this->putJson("/api/games/{$game->id}", ['title' => 'Hijacked'])->assertForbidden();
    }

    public function test_a_token_without_games_write_cannot_delete_a_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();
        Sanctum::actingAs($user, [TokenAbility::GAMES_READ->value]);

        $this->deleteJson("/api/games/{$game->id}")->assertForbidden();
    }

    public function test_a_token_without_profile_read_cannot_fetch_the_user(): void
    {
        Sanctum::actingAs(User::factory()->create(), [TokenAbility::GAMES_READ->value, TokenAbility::GAMES_WRITE->value]);

        $this->getJson('/api/user')->assertForbidden();
    }

    public function test_logout_does_not_require_any_specific_ability(): void
    {
        Sanctum::actingAs(User::factory()->create(), []);

        $this->postJson('/api/logout')->assertOk();
    }

    public function test_a_token_with_every_ability_can_use_every_endpoint(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        $game = Game::factory()->for($user)->create();
        Sanctum::actingAs($user, TokenAbility::all());

        $this->getJson('/api/user')->assertOk();
        $this->getJson('/api/games')->assertOk();
        $this->getJson("/api/games/{$game->id}")->assertOk();
        $this->postJson('/api/games', ['title' => 'Celeste', 'platform_id' => $platform->id])->assertCreated();
        $this->putJson("/api/games/{$game->id}", ['title' => 'Renamed'])->assertOk();
        $this->deleteJson("/api/games/{$game->id}")->assertOk();
    }
}
