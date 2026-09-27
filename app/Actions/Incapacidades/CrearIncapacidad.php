<?php

namespace App\Actions\Incapacidades;

use App\Enums\Incapacidades\EstadoIncapacidad;
use App\Jobs\Incapacidades\EnviarNotificacionIncapacidad;
use App\Models\Empleado;
use App\Models\Incapacidad;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use App\Services\Empresas\ObtenerAdministradoresEmpresa;
use App\Services\ImageCompressor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CrearIncapacidad
{
    public function __construct(
        private readonly EmpresaContext $empresaContext,
        private readonly ObtenerAdministradoresEmpresa $obtenerAdministradores,
        private readonly ImageCompressor $imageCompressor,
    ) {}

    /**
     * @param  array{empleado_id?: int, fecha_inicio: string, fecha_fin: string, motivo: string, archivo?: UploadedFile}  $data
     */
    public function handle(User $solicitante, array $data): Incapacidad
    {
        $empresa = $this->empresaContext->empresaRequerida();

        if ($this->obtenerAdministradores->para($empresa)->isEmpty()) {
            throw ValidationException::withMessages([
                'solicitud' => 'La empresa no tiene un administrador activo con correo para recibir la solicitud.',
            ]);
        }

        $empleadoId = $solicitante->can('incapacidades.create_for_others')
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

        $datosArchivo = $this->datosArchivo($data['archivo'] ?? null);

        $incapacidad = DB::transaction(function () use (
            $data,
            $datosArchivo,
            $empresa,
            $empleadoId,
            $solicitante,
        ): Incapacidad {
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

            return Incapacidad::query()->create([
                'empresa_id' => $empresa->id,
                'empleado_id' => $empleado->id,
                'solicitante_user_id' => $solicitante->id,
                'solicitante_nombre' => $solicitante->name,
                'solicitante_correo' => $solicitante->email,
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'estado' => EstadoIncapacidad::Pendiente,
                'motivo' => $data['motivo'],
                ...$datosArchivo,
            ]);
        });

        EnviarNotificacionIncapacidad::dispatch($incapacidad->id, 'solicitud')->afterCommit();

        return $incapacidad;
    }

    /**
     * @return array{nombre_original?: string, mime_type?: string, extension?: string, archivo?: string}
     */
    private function datosArchivo(?UploadedFile $archivo): array
    {
        if ($archivo === null) {
            return [];
        }

        try {
            $comprimido = $this->imageCompressor->compressIfImage(
                $archivo,
                enforceSourcePixelLimit: true,
            );
            $contenido = $comprimido['contents'] ?? $archivo->getContent();
            $extension = $comprimido['extension'] ?? Str::lower($archivo->getClientOriginalExtension());
            $mimeType = $comprimido['mime_type'] ?? match ($extension) {
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'pdf' => 'application/pdf',
                default => $archivo->getMimeType(),
            };
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo procesar el justificante. Comprueba que el archivo sea una imagen válida.',
            ]);
        }

        if (! is_string($mimeType) || $contenido === '') {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el justificante seleccionado.',
            ]);
        }

        return [
            'nombre_original' => $this->nombreOriginalSeguro($archivo, $extension),
            'mime_type' => $mimeType,
            'extension' => Str::lower($extension),
            'archivo' => $contenido,
        ];
    }

    private function nombreOriginalSeguro(UploadedFile $archivo, string $extension): string
    {
        $nombre = basename(str_replace('\\', '/', $archivo->getClientOriginalName()));
        $nombre = preg_replace('/[\x00-\x1F\x7F]/u', '', $nombre) ?? '';
        $nombreBase = trim(pathinfo($nombre, PATHINFO_FILENAME));

        if ($nombreBase === '' || $nombreBase === '.' || $nombreBase === '..') {
            $nombreBase = 'justificante';
        }

        $extension = Str::lower($extension);
        $maxNombreBaseLength = max(1, 255 - mb_strlen($extension) - 1);

        return mb_substr($nombreBase, 0, $maxNombreBaseLength).'.'.$extension;
    }
}
