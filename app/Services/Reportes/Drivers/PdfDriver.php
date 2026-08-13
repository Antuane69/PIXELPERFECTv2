<?php

namespace App\Services\Reportes\Drivers;

use App\Services\Reportes\ExportConfig;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class PdfDriver
{
    private const DEFAULT_MAX_ROWS = 5000;

    public function __construct(private readonly ExportConfig $config) {}

    public function download(): Response
    {
        return $this->buildPdf()->download($this->config->getFileName().'.pdf');
    }

    /** @param EloquentBuilder<covariant Model>|QueryBuilder $query */
    public function downloadFromQuery(
        EloquentBuilder|QueryBuilder $query,
        int $maxRows = self::DEFAULT_MAX_ROWS,
    ): Response {
        $totalRows = (clone $query)->count();

        if ($totalRows > $maxRows) {
            return response()->json([
                'error' => 'PDF excede límite de '.number_format($maxRows).' registros. Aplica filtros o descarga Excel.',
            ], 422);
        }

        $this->config->data($query->get());

        return $this->download();
    }

    private function buildPdf(): DomPdf
    {
        $pdf = Pdf::loadView('exports.pdf-base', [
            'title' => $this->config->getTitle(),
            'subtitle' => $this->config->getSubtitle(),
            'headings' => $this->config->getHeadings(),
            'rows' => $this->config->getRows(),
            'logoPath' => $this->config->getLogoPath(),
            'totalRows' => $this->config->getData()->count(),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'htmlColumnIndexes' => $this->config->getPdfHtmlColumnIndexes(),
            'fixedTableLayout' => $this->config->usesFixedPdfTableLayout(),
            'columnWidths' => $this->config->getPdfColumnWidths(),
        ]);

        $orientation = count($this->config->getHeadings()) > 6 ? 'landscape' : 'portrait';

        return $pdf->setPaper('letter', $orientation);
    }
}
