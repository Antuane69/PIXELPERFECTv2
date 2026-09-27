<?php

namespace App\Http\Requests\PermisosLaborales;

use App\Models\Empleado;
use App\Models\PermisosLaborales\PermisoLaboral;
use App\Models\PermisosLaborales\TipoPermiso;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class StorePermisoLaboralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PermisoLaboral::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $empresa = app(EmpresaContext::class)->empresaRequerida();
        $puedeElegirEmpleado = $this->user()?->can('permisos_laborales.create_for_others') ?? false;
        $maximoEmpleadosCobertura = Empleado::query()
            ->where('empresa_id', $empresa->id)
            ->count();

        return [
            'tipo_permiso_id' => [
                'required',
                'integer',
                Rule::exists(TipoPermiso::class, 'id')->where(static fn ($query) => $query
                    ->where('empresa_id', $empresa->id)
                    ->where('activo', true)
                    ->whereNull('deleted_at')),
            ],
            'empleado_id' => $puedeElegirEmpleado
                ? [
                    'required',
                    'integer',
                    Rule::exists(Empleado::class, 'id')->where(static fn ($query) => $query
                        ->where('empresa_id', $empresa->id)
                        ->whereNull('deleted_at')),
                ]
                : ['prohibited'],
            'empleados_cubre_ids' => ['sometimes', 'array', 'max:'.$maximoEmpleadosCobertura],
            'empleados_cubre_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Empleado::class, 'id')->where(static fn ($query) => $query
                    ->where('empresa_id', $empresa->id)
                    ->whereNull('deleted_at')),
            ],
            'fecha_inicio' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.now($empresa->zona_horaria)->toDateString(),
            ],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'comentarios' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comentarios'))) {
            $this->merge(['comentarios' => trim($this->input('comentarios')) ?: null]);
        }
    }

    /**
     * @return array{tipo_permiso_id: int, empleado_id?: int, empleados_cubre_ids: list<int>, fecha_inicio: string, fecha_fin: string, comentarios?: string|null}
     */
    public function permisoData(): array
    {
        $validated = $this->validated();
        $coverIds = $validated['empleados_cubre_ids'] ?? [];
        $fechaInicio = $validated['fecha_inicio'] ?? null;
        $fechaFin = $validated['fecha_fin'] ?? null;
        $tipoPermisoId = $validated['tipo_permiso_id'] ?? null;

        if (! is_array($coverIds) || ! is_string($fechaInicio) || ! is_string($fechaFin) || (! is_int($tipoPermisoId) && ! is_string($tipoPermisoId))) {
            throw new LogicException('La solicitud validada de permiso laboral está incompleta.');
        }

        $data = [
            'tipo_permiso_id' => (int) $tipoPermisoId,
            'empleados_cubre_ids' => array_values(array_map(
                static fn (int|string $id): int => (int) $id,
                array_filter($coverIds, static fn (mixed $id): bool => is_int($id) || is_string($id)),
            )),
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ];

        if (array_key_exists('empleado_id', $validated)) {
            $empleadoId = $validated['empleado_id'];

            if (! is_int($empleadoId) && ! is_string($empleadoId)) {
                throw new LogicException('El empleado validado del permiso laboral no es válido.');
            }

            $data['empleado_id'] = (int) $empleadoId;
        }

        if (array_key_exists('comentarios', $validated)) {
            $comentarios = $validated['comentarios'];

            if ($comentarios !== null && ! is_string($comentarios)) {
                throw new LogicException('Los comentarios validados del permiso laboral no son válidos.');
            }

            $data['comentarios'] = $comentarios;
        }

        return $data;
    }
}
