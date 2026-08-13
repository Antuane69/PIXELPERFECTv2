<?php

namespace App\Services\Reportes;

use App\Services\Reportes\Drivers\ExcelDriver;
use App\Services\Reportes\Drivers\PdfDriver;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ExportService
{
    /**
     * @param  EloquentBuilder<covariant Model>|QueryBuilder|Collection<int, mixed>  $query
     */
    public function excelFromQuery(
        ExportConfig $config,
        EloquentBuilder|QueryBuilder|Collection $query,
        int $chunkSize = 1000,
    ): BinaryFileResponse {
        if ($query instanceof Collection) {
            $config->data($query);

            return (new ExcelDriver($config))->download();
        }

        return (new ExcelDriver($config))->downloadFromQuery($query, $chunkSize);
    }

    /**
     * @param  EloquentBuilder<covariant Model>|QueryBuilder|Collection<int, mixed>  $query
     */
    public function pdfFromQuery(
        ExportConfig $config,
        EloquentBuilder|QueryBuilder|Collection $query,
        int $maxRows = 5000,
    ): Response {
        if ($query instanceof Collection) {
            if ($query->count() > $maxRows) {
                return response()->json([
                    'error' => 'PDF excede límite de '.number_format($maxRows).' registros. Aplica filtros o descarga Excel.',
                ], 422);
            }

            $config->data($query);

            return (new PdfDriver($config))->download();
        }

        return (new PdfDriver($config))->downloadFromQuery($query, $maxRows);
    }
}
