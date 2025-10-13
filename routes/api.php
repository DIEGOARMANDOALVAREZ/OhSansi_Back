<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResponsableController;
use App\Models\Area;
use App\Models\Nivel;

/*
|--------------------------------------------------------------------------
| API Routes - OH SanSi
|--------------------------------------------------------------------------
| Todas las rutas de la API usan el middleware 'api' por defecto.
| Se organizan por secciones: autenticación, datos base, y CRUD protegidos.
|--------------------------------------------------------------------------
*/

// =======================================================
// 🩵 PING - Comprobación del backend
// =======================================================
Route::get('/ping', fn () => response()->json([
    'status'  => 'ok',
    'message' => 'Backend OH SanSi activo ✅',
    'time'    => now(),
]));

// =======================================================
// 🔐 AUTENTICACIÓN
// =======================================================
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/perfil', [AuthController::class, 'perfil']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Rutas protegidas según rol (slugs en español)
    Route::get('/admin/usuarios', fn () => ['ok' => true])->middleware('role:ADMINISTRADOR');
    Route::get('/responsable/panel', fn () => ['ok' => true])->middleware('role:RESPONSABLE');
    Route::get('/evaluador/panel', fn () => ['ok' => true])->middleware('role:EVALUADOR');

    // =======================================================
    // 📋 Catálogos base (solo autenticados)
    // =======================================================
    Route::get('/areas', function () {
        return Area::select('id', 'nombre', 'activo')->orderBy('nombre')->get();
        // o paginado: ->paginate(1000);
    });

    Route::get('/niveles', function () {
        return Nivel::select('id', 'nombre')->orderBy('id')->get();
        // o paginado: ->paginate(1000);
    });
});

// =======================================================
// 👤 RESPONSABLES - CRUD protegido
// =======================================================

// --- Lectura (todos los autenticados)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/responsables', [ResponsableController::class, 'index'])->name('responsables.index');
    Route::get('/responsables/{responsable}', [ResponsableController::class, 'show'])->name('responsables.show');
});

// --- Escritura (solo ADMINISTRADOR)
Route::middleware(['auth:sanctum', 'role:ADMINISTRADOR'])->group(function () {
    Route::post('/responsables', [ResponsableController::class, 'store'])->name('responsables.store');
    Route::put('/responsables/{responsable}', [ResponsableController::class, 'update'])->name('responsables.update');
    Route::delete('/responsables/{responsable}', [ResponsableController::class, 'destroy'])->name('responsables.destroy');
});

// =======================================================
// ⚙️ Fallback para rutas no encontradas
// =======================================================
Route::fallback(function () {
    return response()->json([
        'message' => 'Ruta no encontrada.',
    ], 404);
});
