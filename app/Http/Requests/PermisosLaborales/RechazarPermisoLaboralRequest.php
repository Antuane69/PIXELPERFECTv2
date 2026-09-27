<?php

namespace App\Http\Requests\PermisosLaborales;

use App\Models\PermisosLaborales\PermisoLaboral;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RechazarPermisoLaboralRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permisoLaboral = $this->route('permisoLaboral');

        return $permisoLaboral instanceof PermisoLaboral
            && ($this->user()?->can('review', $permisoLaboral) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'comentarios_rechazo' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comentarios_rechazo'))) {
            $this->merge(['comentarios_rechazo' => trim($this->input('comentarios_rechazo'))]);
        }
    }
}
