<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SESSION_SECURE_COOKIE se deja sin definir a propósito (ver .env.example):
 * con el valor por defecto (null), Symfony marca la cookie de sesión como
 * "Secure" automáticamente cuando la petición ya llega como HTTPS
 * (Response::prepare(), ver vendor/symfony/http-foundation/Response.php).
 * bootstrap/app.php confía en las cabeceras X-Forwarded-* de cualquier
 * origen (trustProxies(at: '*')), así que esto ya funciona sin configurar
 * nada más en cuanto el proxy inverso (Cloudflare Tunnel, Caddy...) delante
 * de nginx añade X-Forwarded-Proto: https. Forzar SESSION_SECURE_COOKIE=true
 * a mano rompería el acceso por HTTP plano en localhost/LAN.
 */
class SessionCookieSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_cookie_is_not_secure_over_plain_http(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertFalse($cookie->isSecure());
    }

    public function test_session_cookie_is_secure_when_the_trusted_proxy_forwards_https(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
    }
}
