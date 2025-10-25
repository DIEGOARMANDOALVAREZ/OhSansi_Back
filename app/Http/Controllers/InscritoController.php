<?php
namespace App\Http\Controllers;

use App\Models\Inscrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InscritoController extends Controller
{
    public function import(Request $request)
{
    // Validación inicial de parámetros
    $request->validate([
        'file'             => 'required|file|mimes:csv,txt',
        'simulate'         => 'nullable|string|in:true,false,1,0',
        'no_duplicate_key' => 'nullable|string|in:true,false,1,0',
    ]);

    // Normalización de flags
    $simulate = in_array((string)$request->input('simulate', 'true'), ['true', '1'], true);
    $noDuplicateKey = in_array((string)$request->input('no_duplicate_key', '0'), ['true', '1'], true);

    $file = $request->file('file');
    $path = $file->getRealPath();
    $fh = fopen($path, 'r');

    if ($fh === false) {
        return response()->json([
            'total' => 0, 'inserted' => 0, 'rejected' => 0,
            'errors' => [['row' => 0, 'cause' => 'No se pudo abrir el archivo']],
        ], 400);
    }

    // --- Helpers locales ---
    $stripBOM = static function (string $s): string {
        // elimina BOM UTF-8 si existe
        return preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
    };
    $toLowerTrim = static function (array $arr): array {
        return array_map(static function ($v) {
            return strtolower(trim((string)$v));
        }, $arr);
    };
    $trimRow = static function (array $arr): array {
        return array_map(static function ($v) {
            return trim((string)$v);
        }, $arr);
    };

    // 1) Leer primera línea y detectar delimitador (',' o ';')
    $firstLine = fgets($fh);
    if ($firstLine === false) {
        fclose($fh);
        return response()->json([
            'total' => 0, 'inserted' => 0, 'rejected' => 0,
            'errors' => [['row' => 0, 'cause' => 'CSV vacío']],
        ], 422);
    }
    $firstLine = $stripBOM($firstLine);
    $commaCount = substr_count($firstLine, ',');
    $semiCount  = substr_count($firstLine, ';');
    $delim = $semiCount > $commaCount ? ';' : ',';

    // Volver a apuntar al inicio para usar fgetcsv con el delimitador detectado
    rewind($fh);

    // 2) Encabezados
    $headers = fgetcsv($fh, 0, $delim);
    if ($headers === false) {
        fclose($fh);
        return response()->json([
            'total' => 0, 'inserted' => 0, 'rejected' => 0,
            'errors' => [['row' => 0, 'cause' => 'No se pudieron leer encabezados']],
        ], 422);
    }

    // Normalizar encabezados
    $headers[0] = $stripBOM((string)$headers[0]);
    $headersNorm = $toLowerTrim($headers);

    $expected = ['documento','nombres','apellidos','unidad','area','nivel'];
    if ($headersNorm !== $expected) {
        fclose($fh);
        // Mostrar qué llegó para depurar rápido
        return response()->json([
            'total' => 0, 'inserted' => 0, 'rejected' => 0,
            'errors' => [[
                'row' => 1,
                'cause' => 'Encabezados inválidos. Esperado: ' . implode(',', $expected) .
                           ' | Recibido: ' . implode(',', $headersNorm),
            ]],
        ], 422);
    }

    $errors = [];
    $inserted = 0;
    $processed = 0;
    $rowNumber = 1; // encabezados

    // 3) Recorrer filas
    while (($cols = fgetcsv($fh, 0, $delim)) !== false) {
        $rowNumber++;
        // Aceptar filas vacías al final del archivo sin contarlas como error
        if (count($cols) === 1 && trim((string)$cols[0]) === '') {
            continue;
        }

        $cols = $trimRow($cols);
        // Asegurar que haya 6 columnas
        if (count($cols) < 6) {
            $errors[] = ['row' => $rowNumber, 'cause' => 'Fila incompleta (se esperan 6 columnas)'];
            continue;
        }

        [$documento, $nombres, $apellidos, $unidad, $area, $nivel] = $cols;

        $rowErr = [];
        if ($documento === '' || !preg_match('/^\d{5,}$/', $documento)) {
            $rowErr[] = 'Documento no válido (min 5 dígitos)';
        }
        if ($nombres === '' || $apellidos === '') {
            $rowErr[] = 'Nombres y/o apellidos vacíos';
        }
        if ($area === '') {
            $rowErr[] = 'Área vacía';
        }
        if ($nivel === '') {
            $rowErr[] = 'Nivel vacío';
        }

        // Duplicados (documento + área + nivel) cuando se solicita
        if ($noDuplicateKey && empty($rowErr)) {
            $exists = \App\Models\Inscrito::where('documento', $documento)
                ->where('area', $area)   // 👈 Si en tu BD son area_id/nivel_id, ajusta aquí.
                ->where('nivel', $nivel) // 👈 idem.
                ->exists();

            if ($exists) {
                $rowErr[] = "Duplicado: {$documento} + {$area}/{$nivel}";
            }
        }

        if (!empty($rowErr)) {
            $errors[] = ['row' => $rowNumber, 'cause' => implode('; ', $rowErr)];
            continue;
        }

        // Guardado (solo si no es simulación)
        if (!$simulate) {
            try {
                // ⚠️ Si tu tabla realmente usa area_id/nivel_id, reemplaza por esos campos.
                \App\Models\Inscrito::create([
                    'documento' => $documento,
                    'nombres'   => $nombres,
                    'apellidos' => $apellidos,
                    'unidad'    => $unidad,
                    'area'      => $area,   // o 'area_id' => <mapear>
                    'nivel'     => $nivel,  // o 'nivel_id' => <mapear>
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'cause' => 'Error al insertar: '.$e->getMessage()];
                // no lanzamos, seguimos con el resto
            }
        } else {
            // simulación cuenta como potencial inserción
            $inserted++;
        }

        $processed++;
    }

    fclose($fh);

    // En simulación, 'inserted' representa los que serían insertados
    $rejected = count($errors);

    return response()->json([
        'total'    => $processed,
        'inserted' => $simulate ? $inserted : $inserted, // mismos números, distinto significado
        'rejected' => $rejected,
        'errors'   => $errors,
        'log'      => $simulate
            ? 'Simulación completada. No se insertaron registros.'
            : 'Importación completada. Se insertaron registros válidos y se reportaron errores por fila.',
    ]);
}

    public function getInscritos()
    {
        try {
            $inscritos = Inscrito::all();
            return response()->json($inscritos);
        } catch (\Exception $e) {
            Log::error("Error al obtener inscritos: " . $e->getMessage());
            return response()->json([
                'error' => 'No se pudieron obtener los inscritos',
                'message' => $e->getMessage()
            ], 500);
        }
    }

}

