<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\VerifyIngestKey;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'ingest.key' => VerifyIngestKey::class,
        ]);

        // API-only backend: never redirect guests to a (non-existent) login
        // route. Returning null makes Authenticate throw AuthenticationException,
        // which our handler renders as a 401 JSON envelope regardless of headers.
        $middleware->redirectGuestsTo(fn (Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Standardize all API error responses to the documented envelope.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                $errors = [];
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $errors[] = [
                            'code' => 'VALIDATION_ERROR',
                            'message' => $message,
                            'field' => $field,
                        ];
                    }
                }

                return ApiResponse::error($errors, 422);
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error([[
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                    'field' => null,
                ]], 401);
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $code = match ($status) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                429 => 'TOO_MANY_REQUESTS',
                default => 'SERVER_ERROR',
            };

            $message = $e->getMessage() ?: 'Unexpected error.';
            if ($status === 500 && ! config('app.debug')) {
                $message = 'Server error.';
            }

            return ApiResponse::error([[
                'code' => $code,
                'message' => $message,
                'field' => null,
            ]], $status);
        });
    })->create();
