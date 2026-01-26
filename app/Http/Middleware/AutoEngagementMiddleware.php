<?php

namespace App\Http\Middleware;

use App\Services\EngagementService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AutoEngagementMiddleware
{
    protected EngagementService $engagementService;

    // Actions à tracker automatiquement avec leur valeur XP
    protected array $trackableActions = [
        'GET' => [
            'api/dashboard' => ['page_view', 'dashboard', 3],
            'api/transactions' => ['page_view', 'transactions_list', 2],
            'api/transactions/*' => ['page_view', 'transaction_details', 2],
            'api/financial-goals' => ['page_view', 'goals_list', 2],
            'api/financial-goals/*' => ['page_view', 'goal_details', 2],
            'api/gaming/stats' => ['page_view', 'gaming_stats', 2],
            'api/gaming/achievements' => ['page_view', 'achievements', 2],
        ],
        'POST' => [
            // Ces endpoints ont déjà leur tracking dans les controllers
            // On évite le double tracking
        ],
        'PUT' => [
            'api/transactions/*' => ['item_update', 'transaction_update', 5],
            'api/financial-goals/*' => ['item_update', 'goal_update', 5],
        ],
        'DELETE' => [
            'api/transactions/*' => ['item_delete', 'transaction_delete', 3],
            'api/financial-goals/*' => ['item_delete', 'goal_delete', 3],
        ],
    ];

    public function __construct(EngagementService $engagementService)
    {
        $this->engagementService = $engagementService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Traiter la requête d'abord
        $response = $next($request);

        // Tracker seulement pour les utilisateurs authentifiés et les réponses réussies
        if (Auth::check() && $response->getStatusCode() < 400) {
            $this->trackRequestIfNeeded($request, $response);
        }

        return $next($request);
    }

    /**
     * Tracker la requête si elle correspond à une action trackable
     */
    protected function trackRequestIfNeeded(Request $request, Response $response): void
    {
        $method = $request->getMethod();
        $path = $this->normalizePath($request->getPathInfo());

        $actionConfig = $this->findMatchingAction($method, $path);

        if (! $actionConfig) {
            return;
        }

        [$actionType, $context, $baseXp] = $actionConfig;

        // Éviter le spam de tracking (même action dans les 30 dernières secondes)
        if ($this->isRecentDuplicate($request->user()->id, $actionType, $context)) {
            return;
        }

        // Tracker asynchrone pour ne pas ralentir la réponse
        dispatch(function () use ($request, $actionType, $context) {
            try {
                $this->engagementService->trackUserAction(
                    $request->user(),
                    $actionType,
                    $context,
                    [
                        'method' => $request->getMethod(),
                        'path' => $request->getPathInfo(),
                        'user_agent' => $request->userAgent(),
                        'ip' => $request->ip(),
                    ]
                );
            } catch (\Exception $e) {
                \Log::warning('Auto engagement tracking failed: '.$e->getMessage());
            }
        })->onQueue('engagement');
    }

    /**
     * Normaliser le chemin pour le matching
     */
    protected function normalizePath(string $path): string
    {
        // Remplacer les IDs numériques par *
        return preg_replace('/\/\d+/', '/*', $path);
    }

    /**
     * Trouver l'action correspondante
     */
    protected function findMatchingAction(string $method, string $path): ?array
    {
        $methodActions = $this->trackableActions[$method] ?? [];

        foreach ($methodActions as $pattern => $config) {
            if ($this->matchesPattern($path, $pattern)) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Vérifier si le chemin correspond au pattern
     */
    protected function matchesPattern(string $path, string $pattern): bool
    {
        // Convertir le pattern en regex
        $regex = '#^'.str_replace('*', '[^/]+', preg_quote($pattern, '#')).'$#';

        return preg_match($regex, $path);
    }

    /**
     * Vérifier si c'est un duplicate récent pour éviter le spam
     */
    protected function isRecentDuplicate(int $userId, string $actionType, string $context): bool
    {
        $cacheKey = "recent_action_{$userId}_{$actionType}_{$context}";

        if (Cache::get($cacheKey)) {
            return true;
        }

        // Marquer comme vu pendant 30 secondes
        Cache::put($cacheKey, true, 30);

        return false;
    }
}
