<?php

namespace App\Actions\Vacaciones;

use App\EstadoVacacion;
use App\Jobs\EnviarNotificacionVacacion;
use App\Models\Empleado;
use App\Models\User;
use App\Models\Vacacion;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolverVacacion
{
    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function handle(
        Vacacion $vacacion,
        User $actor,
        EstadoVacacion $estado,
        ?string $comentarioRechazo = null,
    ): Vacacion {
        $empresaId = $this->empresaContext->empresaRequerida()->id;

        if (! in_array($estado, [EstadoVacacion::Autorizada, EstadoVacacion::Rechazada], true)) {
            throw new \InvalidArgumentException('El estado de resolución no es válido.');
        }

        $resuelta = DB::transaction(function () use ($vacacion, $actor, $estado, $comentarioRechazo, $empresaId): Vacacion {
            $vacacion = Vacacion::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($vacacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($vacacion->estado !== EstadoVacacion::Pendiente) {
                throw ValidationException::withMessages([
                    'vacacion' => 'Esta solicitud ya fue resuelta.',
                ]);
            }

            if ($estado === EstadoVacacion::Autorizada) {
                $empleado = Empleado::query()
                    ->where('empresa_id', $empresaId)
                    ->whereKey($vacacion->empleado_id)
                    ->lockForUpdate()
                    ->first();

                if (! $empleado instanceof Empleado || $empleado->dias_vacaciones === null) {
                    throw ValidationException::withMessages([
                        'vacacion' => 'El expediente del empleado no tiene un saldo disponible para descontar.',
                    ]);
                }

                if ($empleado->dias_vacaciones < $vacacion->dias_solicitados) {
                    throw ValidationException::withMessages([
                        'vacacion' => "El saldo actual de {$empleado->dias_vacaciones} días ya no alcanza para esta solicitud.",
                    ]);
                }

                $empleado->decrement('dias_vacaciones', $vacacion->dias_solicitados);
            }

            $vacacion->forceFill([
                'estado' => $estado,
                'resuelto_por_user_id' => $actor->id,
                'resuelto_at' => now(),
                'comentarios_rechazo' => $estado === EstadoVacacion::Rechazada ? $comentarioRechazo : null,
            ])->save();

            return $vacacion;
        });

        EnviarNotificacionVacacion::dispatch($resuelta->id, 'resolucion')->afterCommit();

        return $resuelta;
    }
}
