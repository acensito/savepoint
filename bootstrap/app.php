<?php

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\Middleware\AddContentSecurityPolicyHeader;
use App\Http\Middleware\EnsureSectionIsEnabled;
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

        // 'section:wishlist' etc. (ver routes/web.php, issue #32): 404 en
        // vez de la página si el usuario desactivó esa sección desde
        // /panel/settings.
        $middleware->alias(['section' => EnsureSectionIsEnabled::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Respuestas de error localizadas en español para la API (/api/*),
        // manteniendo los códigos de estado HTTP y la estructura JSON habitual.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->wantsJson(),
        );

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

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $code = match ($status) {
                405 => ApiErrorCode::METHOD_NOT_ALLOWED,
                default => $status >= 500 ? ApiErrorCode::INTERNAL_ERROR : ApiErrorCode::HTTP_ERROR,
            };
            $message = $status >= 500
                ? 'Se ha producido un error interno.'
                : ($status === 405 ? 'Método no permitido.' : 'Se ha producido un error en la petición.');

            return (new ApiException($code, $status, $message, headers: $e->getHeaders()))->render($request);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            return $request->is('api/*')
                ? (new ApiException(ApiErrorCode::INTERNAL_ERROR, 500,
                    'Se ha producido un error interno.'))->render($request)
                : null;
        });
    })->create();
