<?php

namespace App\Http\Requests\Reportes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'formato' => ['required', Rule::in(['pdf', 'xlsx'])],
            'filtros' => ['sometimes', 'array'],
        ];
    }
}
