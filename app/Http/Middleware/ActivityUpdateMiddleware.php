<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ActivityUpdateMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Mettre à jour l'activité après une réponse réussie
        if (Auth::check() && $response->getStatusCode() < 400) {
            $this->updateUserActivity($request);
        }

        return $response;
    }

    /**
     * Mettre à jour l'activité de l'utilisateur
     */
    protected function updateUserActivity(Request $request): void
    {
        $user = $request->user();

        // Éviter de mettre à jour trop fréquemment
        $cacheKey = "last_activity_update_{$user->id}";

        if (Cache::get($cacheKey)) {
            return; // Déjà mis à jour récemment
        }

        // Marquer comme mis à jour pour les 2 prochaines minutes
        Cache::put($cacheKey, true, 120);

        // Mettre à jour asynchrone
        dispatch(function () use ($user) {
            try {
                $user->update([
                    'last_activity_at' => now(),
                ]);

                // Vérifier si c'est une nouvelle session quotidienne
                $this->checkDailyLogin($user);

            } catch (\Exception $e) {
                \Log::warning('Activity update failed: '.$e->getMessage());
            }
        })->onQueue('activity');
    }

    /**
     * Vérifier et enregistrer la connexion quotidienne
     */
    protected function checkDailyLogin($user): void
    {
        $today = now()->format('Y-m-d');

        $dailyLoginKey = "daily_login_{$user->id}_{$today}";

        if (! Cache::get($dailyLoginKey)) {
            // Première connexion de la journée
            Cache::put($dailyLoginKey, true, now()->endOfDay());

            // Créer l'action de login quotidien
            \App\Models\UserAction::trackAction(
                $user->id,
                'daily_login',
                'system',
                ['login_date' => $today]
            );
        }
    }
}
