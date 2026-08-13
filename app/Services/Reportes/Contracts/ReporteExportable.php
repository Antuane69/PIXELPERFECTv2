<?php

namespace App\Services\Reportes\Contracts;

use App\Services\Reportes\ExportConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

interface ReporteExportable
{
    /** @param array<string, mixed> $filtros */
    public function autorizar(Authenticatable $usuario, array $filtros): void;

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function validarFiltros(array $filtros): array;

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function query(array $filtros): Builder;

    /** @param array<string, mixed> $filtros */
    public function config(array $filtros, string $formato): ExportConfig;
}
