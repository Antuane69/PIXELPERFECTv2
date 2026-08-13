<?php

namespace App\Services\Reportes\Definiciones;

use App\Models\User;
use App\Services\Reportes\Contracts\ReporteExportable;
use App\Services\Reportes\ExportConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UsuariosReporte implements ReporteExportable
{
    /** @param array<string, mixed> $filtros */
    public function autorizar(Authenticatable $usuario, array $filtros): void
    {
        Gate::forUser($usuario)->authorize('viewAny', User::class);
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
     * @return Builder<User>
     */
    public function query(array $filtros): Builder
    {
        $search = Str::squish((string) ($filtros['search'] ?? ''));

        return User::query()
            ->select([
                'id',
                'name',
                'email',
                'email_verified_at',
                'two_factor_confirmed_at',
                'created_at',
            ])
            ->with(['roles' => fn ($query) => $query
                ->select(['roles.id', 'name', 'guard_name'])
                ->orderBy('name')])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');
    }

    /** @param array<string, mixed> $filtros */
    public function config(array $filtros, string $formato): ExportConfig
    {
        return ExportConfig::make()
            ->title('Usuarios')
            ->fileName('usuarios_'.now()->format('Ymd_His'))
            ->sheetName('Usuarios')
            ->columns([
                'id' => 'ID',
                'name' => 'Nombre',
                'email' => 'Correo',
                'roles' => 'Roles',
                'email_verified_at' => 'Correo verificado',
                'two_factor_confirmed_at' => '2FA',
                'created_at' => 'Creado',
            ])
            ->formatters([
                'roles' => fn (mixed $valor, User $usuario): string => $usuario->roles->pluck('name')->implode(', '),
                'email_verified_at' => fn (mixed $valor): string => $valor === null ? 'Pendiente' : 'Verificado',
                'two_factor_confirmed_at' => fn (mixed $valor): string => $valor === null ? 'Inactivo' : 'Activo',
                'created_at' => fn (mixed $valor): string => $valor?->format('d/m/Y H:i') ?? '',
            ])
            ->columnWidths([
                'id' => 10,
                'name' => 30,
                'email' => 36,
                'roles' => 30,
                'email_verified_at' => 20,
                'two_factor_confirmed_at' => 14,
                'created_at' => 20,
            ]);
    }
}
