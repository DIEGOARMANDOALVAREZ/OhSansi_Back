<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (API-only)
|--------------------------------------------------------------------------
| Este backend NO sirve vistas ni redirige a la SPA.
| La SPA (React) vive en otro host/puerto y consume /api/*.
|-------------------------------------------------------------------------- 
*/

// Raíz del backend: status JSON
Route::get('/', function () {
    return response()->json([
        'app'     => 'OH SanSi API',
        'status'  => 'ok',
        'time'    => now()->toIso8601String(),
        'message' => 'Este host sirve solo API. Usa /api/* desde tu frontend.',
    ]);
})->name('home');

// Ruta de login "dummy" SOLO para satisfacer redirect()->guest(route('login'))
Route::get('/login', function () {
    return response('Unauthorized.', 401);
})->name('login'); // 👈 IMPORTANTE: nombre 'login'

// (Opcional) /health simple
Route::get('/health', fn () => response()->noContent());

// Fallback general: cualquier ruta web no-API → 404 JSON
Route::fallback(function () {
    return response()->json([
        'error'   => 'Not Found',
        'message' => 'Ruta inválida en backend web. Usa /api/*.',
    ], 404);
});
