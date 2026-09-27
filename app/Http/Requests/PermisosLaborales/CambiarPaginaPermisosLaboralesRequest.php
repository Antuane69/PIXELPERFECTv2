<?php

namespace App\Http\Requests\PermisosLaborales;

use App\Models\PermisosLaborales\PermisoLaboral;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarPaginaPermisosLaboralesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PermisoLaboral::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'page' => ['required', 'integer', 'min:1', 'max:10000'],
            'listado' => ['required', Rule::in(['solicitudes', 'proximas'])],
        ];
    }
}
