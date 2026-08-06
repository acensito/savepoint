<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_a_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'access_token', 'token_type']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_their_own_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_logout_revokes_the_token_that_was_used(): void
    {
        // Creamos el token directamente (en vez de pasar por /api/login) para no
        // arrastrar la sesión 'web' que deja Auth::attempt(): con ella activa,
        // Sanctum autentica por sesión y currentAccessToken() devuelve un
        // TransientToken (sin fila real ni método delete()) en lugar del token.
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // El guard 'sanctum' cachea el usuario resuelto en la petición anterior
        // dentro del mismo contenedor de test; sin esto, la siguiente llamada
        // reutilizaría esa autenticación en vez de volver a mirar el token.
        $this->app['auth']->forgetGuards();

        // El token ya revocado no debe servir para nada más.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_login_is_throttled_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // El sexto intento queda bloqueado aunque la contraseña sea correcta.
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(429);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
