<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour s'assurer que le système gaming est actif
 * et initialiser les données gaming de l'utilisateur si nécessaire
 */
class GamingActiveMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required for gaming features',
            ], 401);
        }

        // Initialiser le niveau utilisateur si nécessaire
        $this->ensureUserLevelExists($user);

        // Vérifier les succès en arrière-plan (non bloquant)
        $this->checkAchievementsAsync($user);

        // Ajouter des headers gaming pour le frontend
        $response = $next($request);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $gamingData = $this->getQuickGamingData($user);

            $response->header('X-Gaming-Level', $gamingData['level']);
            $response->header('X-Gaming-XP', $gamingData['xp']);
            $response->header('X-Gaming-NextLevel', $gamingData['xp_to_next_level']);
        }

        return $response;
    }

    /**
     * S'assurer que l'utilisateur a un niveau gaming
     */
    private function ensureUserLevelExists($user): void
    {
        if (! $user->level) {
            \App\Models\UserLevel::create([
                'user_id' => $user->id,
                'level' => 1,
                'xp' => 0,
            ]);
        }
    }

    /**
     * Vérifier les succès de manière asynchrone
     */
    private function checkAchievementsAsync($user): void
    {
        // Utiliser une queue pour ne pas ralentir la requête
        dispatch(function () use ($user) {
            $user->checkAndUnlockAchievements();
        })->afterResponse();
    }

    /**
     * Obtenir les données gaming rapides pour les headers
     */
    private function getQuickGamingData($user): array
    {
        $level = $user->level;

        if (! $level) {
            return ['level' => 1, 'xp' => 0, 'xp_to_next_level' => 100];
        }

        $xpForCurrentLevel = $this->calculateXpForLevel($level->level);
        $xpForNextLevel = $this->calculateXpForLevel($level->level + 1);
        $xpToNextLevel = $xpForNextLevel - $level->xp;

        return [
            'level' => $level->level,
            'xp' => $level->xp,
            'xp_to_next_level' => max(0, $xpToNextLevel),
        ];
    }

    /**
     * Calculer l'XP requis pour un niveau
     */
    private function calculateXpForLevel(int $level): int
    {
        return ($level - 1) * 100 + (($level - 1) * ($level - 2) * 10);
    }
}
