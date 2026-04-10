<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour vérifier les permissions administrateur
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        // ✅ Vérification simple via champ is_admin (pas de Gate/Policy)
        if (!$request->user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Accès administrateur requis',
            ], 403);
        }

        \Log::info('Admin action', [
            'admin_id' => $request->user()->id,
            'route'    => $request->route()?->getName(),
            'method'   => $request->method(),
            'ip'       => $request->ip(),
        ]);

        return $next($request);
    }
}
