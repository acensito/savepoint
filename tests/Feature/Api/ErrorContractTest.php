<?php

namespace Tests\Feature\Api;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\Game;
use App\Models\User;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\TestCase;

class ErrorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_errors_use_the_common_contract(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
                'status' => 401,
                'message' => 'No autenticado.',
            ]);

        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/games/{$game->id}")
            ->assertForbidden()
            ->assertJson([
                'code' => 'FORBIDDEN',
                'status' => 403,
                'message' => 'No autorizado para realizar esta acción.',
            ]);

        $this->getJson('/api/games/999999')
            ->assertNotFound()
            ->assertJson([
                'code' => 'NOT_FOUND',
                'status' => 404,
                'message' => 'Recurso no encontrado.',
            ]);
    }

    public function test_validation_errors_have_code_status_message_and_errors(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/games', [])
            ->assertUnprocessable()
            ->assertJson([
                'code' => 'VALIDATION_ERROR',
                'status' => 422,
                'message' => 'Los datos proporcionados no son válidos.',
            ])
            ->assertJsonStructure(['errors' => ['title', 'platform_id']]);
    }

    public function test_api_exception_omits_null_and_empty_errors_and_uses_the_reporting_pipeline(): void
    {
        $this->assertArrayNotHasKey('errors', (new ApiException(
            ApiErrorCode::INVALID_CREDENTIALS, 401, 'Invalid',
        ))->payload());
        $this->assertArrayNotHasKey('errors', (new ApiException(
            ApiErrorCode::VALIDATION_ERROR, 422, 'Invalid', [],
        ))->payload());

        Log::shouldReceive('error')
            ->once()
            ->withArgs(static fn (string $message, array $context): bool => str_contains($message, 'Server failure'));
        Route::get('/api/error-contract/client-error', static function () {
            throw new ApiException(ApiErrorCode::INVALID_CREDENTIALS, 401, 'Invalid');
        });
        Route::get('/api/error-contract/server-error', static function () {
            throw new ApiException(ApiErrorCode::INTERNAL_ERROR, 500, 'Server failure');
        });

        $this->getJson('/api/error-contract/client-error')->assertUnauthorized();

        $this->getJson('/api/error-contract/server-error')->assertInternalServerError();
    }

    public function test_http_exceptions_keep_status_headers_and_public_codes(): void
    {
        $this->getJson('/api/login')
            ->assertStatus(405)
            ->assertJson([
                'code' => 'METHOD_NOT_ALLOWED',
                'status' => 405,
                'message' => 'Método no permitido.',
            ])
            ->assertHeader('Allow');

        Route::get('/api/error-contract/bad-request', static function () {
            throw new BadRequestHttpException('internal details');
        });

        $this->getJson('/api/error-contract/bad-request')
            ->assertStatus(400)
            ->assertJson([
                'code' => 'HTTP_ERROR',
                'status' => 400,
                'message' => 'Se ha producido un error en la petición.',
            ])
            ->assertJsonMissing(['internal details']);
    }

    public function test_api_middleware_throttle_preserves_retry_after_header(): void
    {
        Route::middleware('throttle:1,1')
            ->get('/api/error-contract/throttle', static fn () => response()->json(['ok' => true]))
            ->name('error-contract.api-throttle');

        $this->getJson('/api/error-contract/throttle')->assertOk();
        $response = $this->getJson('/api/error-contract/throttle');

        $response->assertTooManyRequests()
            ->assertJson([
                'code' => 'RATE_LIMIT_EXCEEDED',
                'status' => 429,
            ])
            ->assertJsonMissingPath('errors')
            ->assertHeader('Retry-After');
    }

    public function test_unexpected_api_throwable_is_sanitized_even_in_debug_mode(): void
    {
        $this->app['config']->set('app.debug', true);
        Route::get('/api/error-contract/unexpected', static function () {
            throw new RuntimeException('database password: do not expose');
        })->name('error-contract.api-unexpected');

        $this->getJson('/api/error-contract/unexpected')
            ->assertStatus(500)
            ->assertJson([
                'code' => 'INTERNAL_ERROR',
                'status' => 500,
                'message' => 'Se ha producido un error interno.',
            ])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line');
    }

    public function test_two_factor_email_failure_returns_service_unavailable(): void
    {
        $this->mock(Dispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->andThrow(new RuntimeException('SMTP down'));
        });

        $user = User::factory()->twoFactorEnabled()->create([
            'password' => Hash::make('password'),
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(503)->assertJson([
            'code' => 'SERVICE_UNAVAILABLE',
            'status' => 503,
        ]);
    }

    public function test_api_login_and_two_factor_failures_have_specific_error_codes(): void
    {
        $this->postJson('/api/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJson([
            'code' => 'INVALID_CREDENTIALS',
            'status' => 401,
        ]);

        Notification::fake();
        $user = User::factory()->twoFactorEnabled()->create([
            'password' => Hash::make('password'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
        $token = $login->json('two_factor_token');

        $this->postJson('/api/login/verify-2fa', [
            'two_factor_token' => 'expired-token',
            'code' => '123456',
        ])->assertUnauthorized()->assertJson([
            'code' => 'TWO_FACTOR_CHALLENGE_EXPIRED',
            'status' => 401,
        ]);

        $this->postJson('/api/login/verify-2fa', [
            'two_factor_token' => $token,
            'code' => '000000',
        ])->assertUnauthorized()->assertJson([
            'code' => 'INVALID_TWO_FACTOR_CODE',
            'status' => 401,
        ]);
    }

    public function test_web_exception_does_not_receive_the_api_contract(): void
    {
        $this->app['config']->set('app.debug', false);
        Route::get('/error-contract/web-exception', static function () {
            throw new ApiException(ApiErrorCode::INVALID_CREDENTIALS, 401, 'Invalid');
        })->name('error-contract.web-exception');

        $response = $this->get('/error-contract/web-exception');

        $response->assertStatus(500);
        $this->assertStringNotContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('INVALID_CREDENTIALS', $response->getContent());
    }

    public function test_login_throttle_is_not_reported_as_validation_errors(): void
    {
        // Con el reloj congelado, el número de segundos que reporta el
        // throttle es siempre el decay completo (60): sin esto, el tiempo
        // real que tarda en ejecutarse el bucle de abajo podía hacer que
        // "availableIn" devolviera 59 en vez de 60 según lo rápido o lento
        // que fuera el entorno (un test flaky visto en CI, no en local).
        $this->freezeTime();

        User::factory()->create(['password' => Hash::make('password')]);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $response = $this->postJson('/api/login', [
                'email' => 'throttled@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response->assertTooManyRequests()
            ->assertJson([
                'code' => 'RATE_LIMIT_EXCEEDED',
                'status' => 429,
                'message' => 'Demasiados intentos de acceso. Inténtalo de nuevo en 60 segundos.',
            ])
            ->assertJsonMissingPath('errors');
    }
}
