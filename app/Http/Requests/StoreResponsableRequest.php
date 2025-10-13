<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResponsableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el middleware de auth/role ya protege
    }

    protected function prepareForValidation(): void
    {
        // Limpieza suave antes de validar
        $tel = (string)($this->input('telefono') ?? '');
        // Deja solo dígitos y "+", quitando espacios, guiones, etc.
        $tel = preg_replace('/[^\d+]/', '', $tel) ?: null;

        $this->merge([
            'nombres' => trim((string)$this->input('nombres')),
            'apellidos' => trim((string)$this->input('apellidos')),
            'ci' => trim((string)$this->input('ci')),
            'correo' => strtolower(trim((string)$this->input('correo'))),
            'telefono' => $tel,
            'area_id' => $this->input('area_id') !== null ? (int)$this->input('area_id') : null,
            'nivel_id' => ($this->input('nivel_id') === '' || $this->input('nivel_id') === null) ? null : (int)$this->input('nivel_id'),
            'activo' => filter_var($this->input('activo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }

    public function rules(): array
    {
        return [
            'nombres'   => ['required','string','min:2','max:80', 'regex:/^[\pL\s\'\-\.]+$/u'],
            'apellidos' => ['required','string','min:2','max:80', 'regex:/^[\pL\s\'\-\.]+$/u'],
            // CI como en tu input HTML: letras/números/puntos/guiones, 5–20
            'ci'        => ['required','string','regex:/^[A-Za-z0-9.\-]{5,20}$/'],
            'correo'    => ['required','string','email:rfc,dns','max:120'],
            // Teléfono internacional simple: opcional, + y 7–15 dígitos
            'telefono'  => ['nullable','string','regex:/^\+?\d{7,15}$/'],
            'area_id'   => ['required','integer','exists:areas,id'],
            'nivel_id'  => ['nullable','integer','exists:niveles,id'],
            'activo'    => ['required','boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombres.required'   => 'Ingrese los nombres.',
            'nombres.regex'      => 'Los nombres solo pueden contener letras, espacios y - \' .',
            'apellidos.required' => 'Ingrese los apellidos.',
            'apellidos.regex'    => 'Los apellidos solo pueden contener letras, espacios y - \' .',
            'ci.required'        => 'Ingrese el CI/ID.',
            'ci.regex'           => 'El CI debe tener 5–20 caracteres (letras/números/puntos/guiones).',
            'correo.required'    => 'Ingrese el correo.',
            'correo.email'       => 'El correo no tiene un formato válido.',
            'telefono.regex'     => 'El teléfono debe tener entre 7 y 15 dígitos (puede iniciar con +).',
            'area_id.required'   => 'Seleccione un área.',
            'area_id.exists'     => 'El área indicada no existe.',
            'nivel_id.exists'    => 'El nivel indicado no existe.',
            'activo.boolean'     => 'El campo estado es inválido.',
        ];
    }
}
