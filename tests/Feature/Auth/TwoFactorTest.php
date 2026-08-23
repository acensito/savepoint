<?php

namespace Tests\Feature\Auth;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, array $overrides = []): TestResponse
    {
        return $this->post('/login', array_merge([
            'email' => $user->email,
            'password' => 'password',
        ], $overrides));
    }

    /**
     * Simula el estado "a medias" que AuthController::login()/
     * RegisterController::register() dejan en sesión al redirigir al
     * desafío, sin depender de que la sesión de un login real sobreviva
     * entre dos peticiones de test separadas.
     */
    private function withPendingChallenge(User $user, bool $remember = false): static
    {
        return $this->withSession([
            'two_factor.user_id' => $user->id,
            'two_factor.remember' => $remember,
        ]);
    }

    public function test_login_without_two_factor_enabled_is_unaffected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->login($user);

        $response->assertRedirect(route('web.games.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_two_factor_enabled_redirects_to_challenge_instead_of_authenticating(): void
    {
        Notification::fake();

        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $response = $this->login($user);

        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }

    /**
     * Regresión de un 500 real en producción: un fallo de envío (SMTP
     * caído, credenciales mal puestas...) no debe tumbar el login con un
     * error sin manejar ni mandar a una pantalla de código que nunca va a
     * llegar — se avisa y se vuelve a /login, sin dejar sesión pendiente.
     */
    public function test_login_shows_a_generic_error_when_the_two_factor_email_fails_to_send(): void
    {
        $this->mock(Dispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->andThrow(new RuntimeException('SMTP down'));
        });

        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $response = $this->login($user);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();

        // Sin two_factor.user_id en sesión: /login/verify no debe dar acceso
        // a un desafío para el que nunca se mandó ningún código.
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_challenge_redirects_to_login_without_a_pending_session(): void
    {
        $response = $this->get(route('two-factor.challenge'));

        $response->assertRedirect(route('login'));
    }

    public function test_challenge_shows_the_masked_email_of_the_pending_user(): void
    {
        $user = User::factory()->twoFactorEnabled()->create(['email' => 'jugador@example.com']);

        $response = $this->withPendingChallenge($user)->get(route('two-factor.challenge'));

        $response->assertOk();
        $response->assertSee('j******@example.com');
    }

    public function test_correct_code_completes_login(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $code = $user->generateTwoFactorCode();

        $response = $this->withPendingChallenge($user)->post(route('two-factor.verify'), ['code' => $code]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->two_factor_code);
    }

    public function test_incorrect_code_fails_without_authenticating(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $user->generateTwoFactorCode();

        $response = $this->withPendingChallenge($user)->post(route('two-factor.verify'), ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $code = $user->generateTwoFactorCode();
        // forceFill(): two_factor_code_expires_at no es fillable a propósito
        // (ver User::generateTwoFactorCode), así que un update() normal no
        // lo tocaría.
        $user->forceFill(['two_factor_code_expires_at' => now()->subMinute()])->save();

        $response = $this->withPendingChallenge($user)->post(route('two-factor.verify'), ['code' => $code]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_verify_is_rate_limited(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $user->generateTwoFactorCode();

        for ($i = 0; $i < 5; $i++) {
            $this->withPendingChallenge($user)->post(route('two-factor.verify'), ['code' => '000000']);
        }

        $response = $this->withPendingChallenge($user)->post(route('two-factor.verify'), ['code' => '000000']);

        $response->assertStatus(429);
    }

    public function test_resend_issues_a_new_code_that_invalidates_the_previous_one(): void
    {
        Notification::fake();

        $user = User::factory()->twoFactorEnabled()->create();
        $firstCode = $user->generateTwoFactorCode();

        $this->withPendingChallenge($user)->post(route('two-factor.resend'));

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);

        $response = $this->withPendingChallenge($user)->post(route('two-factor.verify'), ['code' => $firstCode]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_resend_is_rate_limited(): void
    {
        Notification::fake();

        $user = User::factory()->twoFactorEnabled()->create();
        $user->generateTwoFactorCode();

        for ($i = 0; $i < 3; $i++) {
            $this->withPendingChallenge($user)->post(route('two-factor.resend'));
        }

        $response = $this->withPendingChallenge($user)->post(route('two-factor.resend'));

        $response->assertStatus(429);
    }

    public function test_resend_shows_a_generic_error_when_the_email_fails_to_send(): void
    {
        $this->mock(Dispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->andThrow(new RuntimeException('SMTP down'));
        });

        $user = User::factory()->twoFactorEnabled()->create();

        $response = $this->withPendingChallenge($user)->post(route('two-factor.resend'));

        $response->assertSessionHas('error');
    }

    public function test_trusting_the_device_creates_a_trusted_device_and_a_cookie(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $code = $user->generateTwoFactorCode();

        $response = $this->withPendingChallenge($user)
            ->post(route('two-factor.verify'), ['code' => $code, 'trust_device' => '1']);

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseCount('two_factor_trusted_devices', 1);
        $this->assertSame($user->id, $user->twoFactorTrustedDevices()->first()->user_id);
        $response->assertCookie(TwoFactorTrustedDevice::COOKIE_NAME);
    }

    public function test_verifying_without_trust_device_does_not_create_one(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $code = $user->generateTwoFactorCode();

        $this->withPendingChallenge($user)->post(route('two-factor.verify'), ['code' => $code]);

        $this->assertDatabaseCount('two_factor_trusted_devices', 0);
    }

    public function test_a_trusted_device_cookie_skips_the_challenge_on_a_later_login(): void
    {
        Notification::fake();

        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $token = TwoFactorTrustedDevice::issueFor($user, '127.0.0.1', 'PHPUnit');

        // withCookie() (no withUnencryptedCookie()) para que el propio
        // cliente de test cifre el valor igual que Cookie::queue() lo haría
        // en producción: la cookie real nunca viaja en claro, y
        // EncryptCookies la descifra al llegar la petición.
        $response = $this->withCookie(TwoFactorTrustedDevice::COOKIE_NAME, $token)
            ->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('web.games.index'));
        $this->assertAuthenticatedAs($user);

        Notification::assertNothingSent();
    }

    public function test_an_unknown_device_cookie_still_triggers_the_challenge(): void
    {
        Notification::fake();

        $user = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $response = $this->withCookie(TwoFactorTrustedDevice::COOKIE_NAME, 'not-a-real-token')
            ->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('two-factor.challenge'));

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }

    /**
     * Seguridad: un dispositivo de confianza de una cuenta no debe servir
     * para saltarse el desafío de otra — la cookie es la misma para toda la
     * app (un solo nombre, ver TwoFactorTrustedDevice::COOKIE_NAME), así que
     * la comprobación tiene que ir siempre scoped al usuario que intenta
     * entrar, nunca solo a "existe algún dispositivo con este token".
     */
    public function test_a_trusted_device_cookie_from_another_account_does_not_skip_the_challenge(): void
    {
        Notification::fake();

        $owner = User::factory()->twoFactorEnabled()->create();
        $victim = User::factory()->twoFactorEnabled()->create(['password' => Hash::make('password')]);

        $tokenForOwner = TwoFactorTrustedDevice::issueFor($owner, '127.0.0.1', 'PHPUnit');

        $response = $this->withCookie(TwoFactorTrustedDevice::COOKIE_NAME, $tokenForOwner)
            ->post('/login', ['email' => $victim->email, 'password' => 'password']);

        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        Notification::assertSentTo($victim, TwoFactorCodeNotification::class);
    }

    /**
     * Seguridad: quién se autentica en verify()/resend() sale siempre de
     * `two_factor.user_id` en sesión, nunca de un campo del propio POST —
     * intentar colar un `user_id` en el cuerpo de la petición para actuar
     * sobre el desafío pendiente de otra cuenta no debe tener ningún efecto.
     */
    public function test_verify_ignores_a_user_id_supplied_in_the_request_body(): void
    {
        $pendingUser = User::factory()->twoFactorEnabled()->create();
        $pendingCode = $pendingUser->generateTwoFactorCode();

        $otherUser = User::factory()->twoFactorEnabled()->create();
        $otherCode = $otherUser->generateTwoFactorCode();

        // Se manda el código de OTRO usuario junto con su user_id, mientras
        // la sesión sigue "a medias" para $pendingUser.
        $response = $this->withPendingChallenge($pendingUser)
            ->post(route('two-factor.verify'), ['code' => $otherCode, 'user_id' => $otherUser->id]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertNotNull($pendingUser->fresh()->two_factor_code);

        // El código correcto del usuario realmente pendiente en sesión sí
        // funciona, confirmando que el user_id del body no cambió a quién
        // se está verificando.
        $response = $this->withPendingChallenge($pendingUser)
            ->post(route('two-factor.verify'), ['code' => $pendingCode, 'user_id' => $otherUser->id]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertAuthenticatedAs($pendingUser);
    }
}
