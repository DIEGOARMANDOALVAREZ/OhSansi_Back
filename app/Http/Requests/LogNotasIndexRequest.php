<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogNotasIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // MUY IMPORTANTE: permitir esta acción
        return true;
    }

    public function rules(): array
    {
        return [
            'q_competidor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'q_evaluador'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'area_id'      => ['sometimes', 'nullable', 'integer'],
            'nivel_id'     => ['sometimes', 'nullable', 'integer'],
            'date_from'    => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to'      => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'page'         => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page'     => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
            'sort_by'      => ['sometimes', 'nullable', 'in:occurred_at,campo,usuario,competidor'],
            'sort_dir'     => ['sometimes', 'nullable', 'in:asc,desc'],
        ];
    }
}
