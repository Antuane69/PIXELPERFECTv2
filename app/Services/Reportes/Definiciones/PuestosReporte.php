<?php

namespace App\Services\Reportes\Definiciones;

use App\Models\Puesto;
use App\Services\Reportes\Contracts\ReporteExportable;
use App\Services\Reportes\ExportConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PuestosReporte implements ReporteExportable
{
    /** @param array<string, mixed> $filtros */
    public function autorizar(Authenticatable $usuario, array $filtros): void
    {
        Gate::forUser($usuario)->authorize('viewAny', Puesto::class);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function validarFiltros(array $filtros): array
    {
        return Validator::validate($filtros, [
            'search' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            'archivados' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Puesto>
     */
    public function query(array $filtros): Builder
    {
        $search = Str::squish((string) ($filtros['search'] ?? ''));

        return Puesto::query()
            ->select(['id', 'nombre', 'salario_dia', 'salario_quincena', 'activo', 'deleted_at'])
            ->withCount('empleados')
            ->when((bool) ($filtros['archivados'] ?? false), fn (Builder $query) => $query->onlyTrashed())
            ->when($search !== '', fn (Builder $query) => $query->where('nombre', 'like', "%{$search}%"))
            ->when(
                array_key_exists('activo', $filtros) && $filtros['activo'] !== null,
                fn (Builder $query) => $query->where('activo', (bool) $filtros['activo']),
            )
            ->orderByDesc('id');
    }

    /** @param array<string, mixed> $filtros */
    public function config(array $filtros, string $formato): ExportConfig
    {
        return ExportConfig::make()
            ->title('Puestos')
            ->fileName('puestos_'.now()->format('Ymd_His'))
            ->sheetName('Puestos')
            ->columns([
                'id' => 'ID',
                'nombre' => 'Nombre',
                'salario_dia' => 'Salario diario',
                'salario_quincena' => 'Salario quincenal',
                'empleados_count' => 'Empleados',
                'estado' => 'Estado',
            ])
            ->formatters([
                'salario_dia' => fn (mixed $valor): string => $this->moneda($valor),
                'salario_quincena' => fn (mixed $valor): string => $this->moneda($valor),
                'estado' => fn (mixed $valor, Puesto $puesto): string => $puesto->trashed()
                    ? 'Archivado'
                    : ($puesto->activo ? 'Activo' : 'Inactivo'),
            ])
            ->columnWidths([
                'id' => 10,
                'nombre' => 35,
                'salario_dia' => 20,
                'salario_quincena' => 22,
                'empleados_count' => 14,
                'estado' => 14,
            ]);
    }

    private function moneda(mixed $valor): string
    {
        return $valor === null ? '' : '$'.number_format((float) $valor, 2).' MXN';
    }
}
