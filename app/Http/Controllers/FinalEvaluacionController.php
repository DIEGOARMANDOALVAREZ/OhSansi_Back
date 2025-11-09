<?php
// app/Http/Controllers/FinalEvaluacionController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Finalista;
use App\Models\EvaluacionFinal;
use App\Models\Audit;

class FinalEvaluacionController extends Controller
{
    // ========================
    // Evaluador: listado asignado
    // ========================
    public function asignadas(Request $request)
    {
        $evaluador = $request->get('evaluador'); // inyectado por AuthEvaluador
        if (!$evaluador) return response()->json(['message'=>'No autenticado'], 401);

        // áreas/niveles del evaluador
        $pairs = collect($evaluador->asociaciones ?? [])->map(fn($a)=>[
            'area_id'=> (int)$a['area_id'],
            'nivel_id'=> isset($a['nivel_id']) ? (int)$a['nivel_id'] : null
        ]);

        $q = Finalista::query()
            ->with(['inscrito:id,apellidos,nombres,area_id,nivel_id'])
            ->when($pairs->isNotEmpty(), function($qq) use ($pairs) {
                $qq->where(function($w) use ($pairs){
                    foreach ($pairs as $p) {
                        $w->orWhere(function($c) use ($p) {
                            $c->where('area_id', $p['area_id']);
                            if (!is_null($p['nivel_id'])) $c->where('nivel_id', $p['nivel_id']);
                        });
                    }
                });
            })
            ->orderBy('habilitado_at','desc');

        // Traer estado si ya hay evaluación final
        $data = $q->paginate(20);
        $finalEvalByFinalista = EvaluacionFinal::whereIn('finalista_id', collect($data->items())->pluck('id'))
                                ->get()->keyBy('finalista_id');

        $data->getCollection()->transform(function($f) use ($finalEvalByFinalista) {
            $eval = $finalEvalByFinalista->get($f->id);
            return [
                'id' => $f->id,
                'inscrito' => $f->inscrito ? [
                    'id' => $f->inscrito->id,
                    'apellidos' => $f->inscrito->apellidos,
                    'nombres' => $f->inscrito->nombres,
                    'area_id' => $f->inscrito->area_id,
                    'nivel_id' => $f->inscrito->nivel_id,
                ] : null,
                'area_id' => $f->area_id,
                'nivel_id' => $f->nivel_id,
                'habilitado_at' => $f->habilitado_at,
                'estado' => $eval?->estado ?? 'EN_EDICION',
                'nota_final' => $eval?->nota_final,
                'notas' => $eval?->notas,
            ];
        });

        return response()->json($data, 200);
    }

    // ========================
    // Evaluador: guardar notas (en edición)
    // ========================
    public function guardar(Request $request, Finalista $finalista)
    {
        $evaluador = $request->get('evaluador');
        if (!$evaluador) return response()->json(['message'=>'No autenticado'], 401);

        // check acceso por asociaciones
        if (!$this->evaluadorPuedeFinalista($evaluador, $finalista)) {
            return response()->json(['message'=>'Acceso denegado a este finalista'], 403);
        }

        $payload = $request->validate([
            'notas' => ['required','array'], // tu estructura libre
            'nota_final' => ['required','numeric','between:0,100'],
            'concepto' => ['nullable','string','max:32'], // opcional
        ]);

        // 2 decimales exactos
        $payload['nota_final'] = round((float)$payload['nota_final'], 2);

        $row = EvaluacionFinal::firstOrNew([
            'finalista_id' => $finalista->id,
            'evaluador_id' => $evaluador->id,
        ]);

        if ($row->estado === 'FINALIZADA') {
            return response()->json(['message' => 'Registro finalizado; no editable.'], 409);
        }

        $row->fill([
            'area_id' => $finalista->area_id,
            'nivel_id'=> $finalista->nivel_id,
            'notas' => $payload['notas'],
            'nota_final' => $payload['nota_final'],
            'concepto' => $payload['concepto'] ?? 'CALIFICADO',
            'estado' => 'EN_EDICION',
        ]);

        $row->save();

        try { Audit::log($evaluador->id ?? 0, 'EvaluacionFinal', $row->id, 'GUARDAR', $row->toArray()); } catch (\Throwable) {}

        return response()->json(['message'=>'Guardado','data'=>$row], 200);
    }

