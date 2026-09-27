<?php

namespace App\Actions\Incapacidades;

use App\Enums\Incapacidades\EstadoIncapacidad;
use App\Jobs\Incapacidades\EnviarNotificacionIncapacidad;
use App\Models\Incapacidad;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolverIncapacidad
{
    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function handle(
        Incapacidad $incapacidad,
        User $actor,
        EstadoIncapacidad $estado,
        ?string $comentarioRechazo = null,
    ): Incapacidad {
        $empresaId = $this->empresaContext->empresaRequerida()->id;

        if (! in_array($estado, [EstadoIncapacidad::Autorizada, EstadoIncapacidad::Rechazada], true)) {
            throw new \InvalidArgumentException('El estado de resolución de la incapacidad no es válido.');
        }

        $resuelta = DB::transaction(function () use (
            $incapacidad,
            $actor,
            $estado,
            $comentarioRechazo,
            $empresaId,
        ): Incapacidad {
            $incapacidad = Incapacidad::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($incapacidad->id)
                ->lockForUpdate()
                ->firstOrFail(['id', 'empresa_id', 'solicitante_user_id', 'estado']);

            if ($incapacidad->estado !== EstadoIncapacidad::Pendiente) {
                throw ValidationException::withMessages([
                    'incapacidad' => 'Esta solicitud ya fue resuelta.',
                ]);
            }

            if ($incapacidad->solicitante_user_id === $actor->id) {
                throw new AuthorizationException('No puedes resolver una solicitud que tú enviaste.');
            }

            $incapacidad->forceFill([
                'estado' => $estado,
                'resuelto_por_user_id' => $actor->id,
                'resuelto_at' => now(),
                'comentarios_rechazo' => $estado === EstadoIncapacidad::Rechazada
                    ? $comentarioRechazo
                    : null,
            ])->save();

            return $incapacidad;
        });

        EnviarNotificacionIncapacidad::dispatch($resuelta->id, 'resolucion')->afterCommit();

        return $resuelta;
    }
}
