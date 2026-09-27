<?php

namespace App\Http\Requests\PermisosLaborales;

use App\Models\PermisosLaborales\TipoPermiso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class FiltrarTiposPermisoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TipoPermiso::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'activo' => ['nullable', 'boolean'],
            'archivados' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('search'))) {
            $normalized['search'] = Str::squish($this->input('search'));
        }

        foreach (['activo', 'archivados'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $normalized[$field] = filter_var(
                    $this->input($field),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ) ?? $this->input($field);
            }
        }

        $this->merge($normalized);
    }
}
