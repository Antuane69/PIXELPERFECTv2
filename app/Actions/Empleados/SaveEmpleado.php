<?php

namespace App\Actions\Empleados;

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SaveEmpleado
{
    private const PRIVATE_DISK = 'local';

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Empleado $empleado = null): Empleado
    {
        $storedFiles = [];
        $obsoleteFiles = [];

        try {
            $savedEmpleado = DB::transaction(function () use (
                $data,
                $empleado,
                &$storedFiles,
                &$obsoleteFiles,
            ): Empleado {
                $lockedEmpleado = $empleado?->exists
                    ? Empleado::query()
                        ->whereKey($empleado->getKey())
                        ->lockForUpdate()
                        ->firstOrFail()
                    : new Empleado;
                $avatar = $data['avatar'] ?? null;
                $documentos = is_array($data['documentos'] ?? null) ? $data['documentos'] : [];

                $lockedEmpleado->fill(Arr::except($data, [
                    'avatar',
                    'documentos',
                    'fecha_contrato_siguiente',
                    'fecha_contrato_indefinido',
                    'fecha_ultimo_aviso',
                    'fecha_evaluacion',
                    'fecha_inicio_contrato',
                    'fecha_termino_contrato',
                ]));
                $this->applyTrialContractDates($lockedEmpleado);
                $lockedEmpleado->save();

                if ($avatar instanceof UploadedFile) {
                    $avatarPath = $this->storeFile(
                        $avatar,
                        "empleados/{$lockedEmpleado->getKey()}/avatar",
                    );
                    $storedFiles[] = ['disk' => self::PRIVATE_DISK, 'path' => $avatarPath];

                    if (is_string($lockedEmpleado->avatar) && $lockedEmpleado->avatar !== '') {
                        $obsoleteFiles[] = [
                            'disk' => self::PRIVATE_DISK,
                            'path' => $lockedEmpleado->avatar,
                        ];
                    }

                    $lockedEmpleado->avatar = $avatarPath;
                    $lockedEmpleado->save();
                }

                foreach ($documentos as $documentoData) {
                    if (! is_array($documentoData)) {
                        continue;
                    }

                    $tipoDocumentoId = (int) ($documentoData['tipo_documento_empleado_id'] ?? 0);

                    if ($tipoDocumentoId < 1) {
                        continue;
                    }

                    $documento = EmpleadoDocumento::withTrashed()
                        ->whereBelongsTo($lockedEmpleado)
                        ->where('tipo_documento_empleado_id', $tipoDocumentoId)
                        ->lockForUpdate()
                        ->first();
                    $archivo = $documentoData['archivo'] ?? null;

                    if (! $archivo instanceof UploadedFile) {
                        if ($documento !== null && array_key_exists('vence_el', $documentoData)) {
                            $documento->vence_el = $documentoData['vence_el'];
                            $documento->save();
                        }

                        continue;
                    }

                    $newPath = $this->storeFile(
                        $archivo,
                        "empleados/{$lockedEmpleado->getKey()}/documentos",
                    );
                    $storedFiles[] = ['disk' => self::PRIVATE_DISK, 'path' => $newPath];

                    if ($documento !== null && $documento->ruta !== '') {
                        $obsoleteFiles[] = [
                            'disk' => $documento->disco,
                            'path' => $documento->ruta,
                        ];
                    }

                    $documento ??= new EmpleadoDocumento;
                    $documento->fill([
                        'empleado_id' => $lockedEmpleado->getKey(),
                        'tipo_documento_empleado_id' => $tipoDocumentoId,
                        'nombre_original' => $this->safeOriginalName($archivo),
                        'ruta' => $newPath,
                        'disco' => self::PRIVATE_DISK,
                        'mime_type' => $archivo->getMimeType() ?: 'application/octet-stream',
                        'tamano' => $archivo->getSize() ?: 0,
                        'vence_el' => $documentoData['vence_el'] ?? null,
                    ]);
                    $documento->deleted_at = null;
                    $documento->save();
                }

                return $lockedEmpleado;
            });
        } catch (Throwable $exception) {
            $this->deleteFiles($storedFiles);

            throw $exception;
        }

        $this->deleteFiles($obsoleteFiles);

        return $savedEmpleado->refresh()->load(['puesto', 'documentos.tipoDocumento']);
    }

    private function storeFile(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, self::PRIVATE_DISK);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No se pudo almacenar el archivo privado del empleado.');
        }

        return $path;
    }

    private function applyTrialContractDates(Empleado $empleado): void
    {
        $fechaIngreso = $empleado->getAttribute('fecha_ingreso');
        $periodoPruebaMeses = $empleado->getAttribute('periodo_prueba_meses');

        if (! $fechaIngreso instanceof DateTimeInterface || ! is_int($periodoPruebaMeses)) {
            return;
        }

        $fechaIngreso = CarbonImmutable::instance($fechaIngreso);

        $empleado->fecha_contrato_siguiente = $fechaIngreso->addMonthNoOverflow()->toDateString();
        $empleado->fecha_contrato_indefinido = $fechaIngreso
            ->addMonthsNoOverflow($periodoPruebaMeses)
            ->toDateString();
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $basename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $sanitized = Str::of($basename)
            ->replaceMatches('/[^\pL\pN._ -]/u', '_')
            ->squish()
            ->limit(255, '')
            ->toString();

        if ($sanitized !== '') {
            return $sanitized;
        }

        return 'documento-'.Str::random(12).'.'.($file->guessExtension() ?: 'bin');
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $files
     */
    private function deleteFiles(array $files): void
    {
        $deleted = [];

        foreach ($files as $file) {
            $key = $file['disk'].'|'.$file['path'];

            if (isset($deleted[$key])) {
                continue;
            }

            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (Throwable $exception) {
                report($exception);
            }

            $deleted[$key] = true;
        }
    }
}
