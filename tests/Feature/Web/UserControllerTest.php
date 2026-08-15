<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_every_users_route(): void
    {
        $other = User::factory()->create();

        $this->get('/panel/users')->assertRedirect('/login');
        $this->get('/panel/users/create')->assertRedirect('/login');
        $this->post('/panel/users')->assertRedirect('/login');
        $this->get("/panel/users/{$other->id}/edit")->assertRedirect('/login');
        $this->put("/panel/users/{$other->id}")->assertRedirect('/login');
        $this->delete("/panel/users/{$other->id}")->assertRedirect('/login');
    }

    public function test_a_non_admin_is_forbidden_from_every_users_route(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create();

        $this->actingAs($user)->get('/panel/users')->assertForbidden();
        $this->actingAs($user)->get('/panel/users/create')->assertForbidden();
        $this->actingAs($user)->post('/panel/users', [])->assertForbidden();
        $this->actingAs($user)->get("/panel/users/{$other->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/panel/users/{$other->id}", [])->assertForbidden();
        $this->actingAs($user)->delete("/panel/users/{$other->id}")->assertForbidden();
    }

    public function test_admin_can_list_all_users_with_their_game_count(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Yo, admin']);
        $other = User::factory()->create(['name' => 'Otro usuario']);
        Game::factory()->for($other)->count(3)->create();

        $response = $this->actingAs($admin)->get('/panel/users');

        $response->assertOk();
        $response->assertSee('Yo, admin');
        $response->assertSee('Otro usuario');

        $users = $response->viewData('users')->keyBy('id');
        $this->assertSame(3, $users[$other->id]->games_count);
        $this->assertSame(0, $users[$admin->id]->games_count);
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/panel/users', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_admin' => '1',
        ]);

        $response->assertRedirect(route('web.panel.users.index'));

        $created = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertSame('Nuevo Usuario', $created->name);
        $this->assertTrue($created->is_admin);
        $this->assertTrue(Hash::check('password123', $created->password));
    }

    public function test_creating_a_user_requires_a_unique_email_and_a_confirmed_password(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'existe@example.com']);

        $response = $this->actingAs($admin)->post('/panel/users', [
            'name' => 'Alguien',
            'email' => 'existe@example.com',
            'password' => 'short',
            'password_confirmation' => 'no-coincide',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_admin_can_edit_another_users_name_email_and_role(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create(['name' => 'Antes', 'is_admin' => false]);

        $response = $this->actingAs($admin)->put("/panel/users/{$other->id}", [
            'name' => 'Después',
            'email' => $other->email,
            'is_admin' => '1',
        ]);

        $response->assertRedirect(route('web.panel.users.index'));

        $other->refresh();
        $this->assertSame('Después', $other->name);
        $this->assertTrue($other->is_admin);
    }

    public function test_admin_can_change_another_users_password_or_leave_it_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $originalHash = $other->password;

        // En blanco: no se toca.
        $this->actingAs($admin)->put("/panel/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
        ]);
        $this->assertSame($originalHash, $other->fresh()->password);

        // Con valor: se actualiza.
        $this->actingAs($admin)->put("/panel/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'password' => 'nueva-password',
            'password_confirmation' => 'nueva-password',
        ]);
        $this->assertTrue(Hash::check('nueva-password', $other->fresh()->password));
    }

    public function test_an_admin_cannot_demote_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put("/panel/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_admin' => '0',
        ]);

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_can_delete_a_user_without_games(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($admin)->delete("/panel/users/{$other->id}");

        $response->assertRedirect(route('web.panel.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    public function test_admin_cannot_delete_a_user_who_still_has_games(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        Game::factory()->for($other)->create();

        $response = $this->actingAs($admin)->delete("/panel/users/{$other->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->delete("/panel/users/{$admin->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
