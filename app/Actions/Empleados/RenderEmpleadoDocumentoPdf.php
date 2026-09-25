<?php

namespace App\Actions\Empleados;

use App\Models\Empleado;
use App\Models\EmpleadoDocumentoCatalogo;
use App\Services\Empleados\EmpleadoDocumentoVariables;
use App\Services\Empleados\SanitizeEmpleadoDocumentoHtml;
use Barryvdh\DomPDF\Facade\Pdf;

class RenderEmpleadoDocumentoPdf
{
    public function __construct(
        private readonly SanitizeEmpleadoDocumentoHtml $sanitizeHtml,
        private readonly EmpleadoDocumentoVariables $variables,
    ) {}

    public function renderHtml(EmpleadoDocumentoCatalogo $documento, ?Empleado $empleado = null): string
    {
        $html = $this->sanitizeHtml->handle($documento->contenido_html, compressImages: false);

        if ($empleado === null) {
            return $html;
        }

        $values = $this->variables->values($empleado);

        return preg_replace_callback(
            '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i',
            static fn (array $match): string => e($values[strtolower($match[1])] ?? ''),
            $html,
        ) ?? $html;
    }

    public function pdf(EmpleadoDocumentoCatalogo $documento, ?Empleado $empleado = null): string
    {
        $documento->loadMissing('empresa:id,nombre_comercial,nombre_legal');

        return Pdf::loadView('empleados.documentos-catalogo.pdf', [
            'contenidoHtml' => $this->renderHtml($documento, $empleado),
            'nombreEmpresa' => $documento->empresa?->stripeName() ?? '',
            'nombreDocumento' => $documento->nombre,
        ])
            ->setPaper('letter')
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])
            ->output();
    }
}
