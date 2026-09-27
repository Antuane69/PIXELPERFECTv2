<?php

namespace App\Http\Requests\PermisosLaborales;

use App\Models\PermisosLaborales\TipoPermiso;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTipoPermisoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', TipoPermiso::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $empresa = app(EmpresaContext::class)->empresaRequerida();

        return [
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique(TipoPermiso::class, 'nombre')->where('empresa_id', $empresa->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('nombre'))) {
            $normalized['nombre'] = str($this->input('nombre'))->squish()->toString();
        }

        if (is_string($this->input('descripcion'))) {
            $normalized['descripcion'] = trim($this->input('descripcion')) ?: null;
        }

        if ($this->has('activo') && is_string($this->input('activo'))) {
            $normalized['activo'] = filter_var(
                $this->input('activo'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? $this->input('activo');
        }

        $this->merge($normalized);
    }
}
