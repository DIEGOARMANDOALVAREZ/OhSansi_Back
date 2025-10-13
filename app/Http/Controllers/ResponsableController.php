<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Responsable;
use App\Models\Audit;
use App\Http\Requests\StoreResponsableRequest;
use App\Http\Requests\UpdateResponsableRequest;
use Throwable;

class ResponsableController extends Controller
{
    // ==========================================================
    // 📋 LISTAR (filtros + búsqueda + paginación)
    // GET /api/responsables?search=&area_id=&nivel_id=&estado=&per_page=
    // estado: 'activo' | 'inactivo' | 1 | 0 | 'true' | 'false'
    // ==========================================================
    public function index(Request $request)
    {
        try {
            $query = Responsable::query()->with(['area', 'nivel']);

            // Búsqueda (PostgreSQL -> ILIKE)
            if ($request->filled('search')) {
                $s = trim((string) $request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('nombres', 'ilike', "%{$s}%")
                      ->orWhere('apellidos', 'ilike', "%{$s}%")
                      ->orWhere('correo', 'ilike', "%{$s}%");
                });
            }

            // Filtros
            if ($request->filled('area_id')) {
                $query->where('area_id', (int) $request->area_id);
            }
            if ($request->filled('nivel_id')) {
                $query->where('nivel_id', (int) $request->nivel_id);
            }
            if ($request->filled('estado')) {
                $estado = $this->parseEstado($request->estado);
                if ($estado !== null) {
                    $query->where('activo', $estado);
                }
            }

            $perPage = max(1, (int) $request->get('per_page', 10));

            return response()->json(
                $query->orderBy('apellidos')->orderBy('nombres')->paginate($perPage),
                200
            );
        } catch (Throwable $e) {
            Log::error('RESPONSABLES index error', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['message' => 'Error al listar responsables.'], 500);
        }
    }

    // ==========================================================
    // 🔎 DETALLE
    // GET /api/responsables/{responsable}
    // ==========================================================
    public function show(Responsable $responsable)
    {
        try {
            $responsable->load(['area', 'nivel']);
            return response()->json($responsable, 200);
        } catch (Throwable $e) {
            Log::error('RESPONSABLE show error', ['id' => $responsable->id, 'msg' => $e->getMessage()]);
            // Si cargar relaciones falla, devolvemos el básico para no romper UX
            return response()->json($responsable, 200);
        }
    }

    // ==========================================================
    // 🟢 CREAR
    // POST /api/responsables
    // ==========================================================
    public function store(StoreResponsableRequest $req)
    {
        $data = $this->normalize($req->validated());

        // Unicidad: un ACTIVO por (area_id, nivel_id)
        if (!empty($data['activo'])) {
            $exists = Responsable::where('area_id', $data['area_id'])
                ->when($data['nivel_id'] === null,
                    fn ($q) => $q->whereNull('nivel_id'),
                    fn ($q) => $q->where('nivel_id', $data['nivel_id'])
                )
                ->where('activo', true)
                ->first();

            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe un responsable ACTIVO para esa combinación de área/nivel.',
                ], Response::HTTP_CONFLICT);
            }
        }

        try {
            // persistencia
            $r = DB::transaction(function () use ($data) {
                return Responsable::create($data);
            });

            // Auditoría NO crítica
            try {
                Audit::log(Auth::id(), 'Responsable', $r->id, 'CREAR', $r->toArray());
            } catch (Throwable $e) {
                Log::warning('AUDIT store falló', ['id' => $r->id, 'error' => $e->getMessage()]);
            }

            // Cargar relaciones (si falla, no romper)
            try {
                $r->load(['area', 'nivel']);
            } catch (Throwable $e) {
                Log::warning('STORE load relaciones falló', ['id' => $r->id, 'error' => $e->getMessage()]);
            }

            return response()->json(['message' => 'Creado', 'data' => $r], Response::HTTP_CREATED);

        } catch (QueryException $e) {
            // Postgres unique violation: 23505 (y tu constraint uniq_responsable_activo)
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'uniq_responsable_activo')) {
                return response()->json([
                    'message' => 'No se puede guardar: combinación de área/nivel ya tiene un responsable activo.',
                ], Response::HTTP_CONFLICT);
            }
            Log::error('RESPONSABLE store SQL error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error al crear responsable.'], 422);

        } catch (Throwable $e) {
            Log::error('RESPONSABLE store error', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['message' => 'Error inesperado al crear.'], 500);
        }
    }

    // ==========================================================
    // ✏️ ACTUALIZAR
    // PUT/PATCH /api/responsables/{responsable}
    // ==========================================================
    public function update(UpdateResponsableRequest $req, Responsable $responsable)
    {
        $before = $responsable->toArray();
        $data   = $this->normalize($req->validated());

        // Unicidad: un ACTIVO por (area_id, nivel_id), excluyendo el actual
        if (!empty($data['activo'])) {
            $exists = Responsable::where('area_id', $data['area_id'])
                ->when($data['nivel_id'] === null,
                    fn ($q) => $q->whereNull('nivel_id'),
                    fn ($q) => $q->where('nivel_id', $data['nivel_id'])
                )
                ->where('activo', true)
                ->where('id', '!=', $responsable->id)
                ->first();

            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe un responsable ACTIVO para esa combinación de área/nivel.',
                ], Response::HTTP_CONFLICT);
            }
        }

        try {
            DB::transaction(function () use ($responsable, $data) {
                $responsable->update($data);
            });

            // Auditoría NO crítica
            try {
                Audit::log(Auth::id(), 'Responsable', $responsable->id, 'EDITAR', [
                    'before' => $before,
                    'after'  => $responsable->fresh()->toArray(),
                ]);
            } catch (Throwable $e) {
                Log::warning('AUDIT update falló', ['id' => $responsable->id, 'error' => $e->getMessage()]);
            }

            // respuesta con relaciones (si carga falla, no romper)
            try {
                $fresh = $responsable->fresh(['area','nivel']);
            } catch (Throwable $e) {
                Log::warning('UPDATE load relaciones falló', ['id' => $responsable->id, 'error' => $e->getMessage()]);
                $fresh = $responsable->fresh();
            }

            return response()->json(['message' => 'Actualizado', 'data' => $fresh], 200);

        } catch (QueryException $e) {
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'uniq_responsable_activo')) {
                return response()->json([
                    'message' => 'No se puede actualizar: combinación de área/nivel ya tiene un responsable activo.',
                ], Response::HTTP_CONFLICT);
            }
            Log::error('RESPONSABLE update SQL error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error al actualizar responsable.'], 422);

        } catch (Throwable $e) {
            Log::error('RESPONSABLE update error', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['message' => 'Error inesperado al actualizar.'], 500);
        }
    }

    // ==========================================================
    // 🗑️ INACTIVAR / ELIMINAR
    // DELETE /api/responsables/{responsable}
    // - Inactivar (por defecto)
    // - Eliminar definitivo: DELETE ...?hard=1
    // ==========================================================
    public function destroy(Request $request, Responsable $responsable)
    {
        $before = $responsable->toArray();

        try {
            if ($request->boolean('hard')) {
                // Si usas SoftDeletes en el modelo:
                if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($responsable))) {
                    $responsable->forceDelete();
                } else {
                    $responsable->delete();
                }

                // Auditoría NO crítica
                try {
                    Audit::log(Auth::id(), 'Responsable', $before['id'] ?? null, 'ELIMINAR', [
                        'before' => $before,
                        'after'  => null,
                    ]);
                } catch (Throwable $e) {
                    Log::warning('AUDIT destroy hard falló', ['id' => $before['id'] ?? null, 'error' => $e->getMessage()]);
                }

                return response()->json(['message' => 'Responsable eliminado definitivamente'], 200);
            }

            // Inactivar (borrado lógico)
            DB::transaction(function () use ($responsable) {
                $responsable->update(['activo' => false]);
            });

            try {
                Audit::log(Auth::id(), 'Responsable', $responsable->id, 'EDITAR', [
                    'before' => $before,
                    'after'  => $responsable->toArray(),
                ]);
            } catch (Throwable $e) {
                Log::warning('AUDIT destroy falló', ['id' => $responsable->id, 'error' => $e->getMessage()]);
            }

            return response()->json(['message' => 'Responsable inactivado'], 200);

        } catch (Throwable $e) {
            Log::error('RESPONSABLE destroy error', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['message' => 'Error al eliminar responsable.'], 500);
        }
    }

    // ==========================================================
    // 🔧 Helpers
    // ==========================================================
    private function parseEstado($raw): ?bool
    {
        $raw = is_string($raw) ? strtolower(trim($raw)) : $raw;
        return match ($raw) {
            '1', 1, 'true', true, 'activo'     => true,
            '0', 0, 'false', false, 'inactivo' => false,
            default                            => null,
        };
    }

    private function normalize(array $data): array
    {
        if (isset($data['area_id'])) {
            $data['area_id'] = (int) $data['area_id'];
        }

        if (array_key_exists('nivel_id', $data)) {
            $data['nivel_id'] = ($data['nivel_id'] === null || $data['nivel_id'] === '')
                ? null
                : (int) $data['nivel_id'];
        }

        if (isset($data['activo'])) {
            $data['activo'] = (bool) $data['activo'];
        }

        if (array_key_exists('telefono', $data)) {
            $tel = trim((string) ($data['telefono'] ?? ''));
            $tel = preg_replace('/[^\d+]/', '', $tel) ?: null;
            $data['telefono'] = $tel;
        }

        return $data;
    }
}
