<?php

namespace Tests\Feature\Web;

use App\Models\Commission;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_every_commissions_route(): void
    {
        $commission = Commission::factory()->for(User::factory())->create();

        $this->get('/commissions')->assertRedirect('/login');
        $this->get('/commissions/create')->assertRedirect('/login');
        $this->get("/commissions/{$commission->id}/edit")->assertRedirect('/login');
    }

    public function test_index_only_lists_the_authenticated_users_commissions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Commission::factory()->for($user)->create(['title' => 'Mío']);
        Commission::factory()->for($otherUser)->create(['title' => 'Ajeno']);

        $response = $this->actingAs($user)->get('/commissions');

        $response->assertOk();
        $response->assertSee('Mío');
        $response->assertDontSee('Ajeno');
    }

    public function test_index_shows_an_empty_state_without_any_commissions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/commissions');

        $response->assertOk();
        $response->assertSee('No tienes ningún encargo');
    }

    public function test_index_filters_by_direction(): void
    {
        $user = User::factory()->create();
        Commission::factory()->for($user)->create(['title' => 'Me lo deben', 'direction' => Commission::DIRECTION_OWED_TO_ME]);
        Commission::factory()->for($user)->create(['title' => 'Yo lo debo', 'direction' => Commission::DIRECTION_OWED_BY_ME]);

        $response = $this->actingAs($user)->get('/commissions?direction=owed_by_me');

        $response->assertOk();
        $response->assertSee('Yo lo debo');
        $response->assertDontSee('Me lo deben');
    }

    public function test_user_can_create_a_commission(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        $response = $this->actingAs($user)->post('/commissions', [
            'title' => 'Celeste',
            'platform_id' => $platform->id,
            'counterparty_name' => 'Ana',
            'direction' => Commission::DIRECTION_OWED_TO_ME,
            'price' => '19.99',
            'purchased_at' => '2026-08-01',
        ]);

        $response->assertRedirect(route('web.commissions.index'));

        $commission = Commission::where('title', 'Celeste')->firstOrFail();
        $this->assertSame($user->id, $commission->user_id);
        $this->assertSame('Ana', $commission->counterparty_name);
        $this->assertSame(Commission::DIRECTION_OWED_TO_ME, $commission->direction);
        $this->assertNull($commission->resolved_at);
    }

    public function test_creating_a_commission_requires_a_title_counterparty_and_direction(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/commissions', []);

        $response->assertSessionHasErrors(['title', 'counterparty_name', 'direction']);
    }

    public function test_user_can_update_their_own_commission(): void
    {
        $user = User::factory()->create();
        $commission = Commission::factory()->for($user)->create(['title' => 'Antiguo', 'counterparty_name' => 'Ana']);

        $response = $this->actingAs($user)->put("/commissions/{$commission->id}", [
            'title' => 'Nuevo título',
            'counterparty_name' => 'Bea',
            'direction' => $commission->direction,
        ]);

        $response->assertRedirect(route('web.commissions.index'));
        $fresh = $commission->fresh();
        $this->assertSame('Nuevo título', $fresh->title);
        $this->assertSame('Bea', $fresh->counterparty_name);
    }

    public function test_user_cannot_update_another_users_commission(): void
    {
        $owner = User::factory()->create();
        $commission = Commission::factory()->for($owner)->create();

        $response = $this->actingAs(User::factory()->create())->put("/commissions/{$commission->id}", [
            'title' => 'Hackeado',
            'counterparty_name' => 'Bea',
            'direction' => $commission->direction,
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_delete_their_own_commission(): void
    {
        $user = User::factory()->create();
        $commission = Commission::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/commissions/{$commission->id}");

        $response->assertRedirect(route('web.commissions.index'));
        $this->assertModelMissing($commission);
    }

    public function test_user_cannot_delete_another_users_commission(): void
    {
        $owner = User::factory()->create();
        $commission = Commission::factory()->for($owner)->create();

        $response = $this->actingAs(User::factory()->create())->delete("/commissions/{$commission->id}");

        $response->assertForbidden();
        $this->assertModelExists($commission);
    }

    public function test_resolving_an_owed_to_me_commission_creates_a_game_and_keeps_the_commission(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        $commission = Commission::factory()->for($user)->create([
            'title' => 'Hollow Knight',
            'platform_id' => $platform->id,
            'direction' => Commission::DIRECTION_OWED_TO_ME,
            'price' => '15.00',
            'purchased_at' => '2026-07-01',
        ]);

        $response = $this->actingAs($user)->post("/commissions/{$commission->id}/resolve", [
            'resolved_at' => '2026-08-15',
        ]);

        $fresh = $commission->fresh();
        $this->assertNotNull($fresh->game_id);
        $this->assertSame('2026-08-15', $fresh->resolved_at->format('Y-m-d'));

        $game = Game::findOrFail($fresh->game_id);
        $this->assertSame($user->id, $game->user_id);
        $this->assertSame('Hollow Knight', $game->title);
        $this->assertSame($platform->id, $game->platform_id);
        $this->assertSame('15.00', (string) $game->price_paid);
        $this->assertSame('2026-07-01', $game->purchase_date->format('Y-m-d'));
        $this->assertSame('owned', $game->status);

        $response->assertRedirect(route('web.games.edit', $game->id));

        // El encargo se queda como histórico, no se borra ni desaparece del listado.
        $this->assertModelExists($commission);
        $this->actingAs($user)->get('/commissions')->assertSee('Hollow Knight');
    }

    public function test_resolving_an_owed_by_me_commission_only_records_the_date_without_creating_a_game(): void
    {
        $user = User::factory()->create();
        $commission = Commission::factory()->for($user)->create([
            'title' => 'Ori and the Blind Forest',
            'direction' => Commission::DIRECTION_OWED_BY_ME,
        ]);

        $response = $this->actingAs($user)->post("/commissions/{$commission->id}/resolve", [
            'resolved_at' => '2026-08-15',
        ]);

        $response->assertRedirect(route('web.commissions.index'));

        $fresh = $commission->fresh();
        $this->assertNull($fresh->game_id);
        $this->assertSame('2026-08-15', $fresh->resolved_at->format('Y-m-d'));
        $this->assertSame(0, Game::where('title', 'Ori and the Blind Forest')->count());
    }

    public function test_resolving_without_a_date_defaults_to_today(): void
    {
        $user = User::factory()->create();
        $commission = Commission::factory()->for($user)->create(['direction' => Commission::DIRECTION_OWED_BY_ME]);

        $this->actingAs($user)->post("/commissions/{$commission->id}/resolve", []);

        $this->assertSame(now()->format('Y-m-d'), $commission->fresh()->resolved_at->format('Y-m-d'));
    }

    public function test_user_cannot_resolve_another_users_commission(): void
    {
        $owner = User::factory()->create();
        $commission = Commission::factory()->for($owner)->create();

        $response = $this->actingAs(User::factory()->create())->post("/commissions/{$commission->id}/resolve", []);

        $response->assertForbidden();
        $this->assertNull($commission->fresh()->resolved_at);
    }
}
