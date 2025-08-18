<?php

namespace App\Http\Middleware;

use App\Services\GamingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutoXpMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // +1 XP pour chaque requête API
        if (auth()->check()) {
            GamingService::addMicroXp(auth()->user(), 1, 'api_call');
        }

        return $response;
    }
}
