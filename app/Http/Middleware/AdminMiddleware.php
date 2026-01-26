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
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vérifier que l'utilisateur est connecté
        if (! $request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        // Vérifier les permissions admin
        if (! $request->user()->can('admin-access')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès administrateur requis',
            ], 403);
        }

        // Log des actions admin pour audit
        \Log::info('Admin action', [
            'admin_id' => $request->user()->id,
            'admin_name' => $request->user()->name,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'url' => $request->url(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $next($request);
    }
}
