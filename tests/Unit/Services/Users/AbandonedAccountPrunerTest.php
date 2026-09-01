<?php

namespace Tests\Unit\Services\Users;

use App\Models\Game;
use App\Models\User;
use App\Services\Users\AbandonedAccountPruner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobertura directa del servicio, sin pasar por HTTP: el único punto de
 * entrada real es el botón manual (ver UserControllerTest::
 * test_admin_can_manually_purge_abandoned_accounts), pero los casos límite
 * de la propia query (verificada, sin 2FA, con juegos...) se prueban mejor
 * aquí, más rápido y sin acoplarse a la ruta/autorización del controlador.
 */
class AbandonedAccountPrunerTest extends TestCase
{
    use RefreshDatabase;

    private function abandonedAccount(array $overrides = []): User
    {
        return User::factory()->twoFactorEnabled()->create(array_merge([
            'created_at' => now()->subDays(AbandonedAccountPruner::GRACE_PERIOD_DAYS + 1),
        ], $overrides));
    }

    public function test_prune_deletes_an_abandoned_account_and_reports_how_many(): void
    {
        $abandoned = $this->abandonedAccount();

        $count = (new AbandonedAccountPruner)->prune();

        $this->assertSame(1, $count);
        $this->assertDatabaseMissing('users', ['id' => $abandoned->id]);
    }

    public function test_prune_reports_zero_when_there_is_nothing_to_purge(): void
    {
        $count = (new AbandonedAccountPruner)->prune();

        $this->assertSame(0, $count);
    }

    public function test_prune_leaves_accounts_still_inside_the_grace_period(): void
    {
        $recent = $this->abandonedAccount(['created_at' => now()->subDay()]);

        (new AbandonedAccountPruner)->prune();

        $this->assertDatabaseHas('users', ['id' => $recent->id]);
    }

    public function test_prune_leaves_accounts_that_already_verified_two_factor_once(): void
    {
        $verified = $this->abandonedAccount(['two_factor_verified_at' => now()->subDays(AbandonedAccountPruner::GRACE_PERIOD_DAYS)]);

        (new AbandonedAccountPruner)->prune();

        $this->assertDatabaseHas('users', ['id' => $verified->id]);
    }

    public function test_prune_leaves_accounts_without_two_factor_enabled(): void
    {
        $noTwoFactor = User::factory()->create(['created_at' => now()->subDays(30)]);

        (new AbandonedAccountPruner)->prune();

        $this->assertDatabaseHas('users', ['id' => $noTwoFactor->id]);
    }

    /**
     * Defensa adicional (doesntHave('games') en la query): en la app real es
     * imposible llegar a tener juegos sin haber verificado nunca el 2FA
     * (crear un juego exige sesión autenticada, y la única forma de
     * autenticarse es completar el desafío al menos una vez), pero se prueba
     * igual como red de seguridad — el coste de un falso negativo aquí (una
     * cuenta con colección real borrada por error) es demasiado alto para
     * confiar solo en que ese estado "no debería" darse.
     */
    public function test_prune_never_deletes_an_account_that_has_games_even_if_flagged_as_unverified(): void
    {
        $account = $this->abandonedAccount();
        Game::factory()->for($account)->create();

        (new AbandonedAccountPruner)->prune();

        $this->assertDatabaseHas('users', ['id' => $account->id]);
    }

    public function test_pending_count_reflects_the_query_without_deleting_anything(): void
    {
        $this->abandonedAccount();
        $this->abandonedAccount();
        $this->abandonedAccount(['created_at' => now()->subDay()]); // dentro del plazo de gracia

        $count = (new AbandonedAccountPruner)->pendingCount();

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('users', 3);
    }
}
