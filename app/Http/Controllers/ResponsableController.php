<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // ✅ ← ESTA ES LA CLAVE
use Illuminate\Database\QueryException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use App\Models\Responsable;
use App\Models\Inscrito;
use App\Models\Audit;
use App\Http\Requests\StoreResponsableRequest;
use App\Http\Requests\UpdateResponsableRequest;



class ResponsableController extends Controller
{
    // ==========================================================
    // 📋 LISTAR (filtros + búsqueda + paginación)
    // ==========================================================
    public function index(Request $request)
    {
        try {
            $query = Responsable::query()->with(['area', 'nivel']);

            if ($request->filled('search')) {
                $s = trim((string) $request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('nombres', 'ilike', "%{$s}%")
                      ->orWhere('apellidos', 'ilike', "%{$s}%")
                      ->orWhere('correo', 'ilike', "%{$s}%");
                });
            }

            if ($request->filled('area_id')) $query->where('area_id', (int) $request->area_id);
            if ($request->filled('nivel_id')) $query->where('nivel_id', (int) $request->nivel_id);

            if ($request->filled('estado')) {
                $estado = $this->parseEstado($request->estado);
                if ($estado !== null) $query->where('activo', $estado);
            }

            $perPage = max(1, (int) $request->get('per_page', 10));
            return response()->json(
                $query->orderBy('apellidos')->orderBy('nombres')->paginate($perPage),
                200
            );

        } catch (Throwable $e) {
            Log::error('RESPONSABLES index error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error al listar responsables.'], 500);
        }
    }

    // ==========================================================
    // 🔎 DETALLE
    // ==========================================================
    public function show(Responsable $responsable)
    {
        try {
            $responsable->load(['area', 'nivel']);
            return response()->json($responsable);
        } catch (Throwable $e) {
            Log::error('RESPONSABLE show error', ['id' => $responsable->id, 'msg' => $e->getMessage()]);
            return response()->json($responsable);
        }
    }

    // ==========================================================
    // 🟢 CREAR
    // ==========================================================
    public function store(StoreResponsableRequest $req)
    {
        $data = $this->normalize($req->validated());

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
            $r = DB::transaction(fn () => Responsable::create($data));
            try { Audit::log(Auth::id(), 'Responsable', $r->id, 'CREAR', $r->toArray()); } catch (Throwable) {}
            $r->load(['area', 'nivel']);
            return response()->json(['message' => 'Creado', 'data' => $r], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('RESPONSABLE store error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error al crear responsable.'], 500);
        }
    }

    // ==========================================================
    // ✏️ ACTUALIZAR
    // ==========================================================
    public function update(UpdateResponsableRequest $req, Responsable $responsable)
    {
        $before = $responsable->toArray();
        $data   = $this->normalize($req->validated());

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
            DB::transaction(fn () => $responsable->update($data));
            try { Audit::log(Auth::id(), 'Responsable', $responsable->id, 'EDITAR', ['before' => $before, 'after' => $responsable->fresh()->toArray()]); } catch (Throwable) {}

            return response()->json(['message' => 'Actualizado', 'data' => $responsable->fresh(['area','nivel'])]);

        } catch (Throwable $e) {
            Log::error('RESPONSABLE update error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error al actualizar responsable.'], 500);
        }
    }

    // ==========================================================
    // 🗑️ ELIMINAR / INACTIVAR
    // ==========================================================
    public function destroy(Request $request, Responsable $responsable)
    {
        $before = $responsable->toArray();

        try {
            if ($request->boolean('hard')) {
                $responsable->forceDelete();
                try { Audit::log(Auth::id(), 'Responsable', $before['id'] ?? null, 'ELIMINAR', ['before' => $before]); } catch (Throwable) {}
                return response()->json(['message' => 'Responsable eliminado definitivamente']);
            }

            DB::transaction(fn () => $responsable->update(['activo' => false]));
            try { Audit::log(Auth::id(), 'Responsable', $responsable->id, 'EDITAR', ['before' => $before, 'after' => $responsable->toArray()]); } catch (Throwable) {}

            return response()->json(['message' => 'Responsable inactivado']);

        } catch (Throwable $e) {
            Log::error('RESPONSABLE destroy error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error al eliminar responsable.'], 500);
        }
    }

    // ==========================================================
    // 📊 PANEL Y LISTA DE COMPETIDORES (HU4)
    // ==========================================================
    public function panel(Request $request)
    {
        $responsable = $request->get('responsable');

        return response()->json([
            'ok' => true,
            'responsable' => $responsable,
            'resumen' => [
                'total_competidores' => Inscrito::where('area', $responsable->area)->count(),
                'total_niveles'      => Inscrito::where('area', $responsable->area)->distinct('nivel')->count('nivel'),
            ],
        ]);
    }

    public function listaCompetidores(\Illuminate\Http\Request $request)
{
    try {
        /** @var \App\Models\Responsable|null $responsable */
        $responsable = $request->input('responsable'); // lo mete el middleware
        if (!$responsable) {
            return response()->json(['message' => 'Responsable no autenticado.'], 401);
        }

        // ⚠️ Modo ALL (para verificar rápidamente si hay datos)
        if ($request->boolean('all')) {
            $all = \App\Models\Inscrito::query()
                ->orderBy('nombres')
                ->get(['id','documento','nombres','apellidos','unidad','area','nivel']);
            return response()->json([
                '_debug' => ['mode' => 'all'],
                'data'   => $all,
            ]);
        }

        // 1️⃣ Obtener área/nivel base del responsable
        $areaNombre  = $responsable->getAttribute('area');
        $nivelNombre = $responsable->getAttribute('nivel');

        if (!$areaNombre && method_exists($responsable, 'area')) {
            try { $areaNombre = optional($responsable->area)->nombre; } catch (\Throwable $e) {}
        }
        if (!$nivelNombre && method_exists($responsable, 'nivel')) {
            try { $nivelNombre = optional($responsable->nivel)->nombre; } catch (\Throwable $e) {}
        }

        // 2️⃣ Aplicar overrides si vienen filtros desde el front
        $areaFiltro  = $request->query('area');
        $nivelFiltro = $request->query('nivel');
        $unidadFiltro = $request->query('unidad');

        if (!empty($areaFiltro))  $areaNombre  = $areaFiltro;
        if (!empty($nivelFiltro)) $nivelNombre = $nivelFiltro;

        // 3️⃣ Construir query
        $q = \App\Models\Inscrito::query();

        if (is_string($areaNombre) && trim($areaNombre) !== '') {
            $q->whereRaw('LOWER(area) = LOWER(?)', [trim($areaNombre)]);
        }

        if (is_string($nivelNombre) && trim($nivelNombre) !== '') {
            $q->whereRaw('LOWER(nivel) = LOWER(?)', [trim($nivelNombre)]);
        }

        if (!empty($unidadFiltro)) {
            $q->where('unidad', 'ILIKE', '%'.trim($unidadFiltro).'%');
        }

        // 4️⃣ Ejecutar query
        $inscritos = $q->orderBy('nombres')
            ->get(['id','documento','nombres','apellidos','unidad','area','nivel']);

        // 5️⃣ Debug opcional
        if ($request->boolean('debug')) {
            return response()->json([
                '_debug' => [
                    'areaResponsable'  => $responsable->area ?? null,
                    'nivelResponsable' => $responsable->nivel ?? null,
                    'areaUsada'        => $areaNombre,
                    'nivelUsado'       => $nivelNombre,
                    'unidadFiltro'     => $unidadFiltro,
                    'total'            => $inscritos->count(),
                ],
                'data' => $inscritos,
            ]);
        }

        return response()->json($inscritos, 200);

    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('listaCompetidores ERROR', [
            'msg'  => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        return response()->json(['message' => 'Error interno al listar competidores'], 500);
    }
}

// GET /responsable/opciones-filtros
public function opcionesFiltros(\Illuminate\Http\Request $request)
{
    try {
        /** @var \App\Models\Responsable|null $responsable */
        $responsable = $request->input('responsable'); // del middleware
        $scope = $request->query('scope', 'all'); // 'all' | 'mine'

        // Por defecto devolvemos TODAS las opciones existentes en la tabla Inscrito
        // (así los select no colapsan al filtrar).
        $base = \App\Models\Inscrito::query();

        if ($scope === 'mine' && $responsable) {
            // Si quieres limitar a los del responsable (opcional)
            $areaNombre  = $responsable->getAttribute('area')  ?? optional($responsable->area)->nombre;
            $nivelNombre = $responsable->getAttribute('nivel') ?? optional($responsable->nivel)->nombre;

            if (is_string($areaNombre) && trim($areaNombre) !== '') {
                $base->whereRaw('LOWER(area) = LOWER(?)', [trim($areaNombre)]);
            }
            if (is_string($nivelNombre) && trim($nivelNombre) !== '') {
                $base->whereRaw('LOWER(nivel) = LOWER(?)', [trim($nivelNombre)]);
            }
        }

        // Sacamos catálogos globales (o acotados si scope=mine)
        $areas = (clone $base)->select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        $niveles = (clone $base)->select('nivel')
            ->whereNotNull('nivel')
            ->distinct()
            ->orderBy('nivel')
            ->pluck('nivel');

        // Si no quieres combo de unidad, quítalo. Útil por autocomplete.
        $unidades = (clone $base)->select('unidad')
            ->whereNotNull('unidad')
            ->distinct()
            ->orderBy('unidad')
            ->limit(500) // por si hay demasiadas
            ->pluck('unidad');

        return response()->json([
            'areas'    => $areas,
            'niveles'  => $niveles,
            'unidades' => $unidades,
        ], 200);

    } catch (Throwable $e) {
        Log::error('opcionesFiltros ERROR', [
            'msg'  => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        return response()->json(['message' => 'Error interno al obtener filtros'], 500);
    }
}



    // ==========================================================
    // 🔧 HELPERS
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
        if (isset($data['area_id'])) $data['area_id'] = (int) $data['area_id'];
        if (array_key_exists('nivel_id', $data))
            $data['nivel_id'] = ($data['nivel_id'] === null || $data['nivel_id'] === '') ? null : (int) $data['nivel_id'];
        if (isset($data['activo'])) $data['activo'] = (bool) $data['activo'];

        if (array_key_exists('telefono', $data)) {
            $tel = trim((string) ($data['telefono'] ?? ''));
            $tel = preg_replace('/[^\d+]/', '', $tel) ?: null;
            $data['telefono'] = $tel;
        }

        return $data;
    }
}
