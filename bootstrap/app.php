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
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,  // ✅ DOIT être en premier !
        ]);

        // ==========================================
        // ✅ ORDRE CRITIQUE DES MIDDLEWARES
        // ==========================================
        // L'ordre d'exécution est CRUCIAL pour le bon fonctionnement :
        //
        // 1. TrustProxies     → Détecte l'IP réelle (Expose/Nginx/Cloudflare)
        // 2. HandleCors       → Gère les headers CORS (AVANT Sanctum!)
        // 3. Sanctum/Auth     → Authentification (après CORS)
        // 4. RateLimiting     → Limitation de requêtes
        // ==========================================

        // ✅ 1. TRUST PROXIES (EN PREMIER - Expose Pro / Nginx)
        $middleware->prepend(\App\Http\Middleware\TrustProxies::class);

        // ✅ 2. CORS (AVANT statefulApi)
        // CRITIQUE: Doit être avant Sanctum pour gérer les preflight OPTIONS

        // ✅ 3. SANCTUM STATEFUL API
        // Permet l'authentification via tokens et cookies
        $middleware->statefulApi();

        // ✅ 4. RATE LIMITING
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

        // Groupe admin (auth + admin check)
        $middleware->group('admin', [
            'auth:sanctum',
            'admin',
        ]);

        // Groupe banking (peut avoir des règles spéciales)
        $middleware->group('banking', [
            'auth:sanctum',
            'throttle:banking', // Rate limit spécial si configuré
        ]);

        // ==========================================
        // MIDDLEWARES WEB
        // ==========================================
        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // ==========================================
        // MIDDLEWARES API
        // ==========================================
        $middleware->api(prepend: [
            // Force Accept: application/json pour toutes les requêtes API
            \Illuminate\Http\Middleware\HandleCors::class, // Doublon intentionnel pour sécurité
        ]);

        // ==========================================
        // EXCLUSIONS DE MIDDLEWARES
        // ==========================================

        // Exclure CSRF pour routes API (déjà fait par défaut mais explicite)
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhooks/*',
            'bank/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ==========================================
        // GESTION GLOBALE DES ERREURS
        // ==========================================

        // ✅ 401 - AUTHENTICATION REQUIRED
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            // Toujours retourner JSON pour API
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'error_code' => 'AUTH_REQUIRED',
                ], 401);
            }

            // Redirection pour web (si besoin)
            return redirect()->guest(route('login'));
        });

        // ✅ 403 - FORBIDDEN
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() === 403) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage() ?: 'Access forbidden',
                        'error_code' => 'FORBIDDEN',
                    ], 403);
                }
            }
        });

        // ✅ 404 - NOT FOUND
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

        // ✅ 422 - VALIDATION ERROR
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

        // ✅ 429 - TOO MANY REQUESTS
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
                ], 429);
            }
        });

        // ✅ 500 - SERVER ERROR
        $exceptions->render(function (\Throwable $e, Request $request) {
            // Ne gérer que les erreurs 500 non gérées
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                return null; // Laisser Laravel gérer les HttpException
            }

            // Logger l'erreur pour debug
            if (app()->environment('production')) {
                \Log::error('Server Error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'url' => $request->fullUrl(),
                    'user_id' => auth()->id(),
                ]);
            }

            // Réponse JSON pour API
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => app()->environment('production')
                        ? 'An unexpected error occurred'
                        : $e->getMessage(),
                    'error_code' => 'SERVER_ERROR',
                    // Détails en dev uniquement
                    'debug' => app()->environment('local') ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => explode("\n", $e->getTraceAsString()),
                    ] : null,
                ], 500);
            }
        });

        // ==========================================
        // ERREURS SPÉCIFIQUES BRIDGE/BANKING
        // ==========================================

        // Erreurs de connexion bancaire
        $exceptions->render(function (\Exception $e, Request $request) {
            if ($request->is('api/bank/*') && str_contains($e->getMessage(), 'Bridge')) {
                \Log::warning('Bridge API Error', [
                    'message' => $e->getMessage(),
                    'endpoint' => $request->path(),
                    'user_id' => auth()->id(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Banking service temporarily unavailable',
                    'error_code' => 'BANKING_SERVICE_ERROR',
                    'retry' => true,
                ], 503);
            }
        });

        // ==========================================
        // REPORTABLE EXCEPTIONS
        // ==========================================

        // Reporter certaines erreurs à un service externe (Sentry, Bugsnag, etc.)
        $exceptions->reportable(function (\Throwable $e) {
            // Exemples d'erreurs importantes à reporter
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                // Ne pas reporter les 401 normaux
                return false;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                // Ne pas reporter les erreurs de validation
                return false;
            }

            // Reporter tout le reste en production
            return app()->environment('production');
        });

        // ==========================================
        // THROTTLE EXCEPTIONS
        // ==========================================

        // Personnaliser le comportement des throttles
        $exceptions->throttle(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests',
                'error_code' => 'THROTTLED',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
            ], 429);
        });
    })
    ->create();
