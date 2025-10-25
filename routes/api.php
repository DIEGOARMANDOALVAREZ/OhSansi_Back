<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResponsableController;
use App\Http\Controllers\EvaluadorController;
use App\Http\Controllers\EvaluacionController;
use App\Http\Controllers\InscritoController;
use App\Http\Controllers\ClasificacionController;
use App\Http\Controllers\LogNotasController; // ✅ CORREGIDO (fuera de la carpeta Responsable)

use App\Http\Middleware\AuthResponsable;
use App\Http\Middleware\AuthEvaluador;

use App\Models\Area;
use App\Models\Nivel;

/*
|--------------------------------------------------------------------------
| API Routes - OH SanSi
|--------------------------------------------------------------------------
| Nota: routes/api.php carga por defecto el middleware 'api'.
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
// 🔐 AUTENTICACIÓN PRINCIPAL
// =======================================================
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

// =======================================================
// 🔒 ZONA PROTEGIDA (Sanctum) - Usuarios del sistema
// =======================================================
Route::middleware('auth:sanctum')->group(function () {

    // Perfil y logout
    Route::get('/auth/perfil', [AuthController::class, 'perfil'])->name('auth.perfil');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // 📋 Catálogos base
    Route::get('/areas', fn () => Area::select('id', 'nombre', 'activo')->orderBy('nombre')->get())
        ->name('catalogo.areas');
    Route::get('/niveles', fn () => Nivel::select('id', 'nombre')->orderBy('id')->get())
        ->name('catalogo.niveles');

    // ===================================================
    // 👤 RESPONSABLES - CRUD (solo ADMIN)
    // ===================================================
    Route::middleware('role:ADMINISTRADOR')->group(function () {
        Route::get('/responsables', [ResponsableController::class, 'index'])->name('responsables.index');
        Route::get('/responsables/{responsable}', [ResponsableController::class, 'show'])->name('responsables.show');
        Route::post('/responsables', [ResponsableController::class, 'store'])->name('responsables.store');
        Route::put('/responsables/{responsable}', [ResponsableController::class, 'update'])->name('responsables.update');
        Route::delete('/responsables/{responsable}', [ResponsableController::class, 'destroy'])->name('responsables.destroy');
    });

    // ===================================================
    // 👤 EVALUADORES - CRUD (solo ADMIN)
    // ===================================================
    Route::middleware('role:ADMINISTRADOR')->group(function () {
        Route::get('/evaluadores', [EvaluadorController::class, 'index'])->name('evaluadores.index');
        Route::get('/evaluadores/{evaluador}', [EvaluadorController::class, 'show'])->name('evaluadores.show');
        Route::post('/evaluadores', [EvaluadorController::class, 'store'])->name('evaluadores.store');
        Route::put('/evaluadores/{evaluador}', [EvaluadorController::class, 'update'])->name('evaluadores.update');
        Route::delete('/evaluadores/{evaluador}', [EvaluadorController::class, 'destroy'])->name('evaluadores.destroy');

        // Tokens de Evaluador (emitir/revocar)
        Route::post('/admin/evaluadores/{evaluador}/emitir-token', [EvaluadorController::class, 'emitirToken'])
            ->name('evaluadores.emitirToken');
        Route::post('/admin/evaluadores/{evaluador}/revocar-tokens', [EvaluadorController::class, 'revocarTokens'])
            ->name('evaluadores.revocarTokens');
    });

    // ===================================================
    // 📥 INSCRITOS (solo ADMIN)
    // ===================================================
    Route::middleware('role:ADMINISTRADOR')->group(function () {
        Route::post('/inscritos/import', [InscritoController::class, 'import'])->name('inscritos.import');
        Route::get('/inscritos', [InscritoController::class, 'getInscritos'])->name('inscritos.list');
    });
});

// =======================================================
// 🧑‍💼 RUTAS PARA RESPONSABLES (token plano ResponsableToken)
// =======================================================
Route::middleware(AuthResponsable::class)->group(function () {

    // Perfil del responsable
    Route::get('/responsable/perfil', [AuthController::class, 'perfilResponsable'])->name('responsable.perfil');

    // Panel/resumen
    Route::get('/responsable/panel', [ResponsableController::class, 'panel'])->name('responsable.panel');

    // Lista de competidores con filtros
    Route::get('/responsable/lista-competidores', [ResponsableController::class, 'listaCompetidores'])
        ->name('responsable.listaCompetidores');

    // Opciones dinámicas para combos
    Route::get('/responsable/opciones-filtros', [ResponsableController::class, 'opcionesFiltros'])
        ->name('responsable.opcionesFiltros');

    // Reabrir evaluaciones
    Route::post('/evaluaciones/{inscrito}/reabrir', [EvaluacionController::class, 'reabrir'])
        ->name('responsable.reabrirEvaluacion');

    // ===================================================
    // 🎯 HU-6: GENERAR LISTA DE CLASIFICADOS
    // ===================================================
    Route::get('/responsable/clasificacion/preview', [ClasificacionController::class, 'preview'])
        ->name('responsable.clasificacion.preview');

    Route::post('/responsable/clasificacion/confirm', [ClasificacionController::class, 'confirm'])
        ->name('responsable.clasificacion.confirm');

    Route::get('/responsable/clasificacion/export', [ClasificacionController::class, 'exportCsv'])
        ->name('responsable.clasificacion.export');
        
    Route::get('/responsable/clasificacion/list', [ClasificacionController::class, 'list'])
        ->name('responsable.clasificacion.list');

    // ===================================================
    // 🧾 HU-8: LOG DE CAMBIOS DE NOTAS (auditoría)
    // ===================================================
    Route::get('/responsable/log-notas', [LogNotasController::class, 'index'])
        ->name('responsable.logNotas.index');
    Route::get('/responsable/log-notas/export', [LogNotasController::class, 'exportCsv'])
        ->name('responsable.logNotas.exportCsv');
    Route::get('/responsable/log-notas/export-xlsx', [LogNotasController::class, 'exportXlsx'])
        ->name('responsable.logNotas.exportXlsx');
});

// =======================================================
// 🧑‍🔬 RUTAS PARA EVALUADORES (token plano EvaluadorToken)
// =======================================================
Route::middleware(AuthEvaluador::class)->group(function () {
    // Perfil del evaluador
    Route::get('/evaluador/perfil', [AuthController::class, 'perfilEvaluador'])->name('evaluador.perfil');

    // Evaluaciones asignadas / gestión
    Route::get('/evaluaciones/asignadas', [EvaluacionController::class, 'asignadas'])
        ->name('evaluador.evaluaciones.asignadas');
    Route::post('/evaluaciones/{inscrito}/guardar', [EvaluacionController::class, 'guardar'])
        ->name('evaluador.evaluaciones.guardar');
    Route::post('/evaluaciones/{inscrito}/finalizar', [EvaluacionController::class, 'finalizar'])
        ->name('evaluador.evaluaciones.finalizar');
});

// =======================================================
// 🚫 RUTA FALLBACK (no encontrada)
// =======================================================
Route::fallback(fn () => response()->json(['message' => 'Ruta no encontrada.'], 404));
