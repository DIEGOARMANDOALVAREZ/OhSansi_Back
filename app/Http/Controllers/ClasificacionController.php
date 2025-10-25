<?php
// app/Http/Controllers/ClasificacionController.php

namespace App\Http\Controllers;

use App\Http\Requests\ClasificacionPreviewRequest;
use App\Http\Requests\ClasificacionConfirmRequest;
use App\Models\CierreClasificacion;
use App\Models\Evaluacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Response;

class ClasificacionController extends Controller
{
    /**
     * Método interno reutilizable: calcula conteos y último cierre.
     *
     * @param  int|null    $areaId
     * @param  int|string|null $nivelId  (puede ser null)
     * @param  float       $minima
     * @return array{
     *   area_id:int|null,
     *   nivel_id:int|string|null,
     *   minima:float,
     *   conteos: array{clasificados:int,no_clasificados:int,desclasificados:int},
     *   confirmado: \App\Models\CierreClasificacion|null
     * }
     */
    private function makePreviewPayload($areaId, $nivelId, float $minima): array
    {
        $base = Evaluacion::query()
            ->where('estado', 'finalizado');

        if (!empty($areaId)) {
            $base->where('area_id', $areaId);
        }
        if ($nivelId !== null && $nivelId !== '') {
            $base->where('nivel_id', $nivelId);
        }

        // Desclasificados
        $desclasificados = (clone $base)
            ->where('concepto', 'DESCLASIFICADO')
            ->count();

        // Clasificados (APROBADO y nota >= mínima)
        $clasificados = (clone $base)
            ->where('concepto', 'APROBADO')
            ->where('nota_final', '>=', $minima)
            ->count();

        // No clasificados (no desclasif. y (DESAPROBADO o nota < mínima))
        $noClasificados = (clone $base)
            ->where('concepto', '!=', 'DESCLASIFICADO')
            ->where(function ($q) use ($minima) {
                $q->where('concepto', 'DESAPROBADO')
                  ->orWhere('nota_final', '<', $minima);
            })
            ->count();

        $ultimoCierre = CierreClasificacion::query()
            ->where('area_id', $areaId)
            ->where('nivel_id', $nivelId)
            ->orderByDesc('id')
            ->first();

        return [
            'area_id'  => $areaId,
            'nivel_id' => $nivelId,
            'minima'   => $minima,
            'conteos'  => [
                'clasificados'    => $clasificados,
                'no_clasificados' => $noClasificados,
                'desclasificados' => $desclasificados,
            ],
            'confirmado' => $ultimoCierre,
        ];
    }

    /** GET /responsable/clasificacion/preview?area_id&nivel_id&minima */
    public function preview(ClasificacionPreviewRequest $request)
    {
        $areaId  = $request->integer('area_id');
        $nivelId = $request->input('nivel_id'); // puede ser null
        $minima  = (float) $request->input('minima');

        $payload = $this->makePreviewPayload($areaId, $nivelId, $minima);

        return response()->json(['data' => $payload], 200);
    }

    /** POST /responsable/clasificacion/confirm */
    public function confirm(ClasificacionConfirmRequest $request)
    {
        /** @var \App\Models\Responsable|null $responsable */
        $responsable = $request->input('responsable'); // inyectado por middleware
        if (!$responsable) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // Usamos la misma lógica de preview con los parámetros ya validados
        $areaId  = $request->integer('area_id');
        $nivelId = $request->input('nivel_id');
        $minima  = (float) $request->input('minima');

        $preview = $this->makePreviewPayload($areaId, $nivelId, $minima);

        $payloadFirmado = [
            'area_id'  => $preview['area_id'],
            'nivel_id' => $preview['nivel_id'],
            'minima'   => $preview['minima'],
            'conteos'  => $preview['conteos'],
            'ts'       => now()->toIso8601String(),
        ];

        $hash = hash('sha256', json_encode($payloadFirmado));

        $cierre = CierreClasificacion::create([
            'area_id'               => $preview['area_id'],
            'nivel_id'              => $preview['nivel_id'],
            'minima'                => $preview['minima'],
            'responsable_id'        => $responsable->id,
            'total_clasificados'    => $preview['conteos']['clasificados'],
            'total_no_clasificados' => $preview['conteos']['no_clasificados'],
            'total_desclasificados' => $preview['conteos']['desclasificados'],
            'hash'                  => $hash,
            'confirmado_at'         => now(),
        ]);

        return response()->json(['message' => 'Clasificación confirmada', 'data' => $cierre], 200);
    }

    /** GET /responsable/clasificacion/export?area_id&nivel_id&minima */
    public function exportCsv(ClasificacionPreviewRequest $request)
    {
        $areaId  = $request->integer('area_id');
        $nivelId = $request->input('nivel_id');
        $minima  = (float) $request->input('minima');

        $q = Evaluacion::query()
            ->with(['inscrito:id,nombres,apellidos,documento'])
            ->where('estado','finalizado')
            ->where('concepto','APROBADO')
            ->where('nota_final','>=',$minima);

        if ($areaId)  $q->where('area_id',$areaId);
        if ($nivelId !== null && $nivelId !== '') $q->where('nivel_id',$nivelId);

        $rows = $q->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="clasificados.csv"',
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Documento','Apellidos','Nombres','Nota','Concepto']);
            foreach ($rows as $e) {
                fputcsv($out, [
                    $e->inscrito->documento ?? '',
                    $e->inscrito->apellidos ?? '',
                    $e->inscrito->nombres ?? '',
                    $e->nota_final ?? '',
                    $e->concepto ?? '',
                ]);
            }
            fclose($out);
        };

        return Response::stream($callback, 200, $headers);
    }
    

public function list(Request $request)
{
    // Validaciones básicas (puedes crear un FormRequest si gustas)
    $areaId   = $request->input('area_id');          // int|null
    $nivelId  = $request->input('nivel_id');         // int|null
    $minima   = (float) $request->input('minima');   // requerido en front
    $page     = max(1, (int) $request->input('page', 1));
    $perPage  = max(1, min(100, (int) $request->input('per_page', 15)));

    $q = \App\Models\Evaluacion::query()
        ->with(['inscrito:id,nombres,apellidos,documento'])
        ->where('estado', 'finalizado')
        ->where('concepto', 'APROBADO')
        ->where('nota_final', '>=', $minima);

    if ($areaId !== null && $areaId !== '') {
        $q->where('area_id', $areaId);
    }
    if ($nivelId !== null && $nivelId !== '') {
        $q->where('nivel_id', $nivelId);
    }

    $q->orderByDesc('nota_final')
      ->orderBy('id');

    $paginator = $q->paginate($perPage, ['*'], 'page', $page);

    // Normaliza la forma de salida para el front
    $paginator->getCollection()->transform(function ($e) {
        return [
            'id'         => $e->id, // o $e->inscrito_id si prefieres
            'inscrito'   => [
                'documento' => $e->inscrito->documento ?? null,
                'apellidos' => $e->inscrito->apellidos ?? '',
                'nombres'   => $e->inscrito->nombres ?? '',
            ],
            'nota_final' => (float) $e->nota_final,
            'concepto'   => (string) $e->concepto,
        ];
    });

    return response()->json($paginator, 200);
}

}
