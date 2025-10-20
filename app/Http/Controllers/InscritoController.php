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
        // Validación del archivo y los parámetros
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt',
            'no_duplicate_key' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Archivo no válido o parámetros incorrectos'], 400);
        }

        $file = $request->file('file');
        $simulate = $request->input('simulate', 'true') === 'true'; // Si es simulación o confirmación
        $noDuplicateKey = $request->input('no_duplicate_key', false);

        // Leer y procesar el archivo CSV
        $handle = fopen($file->getRealPath(), 'r');
        $headers = fgetcsv($handle); // Leer encabezados
        $rows = [];
        $errors = [];
        $inserted = 0;

        // Encabezados esperados
        $expectedHeaders = ['documento', 'nombres', 'apellidos', 'unidad', 'area', 'nivel'];
        $missingHeaders = array_diff($expectedHeaders, array_map('strtolower', $headers));

        if ($missingHeaders) {
            return response()->json([
                'total' => 0,
                'inserted' => 0,
                'rejected' => 0,
                'errors' => [['row' => 1, 'cause' => 'Encabezados faltantes: ' . implode(', ', $missingHeaders)]],
            ]);
        }

        // Leer las filas del archivo
        $lineNumber = 2; // Comenzamos desde la segunda fila (después de los encabezados)
        while (($row = fgetcsv($handle)) !== false) {
            $documento = $row[0];
            $nombres = $row[1];
            $apellidos = $row[2];
            $unidad = $row[3];
            $area = $row[4];
            $nivel = $row[5];

            $rowErrors = [];
            // Validaciones adicionales
            if (!$documento || !preg_match('/^\d{5,}$/', $documento)) {
                $rowErrors[] = 'Documento con formato no válido';
            }
            if (!$nombres || !$apellidos) {
                $rowErrors[] = 'Nombres y/o apellidos vacíos';
            }
            if (!$area) {
                $rowErrors[] = 'Área vacía';
            }
            if (!$nivel) {
                $rowErrors[] = 'Nivel vacío';
            }

            if ($noDuplicateKey) {
                // Verificar duplicados: documento + area + nivel
                $existingInscrito = Inscrito::where('documento', $documento)
                    ->where('area', $area)
                    ->where('nivel', $nivel)
                    ->first();
                if ($existingInscrito) {
                    $rowErrors[] = "Duplicado: $documento + $area/$nivel";
                }
            }

            // Si hay errores, no insertamos este registro
            if ($rowErrors) {
                $errors[] = ['row' => $lineNumber, 'cause' => implode(', ', $rowErrors)];
            } else {
                Log::info("Insertando: {$documento} - {$nombres} {$apellidos} en {$unidad} ({$area}/{$nivel})"); // Log para ver qué se va a insertar
                if (!$simulate) {
                    // Insertar registro real si no está en simulación
                    $inscrito = Inscrito::create([
                        'documento' => $documento,
                        'nombres' => $nombres,
                        'apellidos' => $apellidos,
                        'unidad' => $unidad,
                        'area' => $area,
                        'nivel' => $nivel,
                    ]);
                    if ($inscrito) {
                        $inserted++;  // Solo se incrementa si el registro es insertado con éxito
                    }
                }
            }

            $lineNumber++;
        }

        fclose($handle);

        // Resultado de la importación con detalles de los errores
        return response()->json([
            'total' => $lineNumber - 2,  // Total de filas procesadas (excluyendo encabezados)
            'inserted' => $inserted,     // Registros insertados
            'rejected' => count($errors), // Registros rechazados
            'errors' => $errors,         // Detalles de los errores
            'log' => "Importación realizada con éxito: $inserted insertados, " . count($errors) . " rechazados.",
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

