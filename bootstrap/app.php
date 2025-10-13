<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// ⬇️ importa tu middleware
use App\Http\Middleware\CheckRole;
// (opcional) si creaste el ForceJsonResponseForApi
// use App\Http\Middleware\ForceJsonResponseForApi;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 🔹 Alias de middleware (reemplaza lo del Kernel)
        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        // (opcional) si quieres forzar JSON en /api/*
        // $middleware->appendToGroup('api', [
        //     ForceJsonResponseForApi::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
