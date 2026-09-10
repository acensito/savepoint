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

    /**
     * Issue #186: Instrument Sans y Material Symbols se autoalojan desde
     * public/build en vez de fonts.googleapis.com/fonts.gstatic.com, así que
     * el CSP ya no necesita permitirlos.
     */
    public function test_web_responses_do_not_allow_google_fonts_anymore(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('fonts.googleapis.com', $csp);
        $this->assertStringNotContainsString('fonts.gstatic.com', $csp);
        $this->assertStringContainsString("font-src 'self'", $csp);
    }

    public function test_web_responses_block_plugins_and_a_base_href_takeover(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
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
