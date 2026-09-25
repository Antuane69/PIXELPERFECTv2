<?php

namespace App\Actions\Empleados;

use App\Models\Empleado;
use App\Models\EmpleadoDocumentoCatalogo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

class DownloadEmpleadoDocumentos
{
    public function __construct(private readonly RenderEmpleadoDocumentoPdf $renderPdf) {}

    /**
     * @param  Collection<int, EmpleadoDocumentoCatalogo>  $documentos
     */
    public function handle(Empleado $empleado, Collection $documentos): Response
    {
        $documentos->loadMissing('empresa:id,nombre_comercial,nombre_legal');

        if ($documentos->count() === 1) {
            $documento = $documentos->firstOrFail();
            $pdf = $this->renderPdf->pdf($documento, $empleado);

            return response()->streamDownload(
                static function () use ($pdf): void {
                    echo $pdf;
                },
                $this->pdfFileName($documento),
                ['Content-Type' => 'application/pdf'],
            );
        }

        $archivePath = tempnam(sys_get_temp_dir(), 'empleados-documentos-');

        if ($archivePath === false) {
            throw new RuntimeException('No fue posible preparar la descarga de documentos.');
        }

        $archive = new ZipArchive;

        try {
            if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible crear el archivo ZIP.');
            }

            foreach ($documentos as $documento) {
                $pdf = $this->renderPdf->pdf($documento, $empleado);

                if (! $archive->addFromString($this->pdfFileName($documento), $pdf)) {
                    throw new RuntimeException('No fue posible agregar un documento al archivo ZIP.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('No fue posible finalizar el archivo ZIP.');
            }

            $employeeName = Str::slug($empleado->nombre) ?: 'empleado';

            return response()->download(
                $archivePath,
                'documentos-'.$employeeName.'.zip',
                ['Content-Type' => 'application/zip'],
            )->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            if (file_exists($archivePath)) {
                unlink($archivePath);
            }

            throw $exception;
        }
    }

    private function pdfFileName(EmpleadoDocumentoCatalogo $documento): string
    {
        $documentName = Str::slug($documento->nombre) ?: 'documento';

        return $documento->id.'-'.$documentName.'.pdf';
    }
}
