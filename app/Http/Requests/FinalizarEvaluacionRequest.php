<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizarEvaluacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización en el controlador
    }

    public function rules(): array
    {
        return [
            'notas' => ['required','array'],
            // 'notas.*' => ['numeric','min:0','max:100'],
            'nota_final' => ['required','numeric','min:0','max:100'],
            'concepto'   => ['required','in:APROBADO,DESAPROBADO,DESCLASIFICADO'],
            'observaciones' => ['nullable','string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $concepto = $this->input('concepto');
            $obs = $this->input('observaciones');

            if ($concepto === 'DESCLASIFICADO' && (!is_string($obs) || trim($obs) === '')) {
                $v->errors()->add('observaciones', 'El motivo/observaciones es obligatorio cuando el concepto es DESCLASIFICADO.');
            }
        });
    }
}
