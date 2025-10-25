<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tel = (string)($this->input('telefono') ?? '');
        $tel = preg_replace('/[^\d+]/', '', $tel) ?: null;

        $ci = trim((string)$this->input('ci'));
        $ci = $ci === '' ? null : $ci;

        $this->merge([
            'nombres'   => trim((string)$this->input('nombres')),
            'apellidos' => trim((string)$this->input('apellidos')),
            'correo'    => strtolower(trim((string)$this->input('correo'))),
            'telefono'  => $tel,
            'ci'        => $ci,

            'nivel_id'  => ($this->input('nivel_id') === '' || $this->input('nivel_id') === null)
                ? null
                : (int)$this->input('nivel_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombres'   => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\s\'\-\.]+$/u'],
            'apellidos' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\s\'\-\.]+$/u'],
            'correo'    => ['required', 'string', 'email:rfc,dns', 'max:120', Rule::unique('evaluadores', 'correo')],
            'telefono'  => ['nullable', 'string', 'regex:/^\+?\d{7,15}$/'],

            'ci'        => ['required', 'string', 'min:5', 'max:32', Rule::unique('evaluadores', 'ci')],

            // Múltiples áreas
            'area_id'   => ['required', 'array', 'min:1'],
            'area_id.*' => ['required', 'integer', 'exists:areas,id'],

            // Nivel único (opcional)
            'nivel_id'  => ['nullable', 'integer', 'exists:niveles,id'],
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

            // Mensajes para el array de áreas
            'area_id.required'   => 'Seleccione al menos un área.',
            'area_id.array'      => 'El formato de área es inválido.',
            'area_id.min'        => 'Seleccione al menos un área.',
            'area_id.*.exists'   => 'Una o más áreas seleccionadas no existen.',

            'nivel_id.exists'    => 'El nivel indicado no existe.',
        ];
    }
}
