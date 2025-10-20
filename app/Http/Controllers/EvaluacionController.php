<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarEvaluacionRequest;
use App\Http\Requests\FinalizarEvaluacionRequest;
use App\Models\Evaluacion;
use App\Models\Inscrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluacionController extends Controller
{
    /**
     * GET /evaluaciones/asignadas
     * Lista de inscritos que el evaluador PUEDE evaluar (según sus asociaciones).
     * Incluye si ya tiene evaluación y su estado.
     */
    public function asignadas(Request $request)
    {
        /** @var \App\Models\Evaluador|null $evaluador */
        $evaluador = $request->input('evaluador'); // desde middleware auth.evaluador

        if (!$evaluador) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        try {
            $perPage = max(1, min((int)$request->get('per_page', 10), 100));
            $search  = trim((string)$request->get('search', ''));

            // Traemos las asociaciones del evaluador
            $asocs = $evaluador->asociaciones()->get(['area_id','nivel_id']);

            // Armamos un query de inscritos filtrados por la/s asociaciones
            $inscritos = Inscrito::query()
                ->when($search !== '', function($q) use ($search) {
                    $q->where(function($qq) use ($search) {
                        $qq->where('nombres','ilike',"%{$search}%")
                           ->orWhere('apellidos','ilike',"%{$search}%")
                           ->orWhere('documento','ilike',"%{$search}%");
                    });
                })
                ->where(function($q) use ($asocs) {
                    foreach ($asocs as $a) {
                        // Coincidencia por área y (si nivel_id no es null) también por nivel
                        $q->orWhere(function($qq) use ($a) {
                            $qq->where('area_id', $a->area_id);
                            if (!is_null($a->nivel_id)) {
                                $qq->where('nivel_id', $a->nivel_id);
                            }
                        });
                    }
                })
                ->with(['area','nivel']) // si tienes relaciones
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->paginate($perPage);

            // Adjuntamos estado de evaluación de este evaluador para cada inscrito
            $inscritos->getCollection()->transform(function($inscrito) use ($evaluador) {
                $eval = Evaluacion::where('inscrito_id', $inscrito->id)
                    ->where('evaluador_id', $evaluador->id)
                    ->first();

                $inscrito->evaluacion = $eval ? [
                    'id'          => $eval->id,
                    'estado'      => $eval->estado,
                    'nota_final'  => $eval->nota_final,
                    'concepto'    => $eval->concepto,
                    'finalizado_at' => $eval->finalizado_at,
                ] : null;

                return $inscrito;
            });

            return response()->json($inscritos, 200);

        } catch (Throwable $e) {
            Log::error('EVALUACIONES asignadas error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error al listar asignaciones.'], 500);
        }
    }

    /**
     * POST /evaluaciones/{inscrito}/guardar
     * Crea/actualiza evaluación en estado "borrador".
     */
    public function guardar(GuardarEvaluacionRequest $request, Inscrito $inscrito)
    {
        /** @var \App\Models\Evaluador|null $evaluador */
        $evaluador = $request->input('evaluador');

        if (!$evaluador) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // Autorización: ¿El evaluador está asignado al área/nivel del inscrito?
        if (!$this->evaluadorPuedeEvaluar($evaluador, $inscrito)) {
            return response()->json(['message' => 'No autorizado para evaluar este inscrito.'], 403);
        }

        $data = $request->validated();

        try {
            $evaluacion = DB::transaction(function () use ($evaluador, $inscrito, $data) {
                $eval = Evaluacion::firstOrNew([
                    'inscrito_id'  => $inscrito->id,
                    'evaluador_id' => $evaluador->id,
                ]);

                $eval->area_id  = $inscrito->area_id;
                $eval->nivel_id = $inscrito->nivel_id;

                // Notas parciales
                if (array_key_exists('notas', $data))       $eval->notas      = $data['notas'];
                if (array_key_exists('nota_final', $data))  $eval->nota_final = $data['nota_final'];
                if (array_key_exists('concepto', $data))    $eval->concepto   = $data['concepto'];
                if (array_key_exists('observaciones', $data)) $eval->observaciones = $data['observaciones'];

                // Siempre en borrador al "guardar"
                $eval->estado = 'borrador';
                $eval->finalizado_at = null;

                $eval->save();

                return $eval->fresh();
            });

            return response()->json(['message' => 'Guardado en borrador', 'data' => $evaluacion], 200);

        } catch (Throwable $e) {
            Log::error('EVALUACION guardar error', ['msg' => $e->getMessage()]);
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
                $eval = Evaluacion::firstOrNew([
                    'inscrito_id'  => $inscrito->id,
                    'evaluador_id' => $evaluador->id,
                ]);

                $eval->area_id      = $inscrito->area_id;
                $eval->nivel_id     = $inscrito->nivel_id;
                $eval->notas        = $data['notas'];
                $eval->nota_final   = $data['nota_final'];
                $eval->concepto     = $data['concepto'];
                $eval->observaciones= $data['observaciones'] ?? null;

                $eval->estado = 'finalizado';
                $eval->finalizado_at = now();

                $eval->save();

                return $eval->fresh();
            });

            return response()->json(['message' => 'Evaluación finalizada', 'data' => $evaluacion], 200);

        } catch (Throwable $e) {
            Log::error('EVALUACION finalizar error', ['msg' => $e->getMessage()]);
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
        $responsable = $request->input('responsable'); // desde middleware auth.responsable
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
            Log::error('EVALUACION reabrir error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'No se pudo reabrir la evaluación.'], 500);
        }
    }

    /* ======================
       Helpers de autorización
       ====================== */

    private function evaluadorPuedeEvaluar($evaluador, $inscrito): bool
    {
        // Si no hay asociaciones, no puede evaluar
        $asocs = $evaluador->asociaciones()->get(['area_id','nivel_id']);
        if ($asocs->isEmpty()) return false;

        foreach ($asocs as $a) {
            if ((int)$a->area_id === (int)$inscrito->area_id) {
                // Si el evaluador no tiene nivel (NULL), con solo área basta
                if (is_null($a->nivel_id) || (int)$a->nivel_id === (int)$inscrito->nivel_id) {
                    return true;
                }
            }
        }
        return false;
    }
}
