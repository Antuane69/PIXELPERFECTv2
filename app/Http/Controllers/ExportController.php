<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reportes\ExportarReporteRequest;
use App\Services\Reportes\ExportService;
use App\Services\Reportes\RegistroReportes;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function __construct(
        private readonly RegistroReportes $registroReportes,
        private readonly ExportService $exportService,
    ) {}

    public function exportar(ExportarReporteRequest $request, string $reporte): Response
    {
        $formato = $request->string('formato')->toString();
        $definicion = $this->registroReportes->obtener($reporte);
        $filtros = $definicion->validarFiltros($request->safe()->input('filtros', []));
        $usuario = $request->user();

        abort_unless($usuario instanceof Authenticatable, 401);

        $definicion->autorizar($usuario, $filtros);

        $query = $definicion->query($filtros);
        $config = $definicion->config($filtros, $formato);

        return match ($formato) {
            'xlsx' => $this->exportService->excelFromQuery($config, $query),
            'pdf' => $this->exportService->pdfFromQuery($config, $query),
            default => throw new LogicException('Formato validado no soportado.'),
        };
    }
}
