<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitByEngagementMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = $request->user();

        // Ajuster les limites selon le niveau d'engagement
        [$adjustedMaxAttempts, $adjustedDecayMinutes] = $this->adjustLimitsForUser($user, (int) $maxAttempts, (int) $decayMinutes);

        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $adjustedMaxAttempts)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de requêtes. Essayez dans quelques minutes.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        RateLimiter::hit($key, $adjustedDecayMinutes * 60);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $adjustedMaxAttempts,
            'X-RateLimit-Remaining' => max(0, $adjustedMaxAttempts - RateLimiter::attempts($key)),
        ]);
    }

    /**
     * Ajuster les limites selon l'engagement de l'utilisateur
     */
    protected function adjustLimitsForUser($user, int $maxAttempts, int $decayMinutes): array
    {
        $engagementScore = $user->engagement_score ?? 0;
        $level = $user->level?->level ?? 1;

        // Multiplier les limites selon l'engagement et le niveau
        $multiplier = 1.0;

        if ($engagementScore >= 80) {
            $multiplier += 0.5; // +50% pour les très engagés
        } elseif ($engagementScore >= 60) {
            $multiplier += 0.3; // +30% pour les bien engagés
        } elseif ($engagementScore >= 40) {
            $multiplier += 0.2; // +20% pour les moyennement engagés
        }

        // Bonus niveau
        if ($level >= 20) {
            $multiplier += 0.3;
        } elseif ($level >= 10) {
            $multiplier += 0.2;
        }

        return [
            (int) ceil($maxAttempts * $multiplier),
            $decayMinutes,
        ];
    }

    /**
     * Créer une signature unique pour la requête
     */
    protected function resolveRequestSignature(Request $request): string
    {
        return sha1(
            $request->user()->id.'|'.
            $request->getMethod().'|'.
            $request->getPathInfo().'|'.
            $request->ip()
        );
    }
}
