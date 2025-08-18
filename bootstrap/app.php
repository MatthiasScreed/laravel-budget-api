<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ✅ CORS en premier !
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

       $middleware->append(\App\Http\Middleware\CorsMiddleware::class);
        // ==========================================
        // SANCTUM CONFIGURATION
        // ==========================================
        $middleware->statefulApi();

        // ==========================================
        // RATE LIMITING
        // ==========================================
        $middleware->throttleApi();

        // ==========================================
        // MIDDLEWARE PERSONNALISÉS (optionnel)
        // ==========================================
        $middleware->alias([
            'cors' => \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
