<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Evaluador;
use App\Models\EvaluadorToken;
use App\Models\Audit;

use App\Http\Requests\StoreEvaluadorRequest;
use App\Http\Requests\UpdateEvaluadorRequest;

use Throwable;

class EvaluadorController extends Controller
{
    // ==========================================================
    // 📋 LISTAR (búsqueda + filtros de asociaciones + paginación)
    // ==========================================================
    public function index(Request $request)
    {
        try {
            $query = Evaluador::query()
                ->with(['asociaciones']);

            // Búsqueda (PostgreSQL -> ILIKE). Para MySQL usa like.
            if ($request->filled('search')) {
                $s = trim((string) $request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('nombres', 'ilike', "%{$s}%")
                      ->orWhere('apellidos', 'ilike', "%{$s}%")
                      ->orWhere('correo', 'ilike', "%{$s}%")
                      ->orWhere('ci', 'ilike', "%{$s}%");
                });
            }

            // Filtros por relación (area_id / nivel_id)
            if ($request->filled('area_id')) {
                $query->whereHas('asociaciones', function ($q) use ($request) {
                    $q->where('area_id', (int) $request->area_id);
                });
            }
            if ($request->filled('nivel_id')) {
                $query->whereHas('asociaciones', function ($q) use ($request) {
                    $q->where('nivel_id', (int) $request->nivel_id);
                });
            }

            $perPage = (int) $request->get('per_page', 10);
            $perPage = max(1, min($perPage, 100));

            $paginator = $query
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->paginate($perPage);

            // “Aplanar” asociaciones para el front
            $paginator->getCollection()->transform(function ($e) {
                return $this->shapeEvaluador($e);
            });

            return response()->json($paginator, 200);
        } catch (Throwable $e) {
            Log::error('EVALUADORES index error', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['message' => 'Error al listar evaluadores.'], 500);
        }
    }

    // ----------------------------------------------------------
    // 🔎 DETALLE
    public function show(Evaluador $evaluador)
    {
        try {
            $evaluador->load('asociaciones');
            $shaped = $this->shapeEvaluador($evaluador);
            return response()->json($shaped, 200);
        } catch (Throwable $e) {
            Log::error('EVALUADOR show error', [
                'id' => $evaluador->id,
                'msg' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Error al obtener evaluador.'], 500);
        }
    }

    // ----------------------------------------------------------
    // 🟢 CREAR
    public function store(StoreEvaluadorRequest $req)
    {
        $validated = $req->validated();

        $baseData = collect($validated)->except(['area_id', 'nivel_id', 'asociaciones'])->toArray();
        $baseData = $this->normalizeEvaluadorData($baseData);

        $asociacionesSync = $this->buildAsociacionesSyncArray(
            $validated['asociaciones'] ?? null,
            $validated['area_id'] ?? [],
            $validated['nivel_id'] ?? null
        );

        try {
            $evaluador = DB::transaction(function () use ($baseData, $asociacionesSync) {
                $e = Evaluador::create($baseData);
                if (!empty($asociacionesSync)) {
                    $e->asociaciones()->sync($asociacionesSync);
                }
                return $e->load('asociaciones');
            });

            try {
                Audit::log(Auth::id(), 'Evaluador', $evaluador->id, 'CREAR', $evaluador->toArray());
            } catch (Throwable $e) {
                Log::warning('AUDIT store falló', ['id' => $evaluador->id, 'error' => $e->getMessage()]);
            }

            $shaped = $this->shapeEvaluador($evaluador);
            return response()->json(['message' => 'Evaluador creado', 'data' => $shaped], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('EVALUADOR store error', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['message' => 'Error al crear evaluador.'], 500);
        }
    }

    // ----------------------------------------------------------
    // ✏️ ACTUALIZAR
    public function update(UpdateEvaluadorRequest $req, Evaluador $evaluador)
    {
        $before    = $evaluador->toArray();
        $validated = $req->validated();

        $baseData = collect($validated)->except(['area_id', 'nivel_id', 'asociaciones'])->toArray();
        $baseData = $this->normalizeEvaluadorData($baseData);

        $asociacionesSync = $this->buildAsociacionesSyncArray(
            $validated['asociaciones'] ?? null,
            $validated['area_id'] ?? null,
            $validated['nivel_id'] ?? null
        );

        try {
            DB::transaction(function () use ($evaluador, $baseData, $asociacionesSync) {
                $evaluador->update($baseData);

                if (!is_null($asociacionesSync)) {
                    $evaluador->asociaciones()->sync($asociacionesSync);
                }
            });

            $fresh = $evaluador->fresh(['asociaciones']);

            try {
                Audit::log(Auth::id(), 'Evaluador', $evaluador->id, 'EDITAR', [
                    'before' => $before,
                    'after'  => $fresh?->toArray(),
                ]);
            } catch (Throwable $e) {
                Log::warning('AUDIT update falló', ['id' => $evaluador->id, 'error' => $e->getMessage()]);
            }

            $shaped = $this->shapeEvaluador($fresh);
            return response()->json(['message' => 'Evaluador actualizado', 'data' => $shaped], 200);

        } catch (Throwable $e) {
            Log::error('EVALUADOR update error', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['message' => 'Error inesperado al actualizar.'], 500);
        }
    }

    // ----------------------------------------------------------
    // 🗑️ ELIMINAR (sin “inactivar”)
    public function destroy(Request $request, Evaluador $evaluador)
    {
        $before = $evaluador->toArray();

        try {
            if ($request->boolean('hard')) {
                if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($evaluador))) {
                    $evaluador->forceDelete();
                } else {
                    $evaluador->delete();
                }
            } else {
                // Como tu modelo no usa SoftDeletes, esto será delete real igual.
                $evaluador->delete();
            }

            try {
                Audit::log(Auth::id(), 'Evaluador', $before['id'] ?? null, 'ELIMINAR', [
                    'before' => $before,
                    'after'  => null
                ]);
            } catch (Throwable $e) {
                Log::warning('AUDIT destroy falló', ['id' => $before['id'] ?? null, 'error' => $e->getMessage()]);
            }

            return response()->json(['message' => 'Evaluador eliminado'], 200);

        } catch (Throwable $e) {
            Log::error('EVALUADOR destroy error', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['message' => 'Error al eliminar evaluador.'], 500);
        }
    }

    // ==========================================================
    // 🔐 TOKENS (Admin)
    // ==========================================================
    public function emitirToken(Request $request, Evaluador $evaluador)
    {
        $request->validate([
            'rotar' => ['sometimes','boolean'],
            'name'  => ['sometimes','string','max:50'],
        ]);

        try {
            $rotar = (bool) $request->input('rotar', false);
            $name  = $request->input('name', 'admin-emit');

            if ($rotar) {
                EvaluadorToken::where('evaluador_id', $evaluador->id)->delete();
            }

            $plain = Str::random(10);

            EvaluadorToken::create([
                'evaluador_id' => $evaluador->id,
                'name'         => $name,
                'token'        => hash('sha256', $plain),
                'abilities'    => ['*'],
            ]);

            try {
                Audit::log(Auth::id(), 'EvaluadorToken', $evaluador->id, 'CREAR', ['name' => $name]);
            } catch (Throwable $e) {
                Log::warning('AUDIT emitirToken falló', ['id' => $evaluador->id, 'error' => $e->getMessage()]);
            }

            return response()->json([
                'message' => 'Token emitido',
                'token'   => $plain,
            ], 201);

        } catch (Throwable $e) {
            Log::error('EVALUADOR emitirToken error', [
                'id'  => $evaluador->id,
                'msg' => $e->getMessage()
            ]);
            return response()->json(['message' => 'No se pudo emitir el token.'], 500);
        }
    }

    public function revocarTokens(Request $request, Evaluador $evaluador)
    {
        try {
            EvaluadorToken::where('evaluador_id', $evaluador->id)->delete();

            try {
                Audit::log(Auth::id(), 'EvaluadorToken', $evaluador->id, 'ELIMINAR', ['all' => true]);
            } catch (Throwable $e) {
                Log::warning('AUDIT revocarTokens falló', ['id' => $evaluador->id, 'error' => $e->getMessage()]);
            }

            return response()->json(['message' => 'Todos los tokens del evaluador fueron revocados.'], 200);

        } catch (Throwable $e) {
            Log::error('EVALUADOR revocarTokens error', [
                'id'  => $evaluador->id,
                'msg' => $e->getMessage()
            ]);
            return response()->json(['message' => 'No se pudieron revocar los tokens.'], 500);
        }
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------
    private function normalizeEvaluadorData(array $data): array
    {
        if (isset($data['correo'])) {
            $data['correo'] = strtolower(trim($data['correo']));
        }
        if (array_key_exists('ci', $data) && $data['ci'] !== null) {
            $data['ci'] = trim((string) $data['ci']);
        }
        return $data;
    }

    private function buildAsociacionesSyncArray($asociaciones, $areaIds, $nivelIdRaw): ?array
    {
        // Caso 1: asociaciones explícitas
        if (is_array($asociaciones) && !empty($asociaciones)) {
            $sync = [];
            foreach ($asociaciones as $a) {
                if (!isset($a['area_id'])) continue;
                $sync[(int)$a['area_id']] = ['nivel_id' => $this->normalizeNivelId($a['nivel_id'] ?? null)];
            }
            return $sync;
        }

        // Caso 2: area_id[] + nivel_id único
        if (is_array($areaIds) && !empty($areaIds)) {
            $sync = [];
            $nivelId = $this->normalizeNivelId($nivelIdRaw);
            foreach ($areaIds as $a) {
                $sync[(int)$a] = ['nivel_id' => $nivelId];
            }
            return $sync;
        }

        // No se envió nada
        return null;
    }

    private function normalizeNivelId($raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === 'null' || (is_array($raw) && empty($raw))) {
            return null;
        }
        return (int) $raw;
    }

    private function shapeEvaluador(?Evaluador $e): array
    {
        if (!$e) return [];
        $arr = $e->toArray();

        if ($e->relationLoaded('asociaciones')) {
            $arr['asociaciones'] = $e->asociaciones
                ->map(function ($area) {
                    return [
                        'area_id'  => (int) $area->id,
                        'nivel_id' => $area->pivot?->nivel_id ? (int) $area->pivot->nivel_id : null,
                    ];
                })
                ->values()
                ->all();
        } else {
            $arr['asociaciones'] = [];
        }

        return $arr;
    }
}
