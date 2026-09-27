<?php

namespace App\Http\Requests\FaltasReglamento;

use App\Models\FaltasReglamento\TipoFaltaReglamento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FiltrarTiposFaltaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TipoFaltaReglamento::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'activo' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'archivados' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => Str::squish($this->input('search'))]);
        }
    }
}
