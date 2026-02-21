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
        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
        ]);

        $middleware->api([
            \App\Http\Middleware\ApiResponseMiddleware::class,
        ]);

        // Middleware de cache para APIs
        $middleware->alias([
            'api.cache' => \App\Http\Middleware\ApiCacheMiddleware::class,
        ]);

        // Middleware de monitoreo de performance
        $middleware->append(\App\Http\Middleware\PerformanceMonitor::class);
        $middleware->append(\App\Http\Middleware\RedirectBasedOnRole::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
