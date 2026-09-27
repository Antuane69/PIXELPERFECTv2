<?php

namespace App\Actions\Vacaciones;

use App\EstadoVacacion;
use App\Jobs\EnviarNotificacionVacacion;
use App\Models\DiaFestivo;
use App\Models\Empleado;
use App\Models\User;
use App\Models\Vacacion;
use App\Services\Empresas\EmpresaContext;
use App\Services\Vacaciones\CalcularDiasVacaciones;
use App\Services\Vacaciones\ObtenerAdministradoresVacaciones;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearVacacion
{
    public function __construct(
        private readonly EmpresaContext $empresaContext,
        private readonly CalcularDiasVacaciones $calcularDias,
        private readonly ObtenerAdministradoresVacaciones $obtenerAdministradores,
    ) {}

    /** @param array{empleado_id?: int|string, empleados_cubre_ids?: list<int|string>, fecha_inicio: string, fecha_fin: string, comentarios?: string|null} $data */
    public function handle(User $solicitante, array $data): Vacacion
    {
        $empresa = $this->empresaContext->empresaRequerida();

        if ($this->obtenerAdministradores->para($empresa)->isEmpty()) {
            throw ValidationException::withMessages([
                'solicitud' => 'La empresa no tiene un administrador activo con correo para recibir la solicitud.',
            ]);
        }

        $empleadoId = $solicitante->can('vacaciones.create_for_others')
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

        $vacacion = DB::transaction(function () use ($data, $empresa, $empleadoId, $solicitante): Vacacion {
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

            if ($empleado->dias_vacaciones === null) {
                throw ValidationException::withMessages([
                    'dias_vacaciones' => 'Completa el saldo de vacaciones en el expediente del empleado antes de solicitar vacaciones.',
                ]);
            }

            $fechasFestivas = $empresa->diasFestivos()
                ->whereBetween('fecha', [$data['fecha_inicio'], $data['fecha_fin']])
                ->get(['fecha'])
                ->map(static fn (DiaFestivo $diaFestivo): string => $diaFestivo->fecha->toDateString())
                ->all();

            $diasSolicitados = $this->calcularDias->contarDiasSolicitados(
                $data['fecha_inicio'],
                $data['fecha_fin'],
                $empleado->diasDescansoConfigurados(),
                $fechasFestivas,
            );

            if ($diasSolicitados < 1) {
                throw ValidationException::withMessages([
                    'fecha_fin' => 'El periodo debe contener al menos un día laborable que no sea de descanso ni festivo.',
                ]);
            }

            if ($diasSolicitados > $empleado->dias_vacaciones) {
                throw ValidationException::withMessages([
                    'fecha_fin' => "El saldo actual es de {$empleado->dias_vacaciones} días y la solicitud requiere {$diasSolicitados}.",
                ]);
            }

            $fechaActual = now($empresa->zona_horaria)->toDateString();
            $ultimaVacacion = $this->calcularDias->fechaUltimaVacacion($empleado, $fechaActual);
            $vacacion = Vacacion::query()->create([
                'empresa_id' => $empresa->id,
                'empleado_id' => $empleado->id,
                'solicitante_user_id' => $solicitante->id,
                'solicitante_nombre' => $solicitante->name,
                'solicitante_correo' => $solicitante->email,
                'dias_solicitados' => $diasSolicitados,
                'saldo_dias_al_solicitar' => $empleado->dias_vacaciones,
                'fecha_ultima_vacacion_al_solicitar' => $ultimaVacacion,
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'estado' => EstadoVacacion::Pendiente,
                'comentarios' => $data['comentarios'] ?? null,
            ]);

            $vacacion->empleadosCobertura()->syncWithPivotValues(
                array_map('intval', $data['empleados_cubre_ids'] ?? []),
                ['empresa_id' => $empresa->id],
            );

            return $vacacion;
        });

        EnviarNotificacionVacacion::dispatch($vacacion->id, 'solicitud')->afterCommit();

        return $vacacion;
    }
}
