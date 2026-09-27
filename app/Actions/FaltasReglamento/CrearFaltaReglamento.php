<?php

namespace App\Actions\FaltasReglamento;

use App\Enums\FaltasReglamento\EstadoFaltaReglamento;
use App\Models\Empleado;
use App\Models\FaltasReglamento\FaltaReglamento;
use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use App\Services\ImageCompressor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CrearFaltaReglamento
{
    public function __construct(
        private readonly EmpresaContext $empresaContext,
        private readonly ImageCompressor $imageCompressor,
    ) {}

    /** @param array{empleado_id?: int, falta_reglamento_catalogo_id: int, fecha_ocurrencia: string, comentarios?: string|null, archivos?: list<UploadedFile>} $data */
    public function handle(User $solicitante, array $data): FaltaReglamento
    {
        $empresa = $this->empresaContext->empresaRequerida();
        $canCreateForOthers = $solicitante->can('faltas_reglamento.create_for_others');
        $empleadoId = $canCreateForOthers
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

        $files = $data['archivos'] ?? [];

        return DB::transaction(function () use ($data, $empresa, $empleadoId, $solicitante, $files): FaltaReglamento {
            $empleado = Empleado::query()
                ->where('empresa_id', $empresa->id)
                ->whereNull('deleted_at')
                ->whereKey($empleadoId)
                ->lockForUpdate()
                ->first();

            if (! $empleado instanceof Empleado) {
                throw ValidationException::withMessages([
                    'empleado_id' => 'El empleado seleccionado ya no está disponible en esta empresa.',
                ]);
            }

            $faltaCatalogo = FaltaReglamentoCatalogo::query()
                ->where('empresa_id', $empresa->id)
                ->where('activo', true)
                ->whereNull('deleted_at')
                ->whereKey($data['falta_reglamento_catalogo_id'])
                ->whereHas('tipoFalta', static fn ($query) => $query
                    ->where('activo', true)
                    ->whereNull('deleted_at'))
                ->lockForUpdate()
                ->first();

            if (! $faltaCatalogo instanceof FaltaReglamentoCatalogo) {
                throw ValidationException::withMessages([
                    'falta_reglamento_catalogo_id' => 'La falta seleccionada ya no está disponible.',
                ]);
            }

            $falta = FaltaReglamento::query()->create([
                'empresa_id' => $empresa->id,
                'empleado_id' => $empleado->id,
                'falta_reglamento_catalogo_id' => $faltaCatalogo->id,
                'solicitante_user_id' => $solicitante->id,
                'solicitante_nombre' => $solicitante->name,
                'solicitante_correo' => $solicitante->email,
                'fecha_ocurrencia' => $data['fecha_ocurrencia'],
                'estado' => EstadoFaltaReglamento::Pendiente,
                'comentarios' => $data['comentarios'] ?? null,
            ]);

            foreach ($files as $file) {
                $this->saveEvidence($falta, $file, $empresa->id);
            }

            return $falta;
        });
    }

    private function saveEvidence(FaltaReglamento $falta, UploadedFile $file, int $empresaId): void
    {
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $compressedImage = null;

        if (str_starts_with($mimeType, 'image/')) {
            try {
                $compressedImage = $this->imageCompressor->compressIfImage($file);
            } catch (RuntimeException $exception) {
                if (str_contains($exception->getMessage(), 'supera el límite de píxeles')) {
                    throw ValidationException::withMessages([
                        'archivos' => 'Una de las imágenes adjuntas supera el límite de procesamiento permitido.',
                    ]);
                }
            }
        }

        $contents = $compressedImage['contents'] ?? $file->getContent();
        $effectiveMimeType = $compressedImage['mime_type'] ?? $mimeType;
        $extension = $compressedImage['extension'] ?? strtolower($file->getClientOriginalExtension());
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);

        if ($contents === '' || $extension === '') {
            throw new RuntimeException('No se pudo leer la evidencia adjunta.');
        }

        $falta->evidencias()->create([
            'empresa_id' => $empresaId,
            'nombre' => $this->safeOriginalName($file),
            'mime_type' => $effectiveMimeType,
            'file_extension' => Str::limit($extension, 20, ''),
            'archivo' => $contents,
        ]);
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $basename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = Str::of($basename)
            ->replaceMatches('/[^\pL\pN._ -]/u', '_')
            ->squish()
            ->limit(255, '')
            ->toString();

        return $name !== '' ? $name : 'evidencia-'.Str::random(12);
    }
}
