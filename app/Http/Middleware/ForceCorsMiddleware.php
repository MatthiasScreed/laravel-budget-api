<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceCorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ Origines autorisées depuis .env
        $allowedOrigins = array_filter([
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            env('FRONTEND_URL'),
            env('FRONTEND_EXPOSE_URL'),
        ]);

        $origin = $request->header('Origin');

        // ✅ Vérifier si l'origine est autorisée
        $allowOrigin = in_array($origin, $allowedOrigins) ? $origin : null;

        // Gérer OPTIONS request immédiatement
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }

        // Ajouter headers CORS seulement si origine valide
        if ($allowOrigin) {
            $response->header('Access-Control-Allow-Origin', $allowOrigin);
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->header('Access-Control-Max-Age', '86400');

        return $response;
    }
}