    // ========================
    // Evaluador: finalizar registro
    // ========================
    public function finalizar(Request $request, Finalista $finalista)
    {
        $evaluador = $request->get('evaluador');
        if (!$evaluador) return response()->json(['message'=>'No autenticado'], 401);

        if (!$this->evaluadorPuedeFinalista($evaluador, $finalista)) {
            return response()->json(['message'=>'Acceso denegado a este finalista'], 403);
        }

        $row = EvaluacionFinal::where('finalista_id',$finalista->id)
                ->where('evaluador_id',$evaluador->id)
                ->first();

        if (!$row) {
            return response()->json(['message'=>'No hay datos para finalizar. Guarda primero.'], 422);
        }

        if (!is_numeric($row->nota_final) || $row->nota_final < 0 || $row->nota_final > 100) {
            return response()->json(['message'=>'Nota final inválida (0–100).'], 422);
        }

        if ($row->estado === 'FINALIZADA') {
            return response()->json(['message'=>'Ya finalizada.'], 200);
        }

        $row->estado = 'FINALIZADA';
        $row->finalizado_at = now();
        $row->save();

        try { Audit::log($evaluador->id ?? 0, 'EvaluacionFinal', $row->id, 'FINALIZAR', ['nota_final'=>$row->nota_final]); } catch (\Throwable) {}

        return response()->json(['message'=>'Finalizada','data'=>$row], 200);
    }

    // ========================
    // Responsable: ranking preliminar (no publicar)
    // ========================
    public function ranking(Request $request)
    {
        $responsable = $request->get('responsable');
        if (!$responsable) return response()->json(['message'=>'No autenticado'], 401);

        $data = $request->validate([
            'area_id'  => ['nullable','integer'],
            'nivel_id' => ['nullable','integer'],
            'limit'    => ['nullable','integer','min:1','max:1000'],
        ]);
        $limit = $data['limit'] ?? 200;

        // Trae FINALIZADAS y calcula ranking
        $q = EvaluacionFinal::query()
            ->with(['finalista.inscrito:id,apellidos,nombres'])
            ->where('estado','FINALIZADA')
            ->when($data['area_id'] ?? null, fn($qq,$a)=>$qq->where('area_id',$a))
            ->when($data['nivel_id'] ?? null, fn($qq,$n)=>$qq->where('nivel_id',$n))
            ->orderByDesc('nota_final')
            ->limit($limit);

        $rows = $q->get()->map(function($r){
            return [
                'finalista_id' => $r->finalista_id,
                'inscrito' => $r->finalista?->inscrito ? [
                    'apellidos' => $r->finalista->inscrito->apellidos,
                    'nombres'   => $r->finalista->inscrito->nombres,
                ] : null,
                'nota_final' => (float)$r->nota_final,
                'concepto'   => $r->concepto,
                'finalizado_at' => optional($r->finalizado_at)->toDateTimeString(),
            ];
        });

        return response()->json([
            'meta' => ['area_id'=>$data['area_id']??null,'nivel_id'=>$data['nivel_id']??null],
            'total' => $rows->count(),
            'data' => $rows,
        ], 200);
    }

    // ========================
    // Responsable: reabrir (con motivo)
    // ========================
    public function reabrir(Request $request, Finalista $finalista)
    {
        $responsable = $request->get('responsable');
        if (!$responsable) return response()->json(['message'=>'No autenticado'], 401);

        $payload = $request->validate([
            'motivo' => ['required','string','max:300'],
        ]);

        $row = EvaluacionFinal::where('finalista_id',$finalista->id)->first();
        if (!$row) return response()->json(['message'=>'No existe evaluación final para este finalista.'], 404);

        if ($row->estado !== 'FINALIZADA') {
            return response()->json(['message'=>'La evaluación no está finalizada.'], 409);
        }

        $row->estado = 'EN_EDICION';
        $row->finalizado_at = null;
        $row->save();

        try {
            Audit::log($responsable->id ?? 0, 'EvaluacionFinal', $row->id, 'REABRIR', [
                'motivo' => $payload['motivo']
            ]);
        } catch (\Throwable) {}

        return response()->json(['message'=>'Reabierta','data'=>$row], 200);
    }

    // ========================
    // Helper: acceso evaluador a finalista
    // ========================
    private function evaluadorPuedeFinalista($evaluador, Finalista $f): bool
    {
        $areas = collect($evaluador->asociaciones ?? []);
        if ($areas->isEmpty()) return false;

        return $areas->contains(function($a) use ($f) {
            $okArea = ((int)$a['area_id']) === (int)$f->area_id;
            $niv = $a['nivel_id'] ?? null;
            return $okArea && (is_null($niv) || (int)$niv === (int)$f->nivel_id);
        });
    }
}
