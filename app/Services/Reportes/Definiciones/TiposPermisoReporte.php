<?php

namespace App\Services\Reportes\Definiciones;

use App\Models\PermisosLaborales\TipoPermiso;
use App\Services\Empresas\EmpresaContext;
use App\Services\Reportes\Contracts\ReporteExportable;
use App\Services\Reportes\ExportConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TiposPermisoReporte implements ReporteExportable
{
    public function __construct(private readonly EmpresaContext $empresaContext) {}

    /** @param array<string, mixed> $filtros */
    public function autorizar(Authenticatable $usuario, array $filtros): void
    {
        Gate::forUser($usuario)->authorize('viewAny', TipoPermiso::class);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function validarFiltros(array $filtros): array
    {
        return Validator::validate($filtros, [
            'search' => ['nullable', 'string', 'max:120'],
            'activo' => ['nullable', 'boolean'],
            'archivados' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<TipoPermiso>
     */
    public function query(array $filtros): Builder
    {
        $search = Str::squish((string) ($filtros['search'] ?? ''));

        return TipoPermiso::query()
            ->select(['id', 'empresa_id', 'nombre', 'descripcion', 'activo', 'deleted_at'])
            ->where('empresa_id', $this->empresaContext->empresaRequerida()->id)
            ->when((bool) ($filtros['archivados'] ?? false), fn (Builder $query) => $query->onlyTrashed())
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('nombre', 'like', "%{$search}%")
                        ->orWhere('descripcion', 'like', "%{$search}%");
                });
            })
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
            ->title('Tipos de permisos laborales')
            ->fileName('tipos_permisos_'.now()->format('Ymd_His'))
            ->sheetName('Tipos de permisos')
            ->columns([
                'id' => 'ID',
                'nombre' => 'Nombre',
                'descripcion' => 'Descripción',
                'estado' => 'Estado',
            ])
            ->formatters([
                'estado' => fn (mixed $valor, TipoPermiso $tipoPermiso): string => $tipoPermiso->trashed()
                    ? 'Archivado'
                    : ($tipoPermiso->activo ? 'Activo' : 'Inactivo'),
            ])
            ->columnWidths([
                'id' => 10,
                'nombre' => 32,
                'descripcion' => 60,
                'estado' => 16,
            ]);
    }
}
