<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarEvaluacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización se hace en el controlador con el evaluador y el inscrito.
    }

    public function rules(): array
    {
        return [
            'notas' => ['nullable','array'],
            // opcionalmente validar sub-claves: 'notas.*' => ['numeric','min:0','max:100']
            'nota_final' => ['nullable','numeric','min:0','max:100'],
            'concepto'   => ['nullable','in:APROBADO,DESAPROBADO,DESCLASIFICADO'],
            'observaciones' => ['nullable','string'],
        ];
    }
}
