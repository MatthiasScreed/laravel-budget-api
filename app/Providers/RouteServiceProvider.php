<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // ==========================================
        // RATE LIMITERS POUR API GAMING
        // ==========================================

        // Rate limiter standard pour API
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter strict pour authentification
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()),
            ];
        });

        // Rate limiter pour actions gaming (plus généreux)
        RateLimiter::for('gaming', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter pour les uploads (restrictif)
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter pour analytics (modéré)
        RateLimiter::for('analytics', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter premium pour utilisateurs de haut niveau
        RateLimiter::for('premium', function (Request $request) {
            $user = $request->user();
            $baseLimit = 60;

            // Bonus selon le niveau utilisateur
            if ($user && $user->level) {
                $levelBonus = min(240, $user->level->level * 4); // Max 240 requêtes/min
                $baseLimit += $levelBonus;
            }

            return Limit::perMinute($baseLimit)->by($user?->id ?: $request->ip());
        });

        // ==========================================
        // CONFIGURATION DES ROUTES
        // ==========================================

        $this->routes(function () {
            // ==========================================
            // ROUTES API PRINCIPALES
            // ==========================================
            Route::middleware(['api', 'throttle:api'])
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // ==========================================
            // ROUTES WEB (SI NÉCESSAIRE)
            // ==========================================
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // ==========================================
            // ROUTES API GAMING (RATE LIMIT SPÉCIAL)
            // ==========================================
            Route::middleware(['api', 'auth:sanctum', 'throttle:gaming'])
                ->prefix('api/gaming')
                ->name('api.gaming.')
                ->group(function () {
                    // Ces routes auront un rate limit plus généreux
                    // car les actions gaming sont fréquentes
                });

            // ==========================================
            // ROUTES API ANALYTICS (RATE LIMIT MODÉRÉ)
            // ==========================================
            Route::middleware(['api', 'auth:sanctum', 'throttle:analytics'])
                ->prefix('api/analytics')
                ->name('api.analytics.')
                ->group(function () {
                    // Routes pour rapports et statistiques
                });

            // ==========================================
            // ROUTES ADMIN (PROTECTION SPÉCIALE)
            // ==========================================
            Route::middleware(['api', 'auth:sanctum', 'throttle:api', 'can:admin-access'])
                ->prefix('api/admin')
                ->name('api.admin.')
                ->group(function () {
                    // Routes administratives protégées
                });
        });

        // ==========================================
        // PATTERNS DE ROUTES PERSONNALISÉS
        // ==========================================

        // Pattern pour IDs numériques uniquement
        Route::pattern('id', '[0-9]+');
        Route::pattern('user_id', '[0-9]+');
        Route::pattern('transaction_id', '[0-9]+');
        Route::pattern('goal_id', '[0-9]+');
        Route::pattern('category_id', '[0-9]+');
        Route::pattern('achievement_id', '[0-9]+');

        // Pattern pour dates (YYYY-MM-DD)
        Route::pattern('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');

        // Pattern pour mois (YYYY-MM)
        Route::pattern('month', '[0-9]{4}-[0-9]{2}');

        // Pattern pour années
        Route::pattern('year', '[0-9]{4}');

        // Pattern pour types de transactions
        Route::pattern('type', 'income|expense');

        // Pattern pour statuts
        Route::pattern('status', 'active|completed|paused|cancelled');

        // Pattern pour devises
        Route::pattern('currency', '[A-Z]{3}');

        // ==========================================
        // MODEL BINDINGS PERSONNALISÉS
        // ==========================================

        // Binding automatique avec vérification de propriété
        Route::bind('userTransaction', function (string $value) {
            $transaction = \App\Models\Transaction::findOrFail($value);

            // Vérifier que la transaction appartient à l'utilisateur connecté
            if (auth()->check() && $transaction->user_id !== auth()->id()) {
                abort(403, 'Unauthorized access to transaction');
            }

            return $transaction;
        });

        Route::bind('userGoal', function (string $value) {
            $goal = \App\Models\FinancialGoal::findOrFail($value);

            if (auth()->check() && $goal->user_id !== auth()->id()) {
                abort(403, 'Unauthorized access to goal');
            }

            return $goal;
        });

        Route::bind('userCategory', function (string $value) {
            $category = \App\Models\Category::findOrFail($value);

            if (auth()->check() && $category->user_id !== auth()->id()) {
                abort(403, 'Unauthorized access to category');
            }

            return $category;
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Cette méthode est appelée automatiquement par boot()
        // Utilisée pour des configurations avancées si nécessaire
    }
}
