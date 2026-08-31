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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [AddContentSecurityPolicyHeader::class]);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->wantsJson(),
        );

        $exceptions->render(function (ApiException $e, Request $request) {
            return $request->is('api/*') ? $e->render($request) : null;
        });

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

        $exceptions->render(function (Throwable $e, Request $request) {
            return $request->is('api/*')
                ? (new ApiException(ApiErrorCode::INTERNAL_ERROR, 500,
                    'Se ha producido un error interno.'))->render($request)
                : null;
        });
    })->create();
