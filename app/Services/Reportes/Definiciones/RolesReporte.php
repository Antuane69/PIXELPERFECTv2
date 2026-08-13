<?php

namespace App\Services\Reportes\Definiciones;

use App\Services\Reportes\Contracts\ReporteExportable;
use App\Services\Reportes\ExportConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RolesReporte implements ReporteExportable
{
    /** @param array<string, mixed> $filtros */
    public function autorizar(Authenticatable $usuario, array $filtros): void
    {
        Gate::forUser($usuario)->authorize('viewAny', Role::class);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function validarFiltros(array $filtros): array
    {
        return Validator::validate($filtros, [
            'search' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Role>
     */
    public function query(array $filtros): Builder
    {
        $search = Str::squish((string) ($filtros['search'] ?? ''));

        return Role::query()
            ->select(['id', 'name', 'guard_name'])
            ->where('guard_name', 'web')
            ->with(['permissions' => fn ($query) => $query
                ->select(['permissions.id', 'name', 'guard_name'])
                ->orderBy('name')])
            ->withCount('users')
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('id');
    }

    /** @param array<string, mixed> $filtros */
    public function config(array $filtros, string $formato): ExportConfig
    {
        return ExportConfig::make()
            ->title('Roles')
            ->fileName('roles_'.now()->format('Ymd_His'))
            ->sheetName('Roles')
            ->columns([
                'id' => 'ID',
                'name' => 'Rol',
                'permissions' => 'Permisos',
                'users_count' => 'Usuarios',
            ])
            ->formatters([
                'permissions' => fn (mixed $valor, Role $role): string => $role->permissions->pluck('name')->implode(', '),
            ])
            ->columnWidths([
                'id' => 10,
                'name' => 28,
                'permissions' => 70,
                'users_count' => 14,
            ])
            ->excelMaxColumnWidth(70);
    }
}
