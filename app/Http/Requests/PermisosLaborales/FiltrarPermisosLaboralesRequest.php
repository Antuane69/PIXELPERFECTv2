<?php

namespace App\Http\Requests\PermisosLaborales;

use App\Models\PermisosLaborales\PermisoLaboral;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FiltrarPermisosLaboralesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PermisoLaboral::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(['PENDIENTE', 'AUTORIZADO', 'RECHAZADO', 'VENCIDO'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => Str::squish($this->input('search'))]);
        }
    }
}
