<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResponsableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tel = (string)($this->input('telefono') ?? '');
        $tel = preg_replace('/[^\d+]/', '', $tel) ?: null;

        // Merge solo lo que venga (sometimes) pero saneado
        $merge = [];
        if ($this->has('nombres'))    $merge['nombres'] = trim((string)$this->input('nombres'));
        if ($this->has('apellidos'))  $merge['apellidos'] = trim((string)$this->input('apellidos'));
        if ($this->has('ci'))         $merge['ci'] = trim((string)$this->input('ci'));
        if ($this->has('correo'))     $merge['correo'] = strtolower(trim((string)$this->input('correo')));
        if ($this->has('telefono'))   $merge['telefono'] = $tel;
        if ($this->has('area_id'))    $merge['area_id'] = (int)$this->input('area_id');
        if ($this->has('nivel_id'))   $merge['nivel_id'] = ($this->input('nivel_id') === '' || $this->input('nivel_id') === null) ? null : (int)$this->input('nivel_id');
        if ($this->has('activo'))     $merge['activo'] = filter_var($this->input('activo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'nombres'   => ['sometimes','required','string','min:2','max:80', 'regex:/^[\pL\s\'\-\.]+$/u'],
            'apellidos' => ['sometimes','required','string','min:2','max:80', 'regex:/^[\pL\s\'\-\.]+$/u'],
            'ci'        => ['sometimes','required','string','regex:/^[A-Za-z0-9.\-]{5,20}$/'],
            'correo'    => ['sometimes','required','string','email:rfc,dns','max:120'],
            'telefono'  => ['nullable','string','regex:/^\+?\d{7,15}$/'],
            'area_id'   => ['sometimes','required','integer','exists:areas,id'],
            'nivel_id'  => ['nullable','integer','exists:niveles,id'],
            'activo'    => ['sometimes','required','boolean'],
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
