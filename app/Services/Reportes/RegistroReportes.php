<?php

namespace App\Services\Reportes;

use App\Services\Reportes\Contracts\ReporteExportable;
use App\Services\Reportes\Definiciones\EmpleadosReporte;
use App\Services\Reportes\Definiciones\PuestosReporte;
use App\Services\Reportes\Definiciones\RolesReporte;
use App\Services\Reportes\Definiciones\TiposDocumentoEmpleadoReporte;
use App\Services\Reportes\Definiciones\UsuariosReporte;
use Illuminate\Contracts\Container\Container;

class RegistroReportes
{
    /** @var array<string, class-string<ReporteExportable>> */
    private const REPORTES = [
        'empleados' => EmpleadosReporte::class,
        'puestos' => PuestosReporte::class,
        'roles' => RolesReporte::class,
        'tipos-documento-empleados' => TiposDocumentoEmpleadoReporte::class,
        'usuarios' => UsuariosReporte::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function obtener(string $reporte): ReporteExportable
    {
        abort_unless(isset(self::REPORTES[$reporte]), 404, 'Reporte no disponible.');

        return $this->container->make(self::REPORTES[$reporte]);
    }
}
