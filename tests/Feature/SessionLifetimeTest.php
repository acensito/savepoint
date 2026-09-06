<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * (#140): sin "Recordarme" la sesión solo debe morir al cerrar el navegador,
 * nunca por inactividad a media sesión — ver el comentario de
 * SESSION_LIFETIME/SESSION_EXPIRE_ON_CLOSE en .env.example. Con "Recordarme"
 * marcado, la cookie "recuérdame" de Laravel (400 días por defecto) es la que
 * mantiene la sesión, independientemente de esos dos valores.
 */
class SessionLifetimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_session_cookie_has_no_expiration_date_so_it_dies_when_the_browser_closes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertSame(0, $cookie->getExpiresTime());
    }

    public function test_logging_in_with_remember_me_sets_a_long_lived_recaller_cookie(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->post(route('web.login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $recaller = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNotNull($recaller);
        $this->assertGreaterThan(now()->addDays(7)->getTimestamp(), $recaller->getExpiresTime());
    }

    public function test_logging_in_without_remember_me_sets_no_recaller_cookie(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->post(route('web.login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('web.games.index'));

        $recaller = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNull($recaller);
    }
}
