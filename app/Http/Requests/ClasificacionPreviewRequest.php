<?php
// app/Http/Requests/ClasificacionPreviewRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClasificacionPreviewRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $min = $this->input('minima', 70);
        $min = is_numeric(str_replace(',', '.', (string)$min))
            ? (float) str_replace(',', '.', (string)$min)
            : 70;
        $this->merge(['minima' => round($min, 2)]);
    }

    public function rules(): array
    {
        return [
            'area_id'  => ['nullable','integer'],
            'nivel_id' => ['nullable','integer'],
            'minima'   => ['required','numeric','min:0','max:100'],
        ];
    }
}
