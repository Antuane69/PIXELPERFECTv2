<?php

namespace App\Http\Requests\EmpleadoCarpetas;

use App\EstadoMembresiaEmpresa;
use App\Models\EmpleadoCarpeta;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class StoreEmpleadoCarpetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmpleadoCarpeta::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
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

    /**
     * @return array{nombre: string, activo: bool, user_ids: array<mixed>}
     */
    public function carpetaData(): array
    {
        $data = $this->validated();
        $nombre = $data['nombre'] ?? null;
        $activo = $data['activo'] ?? true;
        $userIds = $data['user_ids'] ?? [];

        if (! is_string($nombre) || ! is_array($userIds)) {
            throw new LogicException('La información validada de la carpeta tiene un formato incorrecto.');
        }

        return [
            'nombre' => $nombre,
            'activo' => (bool) $activo,
            'user_ids' => $userIds,
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $normalized = [];

        if (is_string($data['nombre'] ?? null)) {
            $normalized['nombre'] = str($data['nombre'])->squish()->toString();
        }

        if (array_key_exists('activo', $data)) {
            $normalized['activo'] = $this->normalizedBoolean($data['activo']);
        }

        if (array_key_exists('user_ids_present', $data) && ! array_key_exists('user_ids', $data)) {
            $normalized['user_ids'] = [];
        }

        $this->merge($normalized);
    }

    private function normalizedBoolean(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }
}
