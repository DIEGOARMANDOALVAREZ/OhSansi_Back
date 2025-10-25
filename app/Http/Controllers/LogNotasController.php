<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogNotasIndexRequest;
use App\Models\NoteChangeLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class LogNotasController extends Controller
{
    /** Devuelve 'ilike' para pgsql o 'like' para el resto (MySQL/MariaDB) */
    private function likeOp(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    public function index(LogNotasIndexRequest $request)
    {
        $v = $request->validated();
        $like = $this->likeOp();

        $q = NoteChangeLog::query()
            ->with([
                'user:id,name,email',
                'competidor:id,nombres,apellidos,documento',
            ]);

        // Filtros
        if (!empty($v['q_competidor'])) {
            $term = trim($v['q_competidor']);
            $q->whereHas('competidor', function ($qq) use ($term, $like) {
                $qq->where('nombres', $like, "%{$term}%")
                   ->orWhere('apellidos', $like, "%{$term}%")
                   ->orWhere('documento', $like, "%{$term}%");
            });
        }

        if (!empty($v['q_evaluador'])) {
            $term = trim($v['q_evaluador']);
            $q->whereHas('user', function ($qq) use ($term, $like) {
                $qq->where('email', $like, "%{$term}%")
                   ->orWhere('name',  $like, "%{$term}%");
            });
        }

        if (!empty($v['area_id']))  $q->where('area_id',  $v['area_id']);
        if (!empty($v['nivel_id'])) $q->where('nivel_id', $v['nivel_id']);

        if (!empty($v['date_from'])) $q->where('occurred_at', '>=', $v['date_from'].' 00:00:00');
        if (!empty($v['date_to']))   $q->where('occurred_at', '<=', $v['date_to'].' 23:59:59');

        // Orden
        $sortBy  = $v['sort_by']  ?? 'occurred_at';
        $sortDir = $v['sort_dir'] ?? 'desc';
        $q->orderBy($sortBy, $sortDir);

        // Paginación
        $perPage = $v['per_page'] ?? 15;
        $page    = $v['page']     ?? 1;
        $p = $q->paginate($perPage, ['*'], 'page', $page);

        // Transform para adaptar a lo esperado por el front
        $p->getCollection()->transform(function (NoteChangeLog $log) {
            return [
                'id'          => $log->id,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
                'usuario'     => $log->user ? [
                    'id'     => $log->user->id,
                    'email'  => $log->user->email,
                    'nombre' => $log->user->name,
                ] : null,
                'competidor'  => $log->competidor ? [
                    'id'              => $log->competidor->id,
                    'nombre_completo' => trim($log->competidor->apellidos.' '.$log->competidor->nombres),
                    'documento'       => $log->competidor->documento,
                ] : null,
                'campo'    => $log->campo,
                'anterior' => $log->anterior,
                'nuevo'    => $log->nuevo,
                'motivo'   => $log->motivo,
            ];
        });

        return response()->json($p);
    }

    public function exportCsv(LogNotasIndexRequest $request)
    {
        [$rows, $filename] = $this->collectForExport($request);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        $callback = static function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Fecha/Hora', 'Usuario', 'Competidor', 'Campo', 'Anterior', 'Nuevo', 'Motivo'], ';');

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['occurred_at'],
                    $r['usuario']['email'] ?? '',
                    $r['competidor']['nombre_completo'] ?? '',
                    $r['campo'],
                    (string)($r['anterior'] ?? ''),
                    (string)($r['nuevo'] ?? ''),
                    (string)($r['motivo'] ?? ''),
                ], ';');
            }

            fclose($out);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function exportXlsx(LogNotasIndexRequest $request)
    {
        // XLSX deshabilitado mientras no esté la librería instalada
        return response()->json([
            'message' => 'Exportación a Excel (XLSX) no disponible. Use CSV.'
        ], 501);
    }

    /** Construye la colección exportable reutilizando los filtros del index */
    private function collectForExport(LogNotasIndexRequest $request): array
    {
        $v = $request->validated();
        $like = $this->likeOp();

        $q = NoteChangeLog::query()->with([
            'user:id,name,email',
            'competidor:id,nombres,apellidos,documento',
        ]);

        if (!empty($v['q_competidor'])) {
            $term = trim($v['q_competidor']);
            $q->whereHas('competidor', function ($qq) use ($term, $like) {
                $qq->where('nombres',   $like, "%{$term}%")
                   ->orWhere('apellidos',$like, "%{$term}%")
                   ->orWhere('documento',$like, "%{$term}%");
            });
        }
        if (!empty($v['q_evaluador'])) {
            $term = trim($v['q_evaluador']);
            $q->whereHas('user', function ($qq) use ($term, $like) {
                $qq->where('email', $like, "%{$term}%")
                   ->orWhere('name',  $like, "%{$term}%");
            });
        }
        if (!empty($v['area_id']))  $q->where('area_id',  $v['area_id']);
        if (!empty($v['nivel_id'])) $q->where('nivel_id', $v['nivel_id']);
        if (!empty($v['date_from'])) $q->where('occurred_at', '>=', $v['date_from'].' 00:00:00');
        if (!empty($v['date_to']))   $q->where('occurred_at', '<=', $v['date_to'].' 23:59:59');

        $q->orderBy($v['sort_by'] ?? 'occurred_at', $v['sort_dir'] ?? 'desc');

        $rows = $q->get()->map(function (NoteChangeLog $log) {
            return [
                'occurred_at' => optional($log->occurred_at)->format('Y-m-d H:i:s'),
                'usuario'     => $log->user ? [
                    'id'     => $log->user->id,
                    'email'  => $log->user->email,
                    'nombre' => $log->user->name,
                ] : null,
                'competidor'  => $log->competidor ? [
                    'id'               => $log->competidor->id,
                    'nombre_completo'  => trim($log->competidor->apellidos.' '.$log->competidor->nombres),
                ] : null,
                'campo'    => $log->campo,
                'anterior' => $log->anterior,
                'nuevo'    => $log->nuevo,
                'motivo'   => $log->motivo,
            ];
        })->all();

        $filename = 'log_cambios_notas_'.now()->format('Ymd_His');
        return [$rows, $filename];
    }
}
