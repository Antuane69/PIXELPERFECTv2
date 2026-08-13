<?php

namespace App\Services\Reportes\Definiciones;

use App\Models\TipoDocumentoEmpleado;
use App\Services\Reportes\Contracts\ReporteExportable;
use App\Services\Reportes\ExportConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TiposDocumentoEmpleadoReporte implements ReporteExportable
{
    /** @param array<string, mixed> $filtros */
    public function autorizar(Authenticatable $usuario, array $filtros): void
    {
        Gate::forUser($usuario)->authorize('viewAny', TipoDocumentoEmpleado::class);
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
            'es_renovable' => ['nullable', 'boolean'],
            'archivados' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<TipoDocumentoEmpleado>
     */
    public function query(array $filtros): Builder
    {
        $search = Str::squish((string) ($filtros['search'] ?? ''));

        return TipoDocumentoEmpleado::query()
            ->select([
                'id',
                'nombre',
                'es_renovable',
                'frecuencia_cantidad',
                'frecuencia_tipo',
                'documentos_aceptados',
                'activo',
                'deleted_at',
            ])
            ->when((bool) ($filtros['archivados'] ?? false), fn (Builder $query) => $query->onlyTrashed())
            ->when($search !== '', fn (Builder $query) => $query->where('nombre', 'like', "%{$search}%"))
            ->when(
                array_key_exists('activo', $filtros) && $filtros['activo'] !== null,
                fn (Builder $query) => $query->where('activo', (bool) $filtros['activo']),
            )
            ->when(
                array_key_exists('es_renovable', $filtros) && $filtros['es_renovable'] !== null,
                fn (Builder $query) => $query->where('es_renovable', (bool) $filtros['es_renovable']),
            )
            ->orderByDesc('id');
    }

    /** @param array<string, mixed> $filtros */
    public function config(array $filtros, string $formato): ExportConfig
    {
        return ExportConfig::make()
            ->title('Tipos de documento de empleados')
            ->fileName('tipos_documento_empleados_'.now()->format('Ymd_His'))
            ->sheetName('Tipos de documento')
            ->columns([
                'id' => 'ID',
                'nombre' => 'Nombre',
                'documentos_aceptados' => 'Formatos aceptados',
                'renovacion' => 'Renovación',
                'estado' => 'Estado',
            ])
            ->formatters([
                'documentos_aceptados' => fn (mixed $valor): string => implode(', ', (array) $valor),
                'renovacion' => fn (mixed $valor, TipoDocumentoEmpleado $tipo): string => $tipo->es_renovable
                    ? "Cada {$tipo->frecuencia_cantidad} {$tipo->frecuencia_tipo}"
                    : 'No renovable',
                'estado' => fn (mixed $valor, TipoDocumentoEmpleado $tipo): string => $tipo->trashed()
                    ? 'Archivado'
                    : ($tipo->activo ? 'Activo' : 'Inactivo'),
            ])
            ->columnWidths([
                'id' => 10,
                'nombre' => 38,
                'documentos_aceptados' => 32,
                'renovacion' => 24,
                'estado' => 14,
            ]);
    }
}
