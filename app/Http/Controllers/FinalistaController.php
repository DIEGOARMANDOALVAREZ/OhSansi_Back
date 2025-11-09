<?php
// app/Http/Controllers/FinalistaController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\Inscrito;
use App\Models\Evaluacion;
use App\Models\Finalista;
use App\Models\FinalSnapshot;
use App\Models\Audit;

use Throwable;

class FinalistaController extends Controller
{
    /**
     * GET /responsable/fase-final/listado
     * Lista paginada de finalistas (ya promovidos a fase final).
     * Enriquecido con area_nombre y nivel_nombre para evitar que el front
     * tenga que pedir catálogos protegidos por Sanctum.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $q = Finalista::query()
            ->with([
                'inscrito:id,apellidos,nombres,area_id,nivel_id',
                'areaRef:id,nombre',
                'nivelRef:id,nombre',
            ])
            ->when($request->filled('area_id'),  fn($qq) => $qq->where('area_id',  (int)$request->area_id))
            ->when($request->filled('nivel_id'), fn($qq) => $qq->where('nivel_id', (int)$request->nivel_id))
            ->orderByDesc('habilitado_at');

        $paginator = $q->paginate($perPage);

        // Transformar para exponer nombres amigables
        $paginator->getCollection()->transform(function (Finalista $f) {
            $row = [
                'id'            => $f->id,
                'inscrito'      => $f->relationLoaded('inscrito') && $f->inscrito ? [
                    'id'         => $f->inscrito->id,
                    'apellidos'  => $f->inscrito->apellidos,
                    'nombres'    => $f->inscrito->nombres,
                    'area_id'    => $f->inscrito->area_id,
                    'nivel_id'   => $f->inscrito->nivel_id,
                ] : null,
                'area_id'       => $f->area_id,
                'nivel_id'      => $f->nivel_id,
                'habilitado_at' => $f->habilitado_at,
                'origen_hash'   => $f->origen_hash,
            ];

            // Extras “bonitos” para el front sin catálogos
            $row['area_nombre']  = $f->relationLoaded('areaRef')  && $f->areaRef  ? $f->areaRef->nombre  : null;
            $row['nivel_nombre'] = $f->relationLoaded('nivelRef') && $f->nivelRef ? $f->nivelRef->nombre : null;

            return $row;
        });

        return response()->json($paginator, 200);
    }

    /**
     * GET /responsable/fase-final/snapshots
     * Historial de snapshots/auditoría de promociones.
     */
    public function snapshots(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        return response()->json(
            FinalSnapshot::query()
                ->orderByDesc('creado_at')
                ->paginate($perPage),
            200
        );
    }

    /**
     * POST /responsable/fase-final/promover-por-filtro
     * Promueve a fase final a inscritos con Evaluaciones finalizadas y Aprobadas,
     * opcionalmente filtrando por área y/o nivel.
     *
     * Criterio de “confirmados”: concepto='APROBADO' y finalizado_at != null
     * (Si tienes un estado adicional, añade ->where('estado','CERRADA') u otro valor).
     */
    public function promoverPorFiltro(Request $request)
    {
        $data = $request->validate([
            'area_id'  => ['nullable', 'integer'],
            'nivel_id' => ['nullable', 'integer'],
        ]);

        // IDs de inscritos que cumplen el criterio de “clasificados confirmados”
        $inscritoIds = Evaluacion::query()
            ->select('inscrito_id')
            ->where('concepto', 'APROBADO')
            ->whereNotNull('finalizado_at')
            // ->where('estado', 'CERRADA') // descomenta si manejas estado de cierre
            ->when($data['area_id'] ?? null,  fn($q, $a) => $q->where('area_id', $a))
            ->when($data['nivel_id'] ?? null, fn($q, $n) => $q->where('nivel_id', $n))
            ->groupBy('inscrito_id')
            ->pluck('inscrito_id');

        $origen     = 'FILTRO';
        $origenHash = hash('sha256', 'FILTRO:' . json_encode($data));

        return $this->promoverCore($request, $origen, $origenHash, $inscritoIds, $data);
    }

    /**
     * Núcleo de promoción + snapshot + auditoría (idempotente por $origenHash).
     *
     * @param Request $request
     * @param string  $origen       Ej: 'FILTRO'
     * @param string  $origenHash   Hash único del lote (idempotencia)
     * @param mixed   $inscritoIds  Colección/array de IDs a promover
     * @param array   $meta         Metadatos (area_id?, nivel_id?)
     */
    private function promoverCore(Request $request, string $origen, string $origenHash, $inscritoIds, array $meta)
    {
        try {
            // El middleware AuthResponsable inyecta el modelo en $request->responsable
            $responsable = $request->get('responsable');
            $actorId     = (int)($responsable->id ?? 0); // siempre int

            // Idempotencia: si ya existe snapshot con este hash, no duplicar
            $existe = FinalSnapshot::where('origen_hash', $origenHash)->first();
            if ($existe) {
                return response()->json([
                    'message'  => 'Ya preparado (idempotente).',
                    'snapshot' => $existe,
                ], 200);
            }

            $ids = collect($inscritoIds)->unique()->values();
            if ($ids->isEmpty()) {
                return response()->json(['message' => 'No hay clasificados para promover.'], 422);
            }

            $ahora = now();

            // Traer datos mínimos de los inscritos para fijar área/nivel en finalistas
            $inscritos = Inscrito::whereIn('id', $ids)->get(['id', 'nivel_id', 'area_id']);

            DB::transaction(function () use ($inscritos, $origenHash, $ahora, $meta, $actorId, $origen, $ids) {
                foreach ($inscritos as $i) {
                    Finalista::updateOrCreate(
                        ['inscrito_id' => $i->id, 'origen_hash' => $origenHash],
                        [
                            'habilitado_at' => $ahora,
                            'nivel_id'      => $i->nivel_id,                    // desde Inscrito
                            'area_id'       => $meta['area_id'] ?? $i->area_id, // meta si viene, sino fallback
                            'cierre_id'     => null,                            // no manejamos “cierre” aquí
                        ]
                    );
                }

                FinalSnapshot::create([
                    'origen'         => $origen,
                    'origen_hash'    => $origenHash,
                    'responsable_id' => $actorId,
                    'payload'        => [
                        'meta'  => $meta,
                        'total' => $ids->count(),
                        'ids'   => $ids,
                    ],
                    'creado_at'      => $ahora,
                ]);
            });

            // Auditoría (si utilizas esta clase)
            try {
                // Tercer argumento como 0 (int) para evitar warning de tipos
                Audit::log($actorId, 'Finalistas', 0, 'PROMOVER', [
                    'origen'      => $origen,
                    'origen_hash' => $origenHash,
                    'meta'        => $meta,
                    'total'       => $ids->count(),
                ]);
            } catch (Throwable $e) {
                Log::warning('AUDIT PROMOVER falló', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'message'  => 'Entorno preparado',
                'total'    => $ids->count(),
                'origen'   => $origen,
                'snapshot' => FinalSnapshot::where('origen_hash', $origenHash)->first(),
            ], 201);

        } catch (Throwable $e) {
            Log::error('PROMOVER finales error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error al preparar entorno.'], 500);
        }
    }
}
