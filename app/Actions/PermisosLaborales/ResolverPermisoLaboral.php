<?php

namespace App\Actions\PermisosLaborales;

use App\Enums\PermisosLaborales\EstadoPermisoLaboral;
use App\Jobs\PermisosLaborales\EnviarNotificacionPermisoLaboral;
use App\Models\PermisosLaborales\PermisoLaboral;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolverPermisoLaboral
{
    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function handle(
        PermisoLaboral $permisoLaboral,
        User $actor,
        EstadoPermisoLaboral $estado,
        ?string $comentarioRechazo = null,
    ): PermisoLaboral {
        $empresaId = $this->empresaContext->empresaRequerida()->id;

        if (! in_array($estado, [EstadoPermisoLaboral::Autorizado, EstadoPermisoLaboral::Rechazado], true)) {
            throw new \InvalidArgumentException('El estado de resolución del permiso laboral no es válido.');
        }

        $resuelto = DB::transaction(function () use ($permisoLaboral, $actor, $estado, $comentarioRechazo, $empresaId): PermisoLaboral {
            $permisoLaboral = PermisoLaboral::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($permisoLaboral->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($permisoLaboral->estado !== EstadoPermisoLaboral::Pendiente) {
                throw ValidationException::withMessages([
                    'permisoLaboral' => 'Esta solicitud ya fue resuelta.',
                ]);
            }

            if ($permisoLaboral->solicitante_user_id === $actor->id) {
                throw new AuthorizationException('No puedes resolver una solicitud que tú enviaste.');
            }

            $permisoLaboral->forceFill([
                'estado' => $estado,
                'resuelto_por_user_id' => $actor->id,
                'resuelto_at' => now(),
                'comentarios_rechazo' => $estado === EstadoPermisoLaboral::Rechazado ? $comentarioRechazo : null,
            ])->save();

            return $permisoLaboral;
        });

        EnviarNotificacionPermisoLaboral::dispatch($resuelto->id, 'resolucion')->afterCommit();

        return $resuelto;
    }
}
