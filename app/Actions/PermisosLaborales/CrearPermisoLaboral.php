<?php

namespace App\Actions\PermisosLaborales;

use App\Enums\PermisosLaborales\EstadoPermisoLaboral;
use App\Jobs\PermisosLaborales\EnviarNotificacionPermisoLaboral;
use App\Models\Empleado;
use App\Models\PermisosLaborales\PermisoLaboral;
use App\Models\PermisosLaborales\TipoPermiso;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use App\Services\Empresas\ObtenerAdministradoresEmpresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearPermisoLaboral
{
    public function __construct(
        private readonly EmpresaContext $empresaContext,
        private readonly ObtenerAdministradoresEmpresa $obtenerAdministradores,
    ) {}

    /** @param array{tipo_permiso_id: int, empleado_id?: int, empleados_cubre_ids: list<int>, fecha_inicio: string, fecha_fin: string, comentarios?: string|null} $data */
    public function handle(User $solicitante, array $data): PermisoLaboral
    {
        $empresa = $this->empresaContext->empresaRequerida();

        if ($this->obtenerAdministradores->para($empresa)->isEmpty()) {
            throw ValidationException::withMessages([
                'solicitud' => 'La empresa no tiene un administrador activo con correo para recibir la solicitud.',
            ]);
        }

        $empleadoId = $solicitante->can('permisos_laborales.create_for_others')
            ? (int) ($data['empleado_id'] ?? 0)
            : (int) (Empleado::query()
                ->where('empresa_id', $empresa->id)
                ->where('user_id', $solicitante->id)
                ->value('id') ?? 0);

        if ($empleadoId < 1) {
            throw ValidationException::withMessages([
                'empleado_id' => 'No encontramos un expediente de empleado vinculado a tu cuenta en esta empresa. Pide al administrador que configure la relación.',
            ]);
        }

        $permisoLaboral = DB::transaction(function () use ($data, $empresa, $empleadoId, $solicitante): PermisoLaboral {
            $tipoPermiso = TipoPermiso::query()
                ->where('empresa_id', $empresa->id)
                ->where('activo', true)
                ->whereKey($data['tipo_permiso_id'])
                ->lockForUpdate()
                ->first();

            if (! $tipoPermiso instanceof TipoPermiso) {
                throw ValidationException::withMessages([
                    'tipo_permiso_id' => 'El tipo de permiso seleccionado ya no está disponible.',
                ]);
            }

            $empleado = Empleado::query()
                ->where('empresa_id', $empresa->id)
                ->whereKey($empleadoId)
                ->lockForUpdate()
                ->first();

            if (! $empleado instanceof Empleado) {
                throw ValidationException::withMessages([
                    'empleado_id' => 'El empleado ya no está disponible en esta empresa.',
                ]);
            }

            $permisoLaboral = PermisoLaboral::query()->create([
                'empresa_id' => $empresa->id,
                'empleado_id' => $empleado->id,
                'tipo_permiso_id' => $tipoPermiso->id,
                'solicitante_user_id' => $solicitante->id,
                'solicitante_nombre' => $solicitante->name,
                'solicitante_correo' => $solicitante->email,
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'estado' => EstadoPermisoLaboral::Pendiente,
                'comentarios' => $data['comentarios'] ?? null,
            ]);

            $permisoLaboral->empleadosCobertura()->syncWithPivotValues(
                $data['empleados_cubre_ids'],
                ['empresa_id' => $empresa->id],
            );

            return $permisoLaboral;
        });

        EnviarNotificacionPermisoLaboral::dispatch($permisoLaboral->id, 'solicitud')->afterCommit();

        return $permisoLaboral;
    }
}
