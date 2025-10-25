<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEvaluadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tel = (string)($this->input('telefono') ?? '');
        $tel = preg_replace('/[^\d+]/', '', $tel) ?: null;

        $merge = [];
        if ($this->has('nombres'))   $merge['nombres']   = trim((string)$this->input('nombres'));
        if ($this->has('apellidos')) $merge['apellidos'] = trim((string)$this->input('apellidos'));
        if ($this->has('correo'))    $merge['correo']    = strtolower(trim((string)$this->input('correo')));
        if ($this->has('telefono'))  $merge['telefono']  = $tel;

        if ($this->has('ci')) {
            $ci = trim((string)$this->input('ci'));
            $merge['ci'] = $ci === '' ? null : $ci;
        }

        if ($this->has('nivel_id')) {
            $merge['nivel_id'] = ($this->input('nivel_id') === '' || $this->input('nivel_id') === null)
                ? null
                : (int)$this->input('nivel_id');
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        // Soporta rutas con {evaluador} (model binding) o {id}
        $evaluadorParam = $this->route('evaluador');
        $evaluadorId = is_object($evaluadorParam) ? ($evaluadorParam->id ?? null) : $evaluadorParam;

        return [
            'nombres'   => ['sometimes','required','string','min:2','max:80','regex:/^[\pL\s\'\-\.]+$/u'],
            'apellidos' => ['sometimes','required','string','min:2','max:80','regex:/^[\pL\s\'\-\.]+$/u'],

            'correo'    => ['sometimes','required','string','email:rfc,dns','max:120', Rule::unique('evaluadores','correo')->ignore($evaluadorId)],
            'telefono'  => ['nullable','string','regex:/^\+?\d{7,15}$/'],

            // Ojo: si 'ci' llega en el payload, no puede venir vacío
            'ci'        => ['sometimes','required','string','min:5','max:32', Rule::unique('evaluadores','ci')->ignore($evaluadorId)],

            // Múltiples áreas (solo si las tocan)
            'area_id'   => ['sometimes','required','array','min:1'],
            'area_id.*' => ['required','integer','exists:areas,id'],

            // Nivel único (opcional)
            'nivel_id'  => ['nullable','integer','exists:niveles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ci.required' => 'Ingrese el CI.',
            'ci.unique'   => 'El CI ya está registrado.',

            'nombres.required'   => 'Ingrese los nombres.',
            'nombres.regex'      => 'Los nombres solo pueden contener letras, espacios y - \' .',
            'apellidos.required' => 'Ingrese los apellidos.',
            'apellidos.regex'    => 'Los apellidos solo pueden contener letras, espacios y - \' .',

            'correo.required'    => 'Ingrese el correo.',
            'correo.email'       => 'El correo no tiene un formato válido.',
            'correo.unique'      => 'El correo ya está registrado.',

            'telefono.regex'     => 'El teléfono debe tener entre 7 y 15 dígitos (puede iniciar con +).',

            'area_id.required'   => 'Seleccione al menos un área.',
            'area_id.array'      => 'El formato de área es inválido.',
            'area_id.min'        => 'Seleccione al menos un área.',
            'area_id.*.exists'   => 'Una o más áreas seleccionadas no existen.',

            'nivel_id.exists'    => 'El nivel indicado no existe.',
        ];
    }
}
