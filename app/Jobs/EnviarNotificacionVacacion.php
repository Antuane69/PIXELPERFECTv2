<?php

namespace App\Jobs;

use App\EstadoVacacion;
use App\Mail\NotificacionVacacionMail;
use App\Models\Empresa;
use App\Models\Vacacion;
use App\Services\Vacaciones\ObtenerAdministradoresVacaciones;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class EnviarNotificacionVacacion implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $vacacionId,
        public readonly string $tipo,
    ) {}

    public function handle(ObtenerAdministradoresVacaciones $obtenerAdministradores): void
    {
        if (! in_array($this->tipo, ['solicitud', 'resolucion'], true)) {
            throw new RuntimeException('El tipo de notificación de vacaciones no es válido.');
        }

        $vacacion = Vacacion::query()
            ->with([
                'empresa:id,nombre_legal,nombre_comercial,zona_horaria',
                'empleado:id,empresa_id,nombre,correo',
                'solicitante:id,name,email',
                'resueltoPor:id,name',
                'empleadosCobertura:id,empresa_id,nombre',
            ])
            ->find($this->vacacionId);

        if (! $vacacion instanceof Vacacion) {
            return;
        }

        $empresa = $vacacion->empresa;

        if (! $empresa instanceof Empresa) {
            return;
        }

        $administradores = $obtenerAdministradores->para($empresa);
        $correosAdministradores = $administradores
            ->pluck('email')
            ->filter(static fn (mixed $email): bool => is_string($email) && $email !== '')
            ->unique()
            ->values()
            ->all();

        if ($this->tipo === 'solicitud' && $correosAdministradores === []) {
            throw new RuntimeException('No hay administradores de empresa con correo para recibir la solicitud.');
        }

        $solicitudesCoincidentes = [];

        if ($this->tipo === 'solicitud') {
            $coincidenciasQuery = Vacacion::query()
                ->where('empresa_id', $empresa->id)
                ->whereKeyNot($vacacion->id)
                ->whereIn('estado', [EstadoVacacion::Pendiente->value, EstadoVacacion::Autorizada->value])
                ->where('fecha_inicio', '<=', $vacacion->fecha_fin->toDateString())
                ->where('fecha_fin', '>=', $vacacion->fecha_inicio->toDateString())
                ->with(['empleado:id,empresa_id,nombre', 'empleadosCobertura:id,empresa_id,nombre'])
                ->orderBy('fecha_inicio')
                ->orderBy('id')
                ->limit(50)
                ->get();

            foreach ($coincidenciasQuery as $coincidente) {
                $solicitudesCoincidentes[] = [
                    'empleado' => $coincidente->empleado->nombre,
                    'fecha_inicio' => $coincidente->fecha_inicio->toDateString(),
                    'fecha_fin' => $coincidente->fecha_fin->toDateString(),
                    'dias' => $coincidente->dias_solicitados,
                    'estado' => $coincidente->estado->label(),
                    'coberturas' => $coincidente->empleadosCobertura->pluck('nombre')->all(),
                ];
            }
        }

        $solicitante = $vacacion->solicitante_user_id === null
            ? null
            : $vacacion->solicitante;
        $revisor = $vacacion->resuelto_por_user_id === null
            ? null
            : $vacacion->resueltoPor;

        $datos = [
            'empresa' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
            'empleado' => $vacacion->empleado->nombre,
            'solicitante' => $solicitante === null ? $vacacion->solicitante_nombre : $solicitante->name,
            'fecha_inicio' => $vacacion->fecha_inicio->toDateString(),
            'fecha_fin' => $vacacion->fecha_fin->toDateString(),
            'dias_solicitados' => $vacacion->dias_solicitados,
            'saldo_dias' => $vacacion->saldo_dias_al_solicitar,
            'ultima_vacacion' => $vacacion->fecha_ultima_vacacion_al_solicitar?->toDateString(),
            'comentarios' => $vacacion->comentarios,
            'comentarios_rechazo' => $vacacion->comentarios_rechazo,
            'coberturas' => $vacacion->empleadosCobertura->pluck('nombre')->all(),
            'estado' => $vacacion->estado->label(),
            'resuelto_por' => $revisor?->name,
            'coincidencias' => $solicitudesCoincidentes,
        ];
        $mail = new NotificacionVacacionMail($this->tipo, $datos);

        if ($this->tipo === 'solicitud') {
            Mail::to($correosAdministradores)->send($mail);

            return;
        }

        $destinatarios = [];

        foreach ([
            $solicitante === null ? $vacacion->solicitante_correo : $solicitante->email,
            $vacacion->empleado->correo,
        ] as $destinatario) {
            if (trim($destinatario) === '') {
                continue;
            }

            $destinatario = trim($destinatario);
            $yaIncluido = collect($destinatarios)->contains(
                static fn (string $correo): bool => strcasecmp($correo, $destinatario) === 0,
            );

            if (! $yaIncluido) {
                $destinatarios[] = $destinatario;
            }
        }

        if ($destinatarios === []) {
            return;
        }

        $destinatariosNormalizados = array_map('strtolower', $destinatarios);
        $copias = array_values(array_filter(
            $correosAdministradores,
            static fn (string $correo): bool => ! in_array(strtolower($correo), $destinatariosNormalizados, true),
        ));
        $enviador = Mail::to($destinatarios);

        if ($copias !== []) {
            $enviador->cc($copias);
        }

        $enviador->send($mail);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('No se pudo enviar la notificación de vacaciones.', [
            'vacacion_id' => $this->vacacionId,
            'tipo' => $this->tipo,
            'error' => $exception?->getMessage(),
        ]);
    }
}
