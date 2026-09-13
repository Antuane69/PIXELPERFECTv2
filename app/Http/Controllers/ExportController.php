<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reportes\ExportarReporteRequest;
use App\Services\Empresas\EmpresaContext;
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
        private readonly EmpresaContext $empresaContext,
    ) {}

    public function exportar(ExportarReporteRequest $request, string $reporte): Response
    {
        return $this->generar($request, $reporte);
    }

    public function exportarPuestos(ExportarReporteRequest $request): Response
    {
        return $this->generar($request, 'puestos');
    }

    public function exportarUsuarios(ExportarReporteRequest $request): Response
    {
        return $this->generar($request, 'usuarios');
    }

    public function exportarRoles(ExportarReporteRequest $request): Response
    {
        return $this->generar($request, 'roles');
    }

    public function exportarEmpleados(ExportarReporteRequest $request): Response
    {
        return $this->generar($request, 'empleados');
    }

    public function exportarTiposDocumentoEmpleado(ExportarReporteRequest $request): Response
    {
        return $this->generar($request, 'tipos-documento-empleados');
    }

    private function generar(ExportarReporteRequest $request, string $reporte): Response
    {
        $formato = $request->string('formato')->toString();
        $definicion = $this->registroReportes->obtener($reporte);
        $moduleKey = $this->registroReportes->modulo($reporte);

        if ($moduleKey !== null && ! $request->user()?->es_superadministrador_plataforma) {
            abort_unless(
                $this->empresaContext->empresaRequerida()->moduloHabilitado($moduleKey),
                403,
                'Este módulo no está habilitado para la empresa activa.',
            );
        }

        $filtros = $definicion->validarFiltros($request->safe()->input('filtros', []));
        $usuario = $request->user();

        abort_unless($usuario instanceof Authenticatable, 401);

        $definicion->autorizar($usuario, $filtros);

        $query = $definicion->query($filtros);
        $config = $definicion->config($filtros, $formato);
        $empresa = $this->empresaContext->empresa();

        if ($empresa !== null) {
            $config
                ->brandName($empresa->nombre_comercial ?: $empresa->nombre_legal)
                ->logoContents($empresa->logo, $empresa->logo_mime_type);
        }

        return match ($formato) {
            'xlsx' => $this->exportService->excelFromQuery($config, $query),
            'pdf' => $this->exportService->pdfFromQuery($config, $query),
            default => throw new LogicException('Formato validado no soportado.'),
        };
    }
}
