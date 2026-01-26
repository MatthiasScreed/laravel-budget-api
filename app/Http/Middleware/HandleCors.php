<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CORS unifié pour CoinQuest
 *
 * Gère les requêtes cross-origin entre le frontend Vue.js et l'API Laravel,
 * ainsi que les callbacks de Bridge API.
 */
class HandleCors
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Récupérer l'origine de la requête
        $origin = $request->header('Origin');

        // Liste des origines autorisées depuis .env
        $allowedOrigins = explode(',', env('CORS_ALLOWED_ORIGINS', '*'));

        // Patterns autorisés (Expose, localhost)
        $allowedPatterns = [
            '#^https://[a-z0-9-]+\.sharedwithexpose\.com$#', // Expose Pro
            '#^http://localhost:[0-9]+$#',                    // localhost
            '#^http://127\.0\.0\.1:[0-9]+$#',                // 127.0.0.1
        ];

        // Vérifier si l'origine est autorisée
        $isAllowed = in_array('*', $allowedOrigins)
            || in_array($origin, $allowedOrigins)
            || $this->matchesPattern($origin, $allowedPatterns);

        // Gérer les requêtes OPTIONS (preflight)
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($origin, $isAllowed);
        }

        // Traiter la requête normale
        $response = $next($request);

        // Ajouter les headers CORS à la réponse
        return $this->addCorsHeaders($response, $origin, $isAllowed);
    }

    /**
     * Gérer les requêtes preflight OPTIONS
     */
    private function handlePreflightRequest(?string $origin, bool $isAllowed): Response
    {
        $response = response('', 200);

        if ($isAllowed && $origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-Token');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24h

        return $response;
    }

    /**
     * Ajouter les headers CORS à la réponse
     */
    private function addCorsHeaders(Response $response, ?string $origin, bool $isAllowed): Response
    {
        if ($isAllowed && $origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Length, Content-Type');
        }

        return $response;
    }

    /**
     * Vérifier si l'origine correspond à un pattern
     */
    private function matchesPattern(?string $origin, array $patterns): bool
    {
        if (! $origin) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }
}
