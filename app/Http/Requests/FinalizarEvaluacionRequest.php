<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizarEvaluacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización se maneja en el controlador
    }

    /**
     * Normaliza los campos antes de validar
     */
    protected function prepareForValidation(): void
    {
        // Normaliza nota_final (admite coma decimal)
        $nf = $this->input('nota_final', null);
        if ($nf !== null && $nf !== '') {
            $s   = str_replace(',', '.', (string) $nf);
            $num = is_numeric($s) ? round((float) $s, 2) : null;
        } else {
            $num = null;
        }

        // Normaliza concepto (uppercase y trim)
        $concepto = $this->input('concepto');
        $concepto = is_string($concepto) ? strtoupper(trim($concepto)) : $concepto;

        // Normaliza observaciones (trim)
        $obs = $this->input('observaciones');
        $obs = is_string($obs) ? trim($obs) : $obs;

        // Asegura que "notas" exista SIEMPRE como array ([] o cast de objeto)
        $notas = $this->input('notas', []);
        if ($notas === null) {
            $notas = [];
        } elseif (!is_array($notas)) {
            // JSON {} o valores escalares => a array asociativo/ vacío
            $notas = (array) $notas;
        }

        $this->merge([
            'nota_final'    => $num,
            'concepto'      => $concepto,
            'observaciones' => $obs,
            'notas'         => $notas,
        ]);

        // 💡 Descomenta para depurar qué llega realmente:
        // \Log::info('FINALIZAR prepare payload', $this->all());
    }

    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        return [
            // ⚠️ Usamos PRESENT en lugar de REQUIRED para aceptar []
            // 'present' exige que la clave exista; 'array' valida el tipo.
            'notas'         => ['present', 'array'],

            // nota_final obligatoria salvo DESCLASIFICADO
            'nota_final'    => ['required_unless:concepto,DESCLASIFICADO', 'numeric', 'min:0', 'max:100'],

            'concepto'      => ['required', 'in:APROBADO,DESAPROBADO,DESCLASIFICADO'],
            'observaciones' => ['nullable', 'string'],
        ];
    }

    /**
     * Validaciones adicionales personalizadas
     */
    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $concepto = $this->input('concepto');
            $obs      = $this->input('observaciones');

            if ($concepto === 'DESCLASIFICADO' && (!is_string($obs) || trim($obs) === '')) {
                $v->errors()->add('observaciones', 'El motivo/observaciones es obligatorio cuando el concepto es DESCLASIFICADO.');
            }
        });
    }
}
