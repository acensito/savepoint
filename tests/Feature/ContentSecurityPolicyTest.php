<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AddContentSecurityPolicyHeader sustituye a la cabecera CSP estática que
 * antes mandaba nginx (docker/nginx.conf): 'unsafe-inline'/'unsafe-eval' en
 * script-src se sustituyen por un nonce distinto en cada petición, posible
 * solo generándolo en Laravel (nginx no puede variar una cabecera por
 * petición con la configuración de este proyecto).
 */
class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_include_a_content_security_policy_with_a_nonce(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        // style-src sí conserva 'unsafe-inline' a propósito (Tailwind usa
        // atributos style="" en vivo, ver color pickers de plataformas/
        // fabricantes) — solo script-src pierde unsafe-inline/unsafe-eval.
        preg_match('/script-src ([^;]+)/', $csp, $matches);
        $scriptSrc = $matches[1] ?? '';

        $this->assertStringStartsWith("'self' 'nonce-", $scriptSrc);
        $this->assertStringNotContainsString('unsafe-inline', $scriptSrc);
        $this->assertStringNotContainsString('unsafe-eval', $scriptSrc);
    }

    public function test_the_nonce_is_different_on_every_request(): void
    {
        $first = $this->get('/login')->headers->get('Content-Security-Policy');
        $second = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertNotSame($first, $second);
    }

    public function test_the_header_nonce_matches_the_nonce_rendered_in_inline_scripts(): void
    {
        $response = $this->get('/login');

        $csp = $response->headers->get('Content-Security-Policy');
        preg_match("/nonce-([^']+)'/", $csp, $matches);
        $headerNonce = $matches[1] ?? null;

        $this->assertNotNull($headerNonce);
        $response->assertSee('nonce="'.$headerNonce.'"', false);
    }

    public function test_authenticated_pages_with_several_inline_scripts_all_use_the_headers_nonce(): void
    {
        // games/create combina el <script> del layout (sidebar/toasts) con el
        // de games/_form.blade.php: los dos deben compartir el mismo nonce,
        // no uno cada uno — View::share() los reparte a todas las vistas de
        // la misma petición.
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/games/create');

        $csp = $response->headers->get('Content-Security-Policy');
        preg_match("/nonce-([^']+)'/", $csp, $matches);
        $headerNonce = $matches[1] ?? null;

        $nonces = [];
        preg_match_all('/nonce="([^"]+)"/', $response->getContent(), $nonces);

        $this->assertNotEmpty($nonces[1]);
        $this->assertSame([$headerNonce], array_unique($nonces[1]));
    }
}
