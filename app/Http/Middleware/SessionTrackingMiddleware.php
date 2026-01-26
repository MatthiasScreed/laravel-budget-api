<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SessionTrackingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $this->trackSession($request);
        }

        return $next($request);
    }

    /**
     * Tracker la session utilisateur
     */
    protected function trackSession(Request $request): void
    {
        $user = $request->user();
        $sessionId = $request->session()->getId();

        $cacheKey = "user_session_{$user->id}_{$sessionId}";

        // Récupérer ou créer la session étendue
        $sessionData = Cache::get($cacheKey, function () use ($user, $sessionId, $request) {
            // Chercher une session existante récente ou en créer une nouvelle
            $existingSession = UserSessionExtended::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->first();

            if (! $existingSession) {
                $existingSession = UserSessionExtended::create([
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'started_at' => now(),
                    'actions_count' => 0,
                    'xp_earned' => 0,
                    'pages_visited' => [],
                    'device_type' => $this->detectDeviceType($request),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return [
                'id' => $existingSession->id,
                'started_at' => $existingSession->started_at,
                'actions_count' => $existingSession->actions_count,
                'xp_earned' => $existingSession->xp_earned,
                'pages_visited' => $existingSession->pages_visited ?? [],
            ];
        });

        // Mettre à jour les statistiques de session
        $sessionData['actions_count']++;
        $sessionData['pages_visited'][] = $request->getPathInfo();
        $sessionData['pages_visited'] = array_slice(array_unique($sessionData['pages_visited']), -20); // Garder les 20 dernières pages uniques

        // Sauvegarder en cache pour 30 minutes
        Cache::put($cacheKey, $sessionData, 1800);

        // Mettre à jour la base de données toutes les 10 actions pour éviter trop d'écritures
        if ($sessionData['actions_count'] % 10 === 0) {
            $this->persistSessionData($sessionData);
        }
    }

    /**
     * Persister les données de session en base
     */
    protected function persistSessionData(array $sessionData): void
    {
        UserSessionExtended::where('id', $sessionData['id'])->update([
            'ended_at' => now(),
            'actions_count' => $sessionData['actions_count'],
            'pages_visited' => $sessionData['pages_visited'],
        ]);
    }

    /**
     * Détecter le type d'appareil
     */
    protected function detectDeviceType(Request $request): string
    {
        $userAgent = $request->userAgent();

        if (preg_match('/mobile|android|iphone|ipad/i', $userAgent)) {
            return preg_match('/ipad|tablet/i', $userAgent) ? 'tablet' : 'mobile';
        }

        return 'desktop';
    }
}
