<?php

namespace App\Jobs\FaltasReglamento;

use App\Enums\FaltasReglamento\EstadoFaltaReglamento;
use App\Mail\FaltasReglamento\NotificacionFaltaReglamentoMail;
use App\Models\Empresa;
use App\Models\FaltasReglamento\FaltaReglamento;
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

class EnviarNotificacionFaltaReglamento implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $faltaReglamentoId,
        public readonly string $tipo,
    ) {}

    public function handle(ObtenerAdministradoresEmpresa $obtenerAdministradores): void
    {
        if (! in_array($this->tipo, ['solicitud', 'resolucion'], true)) {
            throw new RuntimeException('El tipo de notificación de faltas al reglamento no es válido.');
        }

        $falta = FaltaReglamento::query()
            ->with([
                'empresa:id,nombre_legal,nombre_comercial,zona_horaria',
                'empleado:id,empresa_id,user_id,nombre,nombre_usuario,correo,puesto_id,deleted_at',
                'empleado.usuario:id,name,email',
                'empleado.puesto:id,nombre',
                'faltaCatalogo:id,empresa_id,tipo_falta_reglamento_id,nombre,descripcion,deleted_at',
                'faltaCatalogo.tipoFalta:id,empresa_id,nombre,descripcion,deleted_at',
                'solicitante:id,name,email',
                'resueltoPor:id,name',
                'evidencias:id,empresa_id,falta_reglamento_id,nombre,mime_type,file_extension',
            ])
            ->find($this->faltaReglamentoId);

        if (! $falta instanceof FaltaReglamento) {
            return;
        }

        $empresa = $falta->empresa;
        $empleado = $falta->empleado;
        $catalogo = $falta->faltaCatalogo;

        if (! $empresa instanceof Empresa || $empleado === null || $catalogo === null) {
            return;
        }

        $administradores = $obtenerAdministradores->para($empresa);
        $correosAdministradores = $this->correosUnicos($administradores->pluck('email')->all());

        if ($this->tipo === 'solicitud' && $correosAdministradores === []) {
            throw new RuntimeException('No hay administradores de empresa con correo para recibir el reporte.');
        }

        $solicitante = $falta->solicitante_user_id === null ? null : $falta->solicitante;
        $revisor = $falta->resuelto_por_user_id === null ? null : $falta->resueltoPor;
        $usuarioEmpleado = $empleado->usuario;
        $puesto = $empleado->puesto;
        $tipoFalta = $catalogo->tipoFalta;
        $datos = [
            'empresa' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
            'registro' => [
                'id' => $falta->id,
                'fecha_registro' => $falta->created_at?->timezone($empresa->zona_horaria)->format('Y-m-d H:i'),
                'fecha_ocurrencia' => $falta->fecha_ocurrencia->toDateString(),
                'tipo_falta' => $tipoFalta->nombre,
                'descripcion_tipo_falta' => $tipoFalta->descripcion,
                'falta' => $catalogo->nombre,
                'descripcion_falta' => $catalogo->descripcion,
                'estado' => $this->tipo === 'solicitud'
                    ? EstadoFaltaReglamento::Pendiente->label()
                    : $falta->estado->label(),
                'comentarios' => $falta->comentarios,
                'comentarios_rechazo' => $this->tipo === 'solicitud' ? null : $falta->comentarios_rechazo,
                'solicitante' => $solicitante?->name ?: $falta->solicitante_nombre,
                'correo_solicitante' => $solicitante?->email ?: $falta->solicitante_correo,
                'resuelto_por' => $this->tipo === 'solicitud' ? null : $revisor?->name,
                'resuelto_at' => $this->tipo === 'solicitud'
                    ? null
                    : $falta->resuelto_at?->timezone($empresa->zona_horaria)->format('Y-m-d H:i'),
                'evidencias' => $falta->evidencias->map(static fn ($evidencia): array => [
                    'nombre' => $evidencia->nombre,
                    'mime_type' => $evidencia->mime_type,
                    'extension' => $evidencia->file_extension,
                ])->all(),
            ],
            'empleado' => [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre,
                'usuario' => $empleado->nombre_usuario,
                'nombre_cuenta' => $usuarioEmpleado?->name,
                'correo' => $empleado->correo,
                'correo_cuenta' => $usuarioEmpleado?->email,
                'puesto' => $puesto?->nombre,
                'expediente' => $empleado->deleted_at === null ? 'Activo' : 'Archivado',
            ],
        ];

        $correoEmpleado = $usuarioEmpleado?->email ?: $empleado->correo;
        $correosPrincipales = $this->tipo === 'solicitud'
            ? $correosAdministradores
            : $this->correosUnicos([
                $solicitante?->email ?: $falta->solicitante_correo,
                $correoEmpleado,
            ]);

        if ($correosPrincipales === []) {
            $correosPrincipales = $correosAdministradores;
        }

        $correosCopia = $this->tipo === 'solicitud'
            ? $this->excluirDestinatarios($this->correosUnicos([
                $solicitante?->email ?: $falta->solicitante_correo,
                $correoEmpleado,
            ]), $correosPrincipales)
            : $this->excluirDestinatarios($correosAdministradores, $correosPrincipales);

        if ($correosPrincipales === []) {
            return;
        }

        $enviador = Mail::to($correosPrincipales);

        if ($correosCopia !== []) {
            $enviador->cc($correosCopia);
        }

        $enviador->send(new NotificacionFaltaReglamentoMail($this->tipo, $datos));
    }

    /**
     * @param  array<array-key, mixed>  $correos
     * @return list<string>
     */
    private function correosUnicos(array $correos): array
    {
        $correosNormalizados = [];

        foreach ($correos as $correo) {
            if (! is_string($correo) || trim($correo) === '') {
                continue;
            }

            $correo = trim($correo);
            $clave = strtolower($correo);

            if (! array_key_exists($clave, $correosNormalizados)) {
                $correosNormalizados[$clave] = $correo;
            }
        }

        return array_values($correosNormalizados);
    }

    /**
     * @param  list<string>  $correos
     * @param  list<string>  $destinatarios
     * @return list<string>
     */
    private function excluirDestinatarios(array $correos, array $destinatarios): array
    {
        $correosDestinatarios = array_map('strtolower', $destinatarios);

        return array_values(array_filter(
            $correos,
            static fn (string $correo): bool => ! in_array(strtolower($correo), $correosDestinatarios, true),
        ));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('No se pudo enviar la notificación de faltas al reglamento.', [
            'falta_reglamento_id' => $this->faltaReglamentoId,
            'tipo' => $this->tipo,
            'error' => $exception?->getMessage(),
        ]);
    }
}
