<?php

namespace App\Http\Requests\EmpleadoCarpetas;

use App\EstadoMembresiaEmpresa;
use App\Models\EmpleadoCarpeta;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpleadoCarpetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $carpeta = $this->route('empleadoCarpeta');
        $user = $this->user();

        return $carpeta instanceof EmpleadoCarpeta
            && $user !== null
            && $user->can('empleados_carpetas.update')
            && $carpeta->creado_por_id === (int) $user->getAuthIdentifier();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('membresias_empresa', 'user_id')
                    ->where('empresa_id', $empresaId)
                    ->where('estado', EstadoMembresiaEmpresa::Activa->value),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $normalized = [];

        if (is_string($data['nombre'] ?? null)) {
            $normalized['nombre'] = str($data['nombre'])->squish()->toString();
        }

        if (array_key_exists('user_ids_present', $data) && ! array_key_exists('user_ids', $data)) {
            $normalized['user_ids'] = [];
        }

        $this->merge($normalized);
    }
}
