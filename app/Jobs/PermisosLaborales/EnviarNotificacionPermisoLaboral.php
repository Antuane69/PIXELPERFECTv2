<?php

namespace App\Jobs\PermisosLaborales;

use App\Enums\PermisosLaborales\EstadoPermisoLaboral;
use App\Mail\PermisosLaborales\NotificacionPermisoLaboralMail;
use App\Models\Empresa;
use App\Models\PermisosLaborales\PermisoLaboral;
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

class EnviarNotificacionPermisoLaboral implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $permisoLaboralId,
        public readonly string $tipo,
    ) {}

    public function handle(ObtenerAdministradoresEmpresa $obtenerAdministradores): void
    {
        if (! in_array($this->tipo, ['solicitud', 'resolucion'], true)) {
            throw new RuntimeException('El tipo de notificación de permisos laborales no es válido.');
        }

        $permisoLaboral = PermisoLaboral::query()
            ->with([
                'empresa:id,nombre_legal,nombre_comercial,zona_horaria',
                'tipoPermiso:id,empresa_id,nombre,descripcion,activo,deleted_at',
                'empleado:id,empresa_id,nombre,correo',
                'solicitante:id,name,email',
                'resueltoPor:id,name',
                'empleadosCobertura:id,empresa_id,nombre',
            ])
            ->find($this->permisoLaboralId);

        if (! $permisoLaboral instanceof PermisoLaboral) {
            return;
        }

        $empresa = $permisoLaboral->empresa;

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
            $coincidencias = PermisoLaboral::query()
                ->where('empresa_id', $empresa->id)
                ->whereKeyNot($permisoLaboral->id)
                ->whereIn('estado', [EstadoPermisoLaboral::Pendiente->value, EstadoPermisoLaboral::Autorizado->value])
                ->where('fecha_inicio', '<=', $permisoLaboral->fecha_fin->toDateString())
                ->where('fecha_fin', '>=', $permisoLaboral->fecha_inicio->toDateString())
                ->with([
                    'empleado:id,empresa_id,nombre',
                    'tipoPermiso:id,empresa_id,nombre,descripcion,activo,deleted_at',
                    'empleadosCobertura:id,empresa_id,nombre',
                ])
                ->orderBy('fecha_inicio')
                ->orderBy('id')
                ->limit(50)
                ->get();

            foreach ($coincidencias as $coincidente) {
                $solicitudesCoincidentes[] = [
                    'empleado' => $coincidente->empleado->nombre,
                    'tipo_permiso' => $coincidente->tipoPermiso?->nombre,
                    'fecha_inicio' => $coincidente->fecha_inicio->toDateString(),
                    'fecha_fin' => $coincidente->fecha_fin->toDateString(),
                    'estado' => $coincidente->estado->label(),
                    'coberturas' => $coincidente->empleadosCobertura->pluck('nombre')->all(),
                ];
            }
        }

        $solicitante = $permisoLaboral->solicitante_user_id === null
            ? null
            : $permisoLaboral->solicitante;
        $revisor = $permisoLaboral->resuelto_por_user_id === null
            ? null
            : $permisoLaboral->resueltoPor;
        $datos = [
            'empresa' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
            'empleado' => $permisoLaboral->empleado->nombre,
            'tipo_permiso' => $permisoLaboral->tipoPermiso->nombre ?? 'Sin tipo registrado',
            'solicitante' => $solicitante === null ? $permisoLaboral->solicitante_nombre : $solicitante->name,
            'fecha_inicio' => $permisoLaboral->fecha_inicio->toDateString(),
            'fecha_fin' => $permisoLaboral->fecha_fin->toDateString(),
            'comentarios' => $permisoLaboral->comentarios,
            'comentarios_rechazo' => $permisoLaboral->comentarios_rechazo,
            'coberturas' => $permisoLaboral->empleadosCobertura->pluck('nombre')->all(),
            'estado' => $permisoLaboral->estado->label(),
            'resuelto_por' => $revisor?->name,
            'coincidencias' => $solicitudesCoincidentes,
        ];
        $mail = new NotificacionPermisoLaboralMail($this->tipo, $datos);

        if ($this->tipo === 'solicitud') {
            Mail::to($correosAdministradores)->send($mail);

            return;
        }

        $destinatarios = [];

        foreach ([
            $solicitante === null ? $permisoLaboral->solicitante_correo : $solicitante->email,
            $permisoLaboral->empleado->correo,
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
        Log::error('No se pudo enviar la notificación de permisos laborales.', [
            'permiso_laboral_id' => $this->permisoLaboralId,
            'tipo' => $this->tipo,
            'error' => $exception?->getMessage(),
        ]);
    }
}
