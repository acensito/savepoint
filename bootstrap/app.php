<?php

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\Middleware\AddContentSecurityPolicyHeader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->wantsJson(),
        );

        // No hace falta registrar aquí un render() para ApiException: Laravel
        // ya invoca el render() propio de una excepción (Handler::render())
        // antes de llegar siquiera a los callbacks de más abajo, así que
        // ApiException::render() ya se ejecuta solo, sin necesidad de nada
        // más en este archivo.

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return $request->is('api/*')
                ? (new ApiException(ApiErrorCode::UNAUTHENTICATED, 401, 'No autenticado.'))->render($request)
                : null;
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) {
            return $request->is('api/*')
                ? (new ApiException(ApiErrorCode::FORBIDDEN, 403,
                    'No autorizado para realizar esta acción.'))->render($request)
                : null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return $request->is('api/*')
                ? (new ApiException(ApiErrorCode::NOT_FOUND, 404, 'Recurso no encontrado.'))->render($request)
                : null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e->status === 429) {
                $message = collect($e->errors())->flatten()->first()
                    ?? 'Demasiados intentos de acceso. Inténtalo de nuevo más tarde.';

                return (new ApiException(ApiErrorCode::RATE_LIMIT_EXCEEDED, 429, $message))->render($request);
            }

            return (new ApiException(
                ApiErrorCode::VALIDATION_ERROR,
                $e->status,
                'Los datos proporcionados no son válidos.',
                $e->errors(),
            ))->render($request);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return $request->is('api/*')
                ? (new ApiException(
                    ApiErrorCode::RATE_LIMIT_EXCEEDED,
                    429,
                    'Demasiados intentos de acceso. Inténtalo de nuevo más tarde.',
                    headers: $e->getHeaders(),
                ))->render($request)
                : null;
        });

        // Cualquier otra HttpExceptionInterface (405 método no permitido, 415
        // tipo de contenido no soportado, 400, 409...) no cubierta por un
        // código más específico de arriba: conserva su status HTTP real en
        // vez de caer en el catch-all de Throwable de más abajo, que
        // siempre informa 500 y ocultaría el código correcto al consumidor.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            return $request->is('api/*')
                ? (new ApiException(ApiErrorCode::HTTP_ERROR, $e->getStatusCode(),
                    'Se ha producido un error al procesar la petición.'))->render($request)
                : null;
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            return $request->is('api/*')
                ? (new ApiException(ApiErrorCode::INTERNAL_ERROR, 500,
                    'Se ha producido un error interno.'))->render($request)
                : null;
        });
    })->create();
