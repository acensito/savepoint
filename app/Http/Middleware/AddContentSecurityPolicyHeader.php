<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSP con nonce por petición para los `<script>` inline del layout y de
 * varias vistas (games/_form.blade.php, games/show.blade.php...) — antes se
 * permitían con 'unsafe-inline'/'unsafe-eval' en script-src (ver
 * docker/nginx.conf, que ya no manda esta cabecera: nginx no puede generar
 * un valor distinto en cada petición, así que pasa a construirse aquí).
 *
 * 'unsafe-eval' se quita del todo, no solo se cambia por wasm-unsafe-eval:
 *
 * @zxing/library (único código de terceros no trivial de la app, para
 * escanear códigos de barras) no usa eval()/new Function() ni WASM en
 * ningún punto — ni en su build ESM ni en el bundle final de Vite
 * (comprobado a mano: cero coincidencias en public/build/assets/*.js).
 */
class AddContentSecurityPolicyHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));

        // Compartido con todas las vistas de la petición (layout incluido)
        // para que puedan marcar sus <script> inline con nonce="{{ $cspNonce }}".
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
        ]));

        return $response;
    }
}
