<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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
        $this->getJson('/api/user')
            ->assertStatus(401)
            ->assertJson(['message' => 'No autenticado.']);
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

    public function test_a_token_older_than_the_configured_expiration_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $user->tokens()->update([
            'created_at' => now()->subMinutes(config('sanctum.expiration') + 1),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_a_token_within_the_configured_expiration_still_works(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $user->tokens()->update([
            'created_at' => now()->subMinutes(config('sanctum.expiration') - 1),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertOk();
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

    public function test_login_error_message_does_not_reveal_whether_the_email_exists(): void
    {
        // El mensaje de error debe ser idéntico exista o no la cuenta, para no
        // permitir enumerar usuarios registrados probando emails al azar.
        $existing = User::factory()->create(['password' => Hash::make('password')]);

        $withExistingEmail = $this->postJson('/api/login', [
            'email' => $existing->email,
            'password' => 'wrong-password',
        ]);

        $withUnknownEmail = $this->postJson('/api/login', [
            'email' => 'no-existe-esta-cuenta@example.com',
            'password' => 'wrong-password',
        ]);

        $withExistingEmail->assertStatus(401);
        $withUnknownEmail->assertStatus(401);
        $this->assertSame(
            $withExistingEmail->json('message'),
            $withUnknownEmail->json('message'),
        );
    }

    public function test_login_rejects_a_sql_injection_style_email_without_erroring(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => "' OR '1'='1",
            'password' => 'password',
        ]);

        // Debe fallar la validación del formato de email (422), nunca colarse
        // como consulta o devolver un 500.
        $response->assertStatus(422)
            ->assertJson(['message' => 'Los datos proporcionados no son válidos.'])
            ->assertJsonValidationErrors('email');
    }

    public function test_malformed_bearer_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/user')
            ->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer')
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_a_revoked_or_deleted_users_token_no_longer_authenticates(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $user->delete();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_authenticated_user_response_never_exposes_hidden_fields(): void
    {
        $user = User::factory()->create([
            'igdb_client_secret' => 'super-secret-value',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user')->assertOk();

        $response->assertJsonMissing(['password'])
            ->assertJsonMissingPath('remember_token')
            ->assertJsonMissingPath('two_factor_code')
            ->assertJsonMissingPath('igdb_client_secret');
    }

    public function test_logging_in_with_two_factor_enabled_does_not_issue_a_token_yet(): void
    {
        // Regresión de seguridad: Api\AuthController::login() emitía el token
        // Sanctum con solo comprobar email+password (Auth::attempt()), sin
        // mirar two_factor_enabled — a diferencia del login web, que exige el
        // código antes de autenticar. Con solo la contraseña filtrada, un
        // atacante se saltaba el segundo factor entero entrando por la API.
        Notification::fake();

        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonStructure(['two_factor_token'])
            ->assertJsonMissingPath('access_token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }

    public function test_login_without_two_factor_enabled_is_unaffected_by_the_challenge_flow(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['access_token']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        Notification::assertNothingSent();
    }

    public function test_correct_two_factor_code_completes_the_login_and_issues_a_token(): void
    {
        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $challenge = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        // two_factor_code se guarda hasheado (ver User::generateTwoFactorCode):
        // lo regeneramos para quedarnos con el valor en claro que hay que enviar.
        $plainCode = $user->generateTwoFactorCode();

        $response = $this->postJson('/api/login/verify-2fa', [
            'two_factor_token' => $challenge->json('two_factor_token'),
            'code' => $plainCode,
        ]);

        $response->assertOk()->assertJsonStructure(['message', 'access_token', 'token_type']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_incorrect_two_factor_code_is_rejected_without_issuing_a_token(): void
    {
        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $challenge = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $response = $this->postJson('/api/login/verify-2fa', [
            'two_factor_token' => $challenge->json('two_factor_token'),
            'code' => '000000',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verify_2fa_rejects_an_unknown_or_expired_challenge_token(): void
    {
        $response = $this->postJson('/api/login/verify-2fa', [
            'two_factor_token' => 'not-a-real-challenge-token',
            'code' => '123456',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verify_2fa_ignores_a_user_id_supplied_in_the_request_body(): void
    {
        // Mismo caso que en el flujo web (TwoFactorTest::test_verify_ignores_a_user_id_supplied_in_the_request_body):
        // a quién se autentica lo dice el two_factor_token emitido por
        // /login, nunca un campo del body.
        $pendingUser = User::factory()->twoFactorEnabled()->create();
        $otherUser = User::factory()->twoFactorEnabled()->create();
        $otherCode = $otherUser->generateTwoFactorCode();

        $challenge = $this->postJson('/api/login', [
            'email' => $pendingUser->email,
            'password' => 'password',
        ])->assertOk();

        $response = $this->postJson('/api/login/verify-2fa', [
            'two_factor_token' => $challenge->json('two_factor_token'),
            'code' => $otherCode,
            'user_id' => $otherUser->id,
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_resend_2fa_issues_a_new_code_that_invalidates_the_previous_one(): void
    {
        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $challenge = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $firstCode = $user->generateTwoFactorCode();

        $this->postJson('/api/login/resend-2fa', [
            'two_factor_token' => $challenge->json('two_factor_token'),
        ])->assertOk();

        $this->postJson('/api/login/verify-2fa', [
            'two_factor_token' => $challenge->json('two_factor_token'),
            'code' => $firstCode,
        ])->assertStatus(401);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verify_2fa_is_rate_limited(): void
    {
        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $challenge = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login/verify-2fa', [
                'two_factor_token' => $challenge->json('two_factor_token'),
                'code' => '000000',
            ]);
        }

        $response = $this->postJson('/api/login/verify-2fa', [
            'two_factor_token' => $challenge->json('two_factor_token'),
            'code' => '000000',
        ]);

        $response->assertStatus(429);
    }

    public function test_resend_2fa_is_rate_limited(): void
    {
        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $challenge = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/login/resend-2fa', [
                'two_factor_token' => $challenge->json('two_factor_token'),
            ]);
        }

        $response = $this->postJson('/api/login/resend-2fa', [
            'two_factor_token' => $challenge->json('two_factor_token'),
        ]);

        $response->assertStatus(429);
    }

    public function test_verify_2fa_rate_limit_is_shared_across_tokens_from_repeated_logins_for_the_same_user(): void
    {
        // Regresión: RateLimiter::for('api-two-factor-verify') usaba el
        // propio "two_factor_token" como clave, y /api/login emite uno nuevo
        // (con un código nuevo) en cada llamada mientras la cuenta siga con
        // la contraseña correcta — así que pedir login() otra vez restauraba
        // 5 intentos frescos en vez de compartir el mismo límite de 5 cada
        // 10 minutos por cuenta que sí respeta el flujo web (session-based).
        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $login = fn () => $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $firstChallenge = $login();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login/verify-2fa', [
                'two_factor_token' => $firstChallenge->json('two_factor_token'),
                'code' => '000000',
            ]);
        }

        // Un segundo login (misma cuenta, contraseña correcta) emite un
        // two_factor_token distinto: no debe darle 5 intentos nuevos.
        $secondChallenge = $login();

        $this->assertNotSame(
            $firstChallenge->json('two_factor_token'),
            $secondChallenge->json('two_factor_token'),
        );

        $response = $this->postJson('/api/login/verify-2fa', [
            'two_factor_token' => $secondChallenge->json('two_factor_token'),
            'code' => '000000',
        ]);

        $response->assertStatus(429);
    }
}
