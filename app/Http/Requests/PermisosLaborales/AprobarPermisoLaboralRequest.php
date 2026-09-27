<?php

namespace App\Http\Requests\PermisosLaborales;

use App\Models\PermisosLaborales\PermisoLaboral;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AprobarPermisoLaboralRequest extends FormRequest
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
        return [];
    }
}
