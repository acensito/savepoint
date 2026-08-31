<?php

namespace Tests\Feature\Api;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\Game;
use App\Models\User;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
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

    public function test_api_exception_omits_null_and_empty_errors_and_reports_only_server_errors(): void
    {
        $withoutErrors = new ApiException(ApiErrorCode::INVALID_CREDENTIALS, 401, 'Invalid');
        $withEmptyErrors = new ApiException(ApiErrorCode::VALIDATION_ERROR, 422, 'Invalid', []);
        $serverError = new ApiException(ApiErrorCode::SERVICE_UNAVAILABLE, 503, 'Unavailable');

        $this->assertArrayNotHasKey('errors', $withoutErrors->payload());
        $this->assertArrayNotHasKey('errors', $withEmptyErrors->payload());

        // Handler::reportThrowable() solo suprime el log por defecto cuando
        // report() devuelve algo distinto de `false` (comprueba !== false):
        // null/true lo suprimen, `false` deja pasar al logger. Por eso un
        // error de cliente controlado (4xx) devuelve null (sin ruido en el
        // log) y uno de servidor (5xx) devuelve false (sigue reportándose).
        $this->assertNull($withoutErrors->report());
        $this->assertFalse($serverError->report());
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

    /**
     * Regresión: cualquier HttpExceptionInterface sin un código más
     * específico (aquí, MethodNotAllowedHttpException al pedir /login con un
     * verbo que la ruta no admite) caía en el catch-all de Throwable y
     * siempre informaba 500, ocultando el 405 real al consumidor.
     */
    public function test_a_disallowed_http_method_keeps_its_real_status_code(): void
    {
        $this->getJson('/api/login')
            ->assertStatus(405)
            ->assertJson(['code' => 'HTTP_ERROR', 'status' => 405]);
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
            ])
            ->assertJsonMissingPath('errors');
    }
}
