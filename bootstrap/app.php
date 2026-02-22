<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ==========================================
        // ✅ ORDRE CRITIQUE DES MIDDLEWARES
        // ==========================================

        // 1. TRUST PROXIES (EN PREMIER)
        $middleware->prepend(\App\Http\Middleware\TrustProxies::class);

        // 2. CORS - UNE SEULE FOIS en prepend API
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // 3. SANCTUM STATEFUL API
        $middleware->statefulApi();

        // 4. RATE LIMITING
        $middleware->throttleApi();

        // ==========================================
        // MIDDLEWARE ALIASES
        // ==========================================
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        // ==========================================
        // GROUPES DE MIDDLEWARES PERSONNALISÉS
        // ==========================================
        $middleware->group('admin', [
            'auth:sanctum',
            'admin',
        ]);

        $middleware->group('banking', [
            'auth:sanctum',
            'throttle:banking',
        ]);

        // ==========================================
        // MIDDLEWARES WEB
        // ==========================================
        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // ==========================================
        // EXCLUSIONS CSRF
        // ==========================================
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhooks/*',
            'bank/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // 401
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'error_code' => 'AUTH_REQUIRED',
                ], 401);
            }
            return redirect()->guest(route('login'));
        });

        // 403
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() === 403 && ($request->is('api/*') || $request->expectsJson())) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Access forbidden',
                    'error_code' => 'FORBIDDEN',
                ], 403);
            }
        });

        // 404
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                    'error_code' => 'NOT_FOUND',
                    'path' => $request->path(),
                ], 404);
            }
        });

        // 422
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'error_code' => 'VALIDATION_ERROR',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 429
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
                ], 429);
            }
        });

        // 500
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                return null;
            }

            if (app()->environment('production')) {
                \Log::error('Server Error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'url' => $request->fullUrl(),
                    'user_id' => auth()->id(),
                ]);
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => app()->environment('production')
                        ? 'An unexpected error occurred'
                        : $e->getMessage(),
                    'error_code' => 'SERVER_ERROR',
                    'debug' => app()->environment('local') ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ] : null,
                ], 500);
            }
        });

        // Bridge errors
        $exceptions->render(function (\Exception $e, Request $request) {
            if ($request->is('api/bank/*') && str_contains($e->getMessage(), 'Bridge')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Banking service temporarily unavailable',
                    'error_code' => 'BANKING_SERVICE_ERROR',
                    'retry' => true,
                ], 503);
            }
        });

        $exceptions->reportable(function (\Throwable $e) {
            if ($e instanceof \Illuminate\Auth\AuthenticationException) return false;
            if ($e instanceof \Illuminate\Validation\ValidationException) return false;
            return app()->environment('production');
        });
    })
    ->create();
