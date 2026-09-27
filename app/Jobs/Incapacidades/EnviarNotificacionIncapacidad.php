<?php

namespace App\Jobs\Incapacidades;

use App\Mail\Incapacidades\NotificacionIncapacidadMail;
use App\Models\Empresa;
use App\Models\Incapacidad;
use App\Services\Empresas\ObtenerAdministradoresEmpresa;
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

class EnviarNotificacionIncapacidad implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $incapacidadId,
        public readonly string $tipo,
    ) {}

    public function handle(ObtenerAdministradoresEmpresa $obtenerAdministradores): void
    {
        if (! in_array($this->tipo, ['solicitud', 'resolucion'], true)) {
            throw new RuntimeException('El tipo de notificación de incapacidades no es válido.');
        }

        $incapacidad = Incapacidad::query()
            ->select([
                'id',
                'empresa_id',
                'empleado_id',
                'solicitante_user_id',
                'resuelto_por_user_id',
                'solicitante_nombre',
                'solicitante_correo',
                'fecha_inicio',
                'fecha_fin',
                'estado',
                'motivo',
                'comentarios_rechazo',
                'resuelto_at',
                'nombre_original',
                'mime_type',
                'extension',
            ])
            ->with([
                'empresa:id,nombre_legal,nombre_comercial,zona_horaria',
                'empleado:id,empresa_id,nombre,correo',
                'solicitante:id,name,email',
                'resueltoPor:id,name',
            ])
            ->find($this->incapacidadId);

        if (! $incapacidad instanceof Incapacidad) {
            return;
        }

        $empresa = $incapacidad->empresa;

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

        $solicitante = $incapacidad->solicitante_user_id === null
            ? null
            : $incapacidad->solicitante;
        $revisor = $incapacidad->resuelto_por_user_id === null
            ? null
            : $incapacidad->resueltoPor;
        $datos = [
            'empresa' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
            'empleado' => $incapacidad->empleado->nombre,
            'solicitante' => $solicitante?->name ?: $incapacidad->solicitante_nombre,
            'fecha_inicio' => $incapacidad->fecha_inicio->toDateString(),
            'fecha_fin' => $incapacidad->fecha_fin->toDateString(),
            'motivo' => $incapacidad->motivo,
            'nombre_archivo' => $incapacidad->nombre_original,
            'estado' => $incapacidad->estado->label(),
            'resuelto_por' => $revisor?->name,
            'comentarios_rechazo' => $incapacidad->comentarios_rechazo,
        ];
        $mail = new NotificacionIncapacidadMail($this->tipo, $datos);

        if ($this->tipo === 'solicitud') {
            Mail::to($correosAdministradores)->send($mail);

            return;
        }

        $destinatarios = [];

        foreach ([
            $solicitante?->email ?: $incapacidad->solicitante_correo,
            $incapacidad->empleado->correo,
        ] as $destinatario) {
            $destinatario = trim($destinatario);

            if ($destinatario === '') {
                continue;
            }

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
        Log::error('No se pudo enviar la notificación de incapacidades.', [
            'incapacidad_id' => $this->incapacidadId,
            'tipo' => $this->tipo,
            'error' => $exception?->getMessage(),
        ]);
    }
}
