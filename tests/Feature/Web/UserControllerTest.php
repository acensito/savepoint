<?php

namespace Tests\Feature\Web;

use App\Models\AppSetting;
use App\Models\Game;
use App\Models\User;
use App\Services\Users\AbandonedAccountPruner;
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
        $this->patch('/panel/registration', ['registration_enabled' => '0'])->assertRedirect('/login');
        $this->post('/panel/users/prune-abandoned')->assertRedirect('/login');
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
        $this->actingAs($user)->patch('/panel/registration', ['registration_enabled' => '0'])->assertForbidden();
        $this->actingAs($user)->post('/panel/users/prune-abandoned')->assertForbidden();
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

    /**
     * (#10): antes alfabético por nombre, sin ninguna forma de distinguir de
     * un vistazo una cuenta recién llegada.
     */
    public function test_users_list_is_ordered_admins_first_then_by_most_recent_signup(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Yo, admin']);
        $oldRegular = User::factory()->create(['name' => 'Zulema', 'created_at' => now()->subDays(10)]);
        $newRegular = User::factory()->create(['name' => 'Ana', 'created_at' => now()->subDay()]);
        $oldAdmin = User::factory()->admin()->create(['name' => 'Otro admin', 'created_at' => now()->subDays(20)]);

        $response = $this->actingAs($admin)->get('/panel/users');

        $ids = $response->viewData('users')->pluck('id')->all();

        $this->assertSame([
            $admin->id, $oldAdmin->id, $newRegular->id, $oldRegular->id,
        ], $ids);
    }

    public function test_abandoned_two_factor_account_shows_a_pending_badge(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->twoFactorEnabled()->create(['name' => 'Cuenta huérfana']);

        $response = $this->actingAs($admin)->get('/panel/users');

        $response->assertSee('2FA pendiente');
    }

    public function test_verified_two_factor_account_does_not_show_a_pending_badge(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->twoFactorEnabled()->create([
            'name' => 'Cuenta normal',
            'two_factor_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/panel/users');

        $response->assertDontSee('2FA pendiente');
    }

    /**
     * Regresión (#10): activar 2FA desde Ajustes (PanelControllerTest cubre
     * el detalle) no debe dejar a una cuenta real y activa con el mismo
     * aspecto que una huérfana.
     */
    public function test_account_that_enabled_two_factor_from_settings_does_not_show_a_pending_badge(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create([
            'name' => 'Activó 2FA hoy',
            'two_factor_enabled' => true,
            'two_factor_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/panel/users');

        $response->assertDontSee('2FA pendiente');
    }

    public function test_admin_can_manually_purge_abandoned_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $abandoned = User::factory()->twoFactorEnabled()->create([
            'created_at' => now()->subDays(AbandonedAccountPruner::GRACE_PERIOD_DAYS + 1),
        ]);
        $tooRecent = User::factory()->twoFactorEnabled()->create([
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->post('/panel/users/prune-abandoned');

        $response->assertRedirect(route('web.panel.users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $abandoned->id]);
        $this->assertDatabaseHas('users', ['id' => $tooRecent->id]);
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/panel/users', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_admin' => '1',
        ]);

        $response->assertRedirect(route('web.panel.users.index'));

        $created = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertSame('Nuevo Usuario', $created->name);
        $this->assertTrue($created->is_admin);
        $this->assertTrue(Hash::check('Password123!', $created->password));
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

    /**
     * Regresión (#51): antes solo el registro público (RegisterController)
     * exigía complejidad de contraseña — un admin dando de alta un usuario a
     * mano se conformaba con min:8, así que "password1" (sin mayúscula ni
     * símbolo) colaba aquí aunque no lo hiciera en /register.
     */
    public function test_creating_a_user_requires_a_complex_password(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/panel/users', [
            'name' => 'Alguien',
            'email' => 'alguien@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'alguien@example.com']);
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
            'password' => 'Nueva-Password1',
            'password_confirmation' => 'Nueva-Password1',
        ]);
        $this->assertTrue(Hash::check('Nueva-Password1', $other->fresh()->password));
    }

    /**
     * Regresión (#51): ver test_creating_a_user_requires_a_complex_password.
     */
    public function test_admin_changing_another_users_password_requires_it_to_be_complex(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $originalHash = $other->password;

        $response = $this->actingAs($admin)->put("/panel/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame($originalHash, $other->fresh()->password);
    }

    public function test_admin_changing_another_users_password_revokes_their_existing_api_tokens(): void
    {
        // Regresión (#34): ver ProfileControllerTest::test_changing_the_password_revokes_existing_api_tokens.
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $other->createToken('MobileApp');

        $this->actingAs($admin)->put("/panel/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'password' => 'Nueva-Password1',
            'password_confirmation' => 'Nueva-Password1',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_leaving_the_password_blank_does_not_revoke_the_users_existing_api_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $other->createToken('MobileApp');

        $this->actingAs($admin)->put("/panel/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
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

    public function test_registration_is_open_by_default(): void
    {
        $this->assertTrue(AppSetting::current()->registration_enabled);
    }

    public function test_admin_can_close_public_registration(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patchJson('/panel/registration', [
            'field' => 'registration_enabled',
            'value' => false,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertFalse(AppSetting::current()->fresh()->registration_enabled);
    }

    public function test_admin_can_reopen_public_registration(): void
    {
        $admin = User::factory()->admin()->create();
        AppSetting::current()->update(['registration_enabled' => false]);

        $response = $this->actingAs($admin)->patchJson('/panel/registration', [
            'field' => 'registration_enabled',
            'value' => true,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertTrue(AppSetting::current()->fresh()->registration_enabled);
    }

    public function test_updating_registration_rejects_a_field_outside_the_whitelist(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patchJson('/panel/registration', [
            'field' => 'is_admin',
            'value' => true,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('field');
    }
}
