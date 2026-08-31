<?php

use App\Http\Middleware\AddContentSecurityPolicyHeader;
use App\Http\Middleware\EnsureSectionIsEnabled;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // La app siempre sirve HTTP plano (nginx no gestiona TLS, ver README
        // "Exponer la app fuera de localhost") y el proxy inverso (Cloudflare
        // Tunnel, Tailscale Funnel, Caddy...) es el único punto de entrada, así
        // que confiar en cualquier origen para las cabeceras X-Forwarded-* es
        // seguro aquí: sin esto, route()/url() generan enlaces con http://
        // aunque el navegador esté en https://, lo que la CSP bloquea por
        // desajuste de origen (ver CHANGELOG).
        $middleware->trustProxies(at: '*');

        // Solo en 'web': una respuesta JSON de la API nunca se renderiza
        // como HTML, así que un nonce de CSP ahí no protege nada.
        $middleware->web(append: [AddContentSecurityPolicyHeader::class]);

        // Sin esto, 'api' no traía ningún límite de peticiones por defecto
        // (Laravel solo lo activa si se pide explícitamente) — /login ya
        // tenía su propio throttle contra fuerza bruta (ThrottlesLogins),
        // pero /games quedaba sin ningún tope: un token robado podía
        // machacar la API sin límite. 120/min por usuario autenticado (o por
        // IP para /login antes de tener token) es generoso de sobra para una
        // app de colección.
        $middleware->throttleApi();

        // 'section:wishlist' etc. (ver routes/web.php, issue #32): 404 en
        // vez de la página si el usuario desactivó esa sección desde
        // /panel/settings.
        $middleware->alias(['section' => EnsureSectionIsEnabled::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->wantsJson(),
        );

        // Respuestas de error localizadas en español para la API (/api/*),
        // manteniendo los códigos de estado HTTP y la estructura JSON habitual.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'No autenticado.',
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'No autorizado para realizar esta acción.',
                ], 403);
            }

            return null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Recurso no encontrado.',
                ], 404);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Los datos proporcionados no son válidos.',
                    'errors' => $e->errors(),
                ], $e->status);
            }

            return null;
        });
    })->create();
