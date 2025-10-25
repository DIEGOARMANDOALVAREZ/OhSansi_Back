<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarEvaluacionRequest;
use App\Http\Requests\FinalizarEvaluacionRequest;
use App\Models\Evaluacion;
use App\Models\Inscrito;
use App\Models\Area;
use App\Models\Nivel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluacionController extends Controller
{
    /**
     * GET /evaluaciones/asignadas
     * Lista de inscritos que el evaluador puede evaluar según sus asociaciones (área/nivel).
     * NOTA: En tu modelo Inscrito, área/nivel son STRINGS (no FKs).
     */
    public function asignadas(Request $request)
    {
        /** @var \App\Models\Evaluador|null $evaluador */
        $evaluador = $request->input('evaluador'); // inyectado por middleware auth.evaluador
        if (!$evaluador) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        try {
            $perPage = max(1, min((int)$request->get('per_page', 10), 100));
            $search  = trim((string)$request->get('search', ''));

            // Asociaciones: áreas del evaluador + nivel_id opcional (desde pivot evaluador_area)
            $asocs = $evaluador->asociaciones()
                ->select('areas.id as area_id', 'areas.nombre as area_nombre', 'evaluador_area.nivel_id')
                ->get();

            // Si no tiene asociaciones → paginado vacío
            if ($asocs->isEmpty()) {
                $empty = Inscrito::query()
                    ->whereRaw(DB::getDriverName() === 'pgsql' ? 'true = false' : '1 = 0')
                    ->paginate($perPage);
                return response()->json($empty, 200);
            }

            // Mapa nivel_id => nombre para cotejar contra el string de Inscrito.nivel
            $nivelIds = $asocs->pluck('nivel_id')->filter()->unique()->values();
            $nivelesById = $nivelIds->isNotEmpty()
                ? Nivel::whereIn('id', $nivelIds)->pluck('nombre', 'id')
                : collect();

            // Base query de inscritos (filtros por texto en nombres/apellidos/documento)
            $q = Inscrito::query();
            if ($search !== '') {
                $s = mb_strtolower($search);
                $q->where(function ($qq) use ($s) {
                    $qq->whereRaw('LOWER(nombres)   LIKE ?', ["%{$s}%"])
                       ->orWhereRaw('LOWER(apellidos) LIKE ?', ["%{$s}%"])
                       ->orWhereRaw('LOWER(documento) LIKE ?', ["%{$s}%"]);
                });
            }

            // Filtro por asociaciones (comparación por NOMBRE de área/nivel, case-insensitive)
            $q->where(function ($qq) use ($asocs, $nivelesById) {
                foreach ($asocs as $a) {
                    $areaNombre = (string) $a->area_nombre;
                    $nivelId    = $a->nivel_id;

                    $qq->orWhere(function ($or) use ($areaNombre, $nivelId, $nivelesById) {
                        $or->whereRaw('LOWER(area) = ?', [mb_strtolower($areaNombre)]);

                        if (!is_null($nivelId)) {
                            $nivelNombre = (string) ($nivelesById[$nivelId] ?? '');
                            if ($nivelNombre !== '') {
                                $or->whereRaw('LOWER(nivel) = ?', [mb_strtolower($nivelNombre)]);
                            } else {
                                // Si no se puede resolver el nombre del nivel, forzamos falso
                                $or->whereRaw(DB::getDriverName() === 'pgsql' ? 'true = false' : '1 = 0');
                            }
                        }
                    });
                }
            });

            $paginator = $q
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->paginate($perPage);

            // Transformamos cada inscrito para adjuntar evaluación y objetos area/nivel {id?, nombre}
            $paginator->getCollection()->transform(function ($i) use ($evaluador) {
                $eval = Evaluacion::where('inscrito_id', $i->id)
                    ->where('evaluador_id', $evaluador->id)
                    ->first();

                $areaId  = $this->resolverAreaIdPorNombre($i->area);
                $nivelId = $this->resolverNivelIdPorNombre($i->nivel);

                return [
                    'id'        => $i->id,
                    'nombres'   => $i->nombres,
                    'apellidos' => $i->apellidos,
                    'documento' => $i->documento,

                    // Enviamos objetos area/nivel con nombre (y id si existe en catálogo)
                    'area'      => $i->area ? ['id' => $areaId,  'nombre' => $i->area] : null,
                    'nivel'     => $i->nivel ? ['id' => $nivelId, 'nombre' => $i->nivel] : null,

                    'evaluacion' => $eval ? [
                        'id'            => $eval->id,
                        'estado'        => $eval->estado,
                        'nota_final'    => $eval->nota_final,
                        'concepto'      => $eval->concepto,
                        'finalizado_at' => $eval->finalizado_at,
                    ] : null,
                ];
            });

            return response()->json($paginator, 200);
        } catch (Throwable $e) {
            Log::error('EVALUACIONES asignadas error', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['message' => 'Error al listar asignaciones.'], 500);
        }
    }

    /**
     * POST /evaluaciones/{inscrito}/guardar
     * Crea/actualiza evaluación en estado "borrador".
     * Resolvemos area_id/nivel_id por nombre (si existen en catálogos).
     */
    public function guardar(GuardarEvaluacionRequest $request, Inscrito $inscrito)
    {
        /** @var \App\Models\Evaluador|null $evaluador */
        $evaluador = $request->input('evaluador');
        if (!$evaluador) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (!$this->evaluadorPuedeEvaluar($evaluador, $inscrito)) {
            return response()->json(['message' => 'No autorizado para evaluar este inscrito.'], 403);
        }

        $data = $request->validated();

        try {
            $evaluacion = DB::transaction(function () use ($evaluador, $inscrito, $data) {
                $areaId  = $this->resolverAreaIdPorNombre($inscrito->area);
                $nivelId = $this->resolverNivelIdPorNombre($inscrito->nivel);

                $eval = Evaluacion::firstOrNew([
                    'inscrito_id'  => $inscrito->id,
                    'evaluador_id' => $evaluador->id,
                ]);

                $eval->area_id       = $areaId;
                $eval->nivel_id      = $nivelId;
                if (array_key_exists('notas', $data))         $eval->notas         = $data['notas'];
                if (array_key_exists('nota_final', $data))    $eval->nota_final    = $data['nota_final'];
                if (array_key_exists('concepto', $data))      $eval->concepto      = $data['concepto'];
                if (array_key_exists('observaciones', $data)) $eval->observaciones = $data['observaciones'];

                $eval->estado = 'borrador';
                $eval->finalizado_at = null;
                $eval->save();

                return $eval->fresh();
            });

            return response()->json(['message' => 'Guardado en borrador', 'data' => $evaluacion], 200);
        } catch (Throwable $e) {
            Log::error('EVALUACION guardar error', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['message' => 'No se pudo guardar la evaluación.'], 500);
        }
    }

    /**
     * POST /evaluaciones/{inscrito}/finalizar
     * Valida y marca evaluación como "finalizado".
     */
    public function finalizar(FinalizarEvaluacionRequest $request, Inscrito $inscrito)
    {
        /** @var \App\Models\Evaluador|null $evaluador */
        $evaluador = $request->input('evaluador');
        if (!$evaluador) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (!$this->evaluadorPuedeEvaluar($evaluador, $inscrito)) {
            return response()->json(['message' => 'No autorizado para evaluar este inscrito.'], 403);
        }

        $data = $request->validated();

        try {
            $evaluacion = DB::transaction(function () use ($evaluador, $inscrito, $data) {
                $areaId  = $this->resolverAreaIdPorNombre($inscrito->area);
                $nivelId = $this->resolverNivelIdPorNombre($inscrito->nivel);

                $eval = Evaluacion::firstOrNew([
                    'inscrito_id'  => $inscrito->id,
                    'evaluador_id' => $evaluador->id,
                ]);

                $eval->area_id       = $areaId;
                $eval->nivel_id      = $nivelId;
                $eval->notas         = $data['notas'];
                $eval->nota_final    = $data['nota_final'];
                $eval->concepto      = $data['concepto'];
                $eval->observaciones = $data['observaciones'] ?? null;

                $eval->estado = 'finalizado';
                $eval->finalizado_at = now();
                $eval->save();

                return $eval->fresh();
            });

            return response()->json(['message' => 'Evaluación finalizada', 'data' => $evaluacion], 200);
        } catch (Throwable $e) {
            Log::error('EVALUACION finalizar error', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['message' => 'No se pudo finalizar la evaluación.'], 500);
        }
    }

    /**
     * POST /evaluaciones/{inscrito}/reabrir  (Solo RESPONSABLE)
     * Cambia estado a "borrador".
     */
    public function reabrir(Request $request, Inscrito $inscrito)
    {
        /** @var \App\Models\Responsable|null $responsable */
        $responsable = $request->input('responsable'); // middleware auth.responsable
        if (!$responsable) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        try {
            $eval = Evaluacion::where('inscrito_id', $inscrito->id)->first();
            if (!$eval) {
                return response()->json(['message' => 'No existe evaluación para reabrir.'], 404);
            }

            $eval->estado = 'borrador';
            $eval->finalizado_at = null;
            $eval->save();

            return response()->json(['message' => 'Evaluación reabierta', 'data' => $eval], 200);
        } catch (Throwable $e) {
            Log::error('EVALUACION reabrir error', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['message' => 'No se pudo reabrir la evaluación.'], 500);
        }
    }

    /* ======================
       Helpers
       ====================== */

    /**
     * Autoriza si el evaluador puede evaluar al inscrito:
     * - Coincide por NOMBRE de área (Inscrito.area) con algún área asignada al evaluador.
     * - Si la asignación tiene nivel específico, validamos NOMBRE de nivel (Inscrito.nivel).
     */
    private function evaluadorPuedeEvaluar($evaluador, Inscrito $inscrito): bool
    {
        $asocs = $evaluador->asociaciones()
            ->select('areas.id as area_id', 'areas.nombre as area_nombre', 'evaluador_area.nivel_id')
            ->get();

        if ($asocs->isEmpty()) return false;

        $nivelIds = $asocs->pluck('nivel_id')->filter()->unique()->values();
        $nivelesById = $nivelIds->isNotEmpty()
            ? Nivel::whereIn('id', $nivelIds)->pluck('nombre', 'id')
            : collect();

        foreach ($asocs as $a) {
            if (strcasecmp((string)$a->area_nombre, (string)$inscrito->area) === 0) {
                if (is_null($a->nivel_id)) {
                    return true; // sin nivel en la asociación
                }
                $nivelNombre = (string) ($nivelesById[$a->nivel_id] ?? '');
                if ($nivelNombre !== '' && strcasecmp($nivelNombre, (string)$inscrito->nivel) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Resuelve Area.id por nombre (case-insensitive); si no existe retorna null. */
    private function resolverAreaIdPorNombre(?string $nombre): ?int
    {
        if (!$nombre) return null;
        $row = Area::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first(['id']);
        return $row?->id;
    }

    /** Resuelve Nivel.id por nombre (case-insensitive); si no existe retorna null. */
    private function resolverNivelIdPorNombre(?string $nombre): ?int
    {
        if (!$nombre) return null;
        $row = Nivel::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first(['id']);
        return $row?->id;
    }
}
