<?php

namespace Tests\Feature\Auth;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_form(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_login_shows_the_register_link_when_registration_is_open(): void
    {
        $response = $this->get('/login');

        $response->assertSee(route('register'));
        $response->assertSee('Regístrate');
    }

    public function test_login_hides_the_register_link_when_an_admin_has_closed_registration(): void
    {
        AppSetting::current()->update(['registration_enabled' => false]);

        $response = $this->get('/login');

        $response->assertDontSee(route('register'));
        $response->assertDontSee('Regístrate');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_redirects_to_originally_intended_page(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->get('/stats'); // guarda la URL "intended" antes de loguear

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('web.stats.index'));
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        // El middleware 'guest' no conoce rutas con nombre 'dashboard'/'home',
        // así que cae al fallback genérico '/', que en esta app es el listado.
        $response->assertRedirect('/');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_from_protected_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_login_is_throttled_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // El sexto intento queda bloqueado aunque la contraseña sea correcta.
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_by_email_alone_even_when_rotating_ip(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        // El límite por email+IP (5 intentos) se resetea en la práctica al
        // cambiar de IP en cada intento, así que hacen falta más de 10 para
        // comprobar que el límite por email solo (más laxo) sigue frenando.
        for ($i = 0; $i < 10; $i++) {
            $this->call('POST', '/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ], [], [], ['REMOTE_ADDR' => "10.0.0.$i"]);
        }

        $response = $this->call('POST', '/login', [
            'email' => $user->email,
            'password' => 'password',
        ], [], [], ['REMOTE_ADDR' => '10.0.0.99']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
