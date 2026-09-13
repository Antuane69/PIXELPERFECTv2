<?php

namespace App\Actions\Empresas;

use App\AlcancePermiso;
use App\Models\Empresa;
use App\Models\Modulo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ActualizarModulosEmpresa
{
    /** @param list<int> $enabledModuleIds */
    public function handle(Empresa $empresa, array $enabledModuleIds, User $actor): void
    {
        DB::transaction(function () use ($empresa, $enabledModuleIds, $actor): void {
            Empresa::query()->lockForUpdate()->findOrFail($empresa->id);
            $activeModuleIds = Modulo::query()
                ->where('activo', true)
                ->lockForUpdate()
                ->pluck('id');
            $enabledModuleIds = collect($enabledModuleIds);
            $before = $empresa->modulos()
                ->where('activo', true)
                ->wherePivot('habilitado', true)
                ->orderBy('orden')
                ->pluck('clave')
                ->all();
            $sync = $activeModuleIds->mapWithKeys(static fn (int $moduleId): array => [
                $moduleId => ['habilitado' => $enabledModuleIds->containsStrict($moduleId)],
            ]);

            $empresa->modulos()->sync($sync->all(), false);

            $administratorRole = Role::query()
                ->where('empresa_id', $empresa->id)
                ->where('name', 'Administrador')
                ->where('guard_name', 'web')
                ->first();

            $administratorRole?->syncPermissions(
                Permission::query()
                    ->where('guard_name', 'web')
                    ->where('alcance', AlcancePermiso::Empresa)
                    ->whereIn('modulo_id', $enabledModuleIds)
                    ->get(),
            );

            $after = Modulo::query()
                ->where('activo', true)
                ->whereIn('id', $enabledModuleIds)
                ->orderBy('orden')
                ->pluck('clave')
                ->all();

            if ($before !== $after) {
                activity('modulos_empresa')
                    ->causedBy($actor)
                    ->performedOn($empresa)
                    ->event('updated')
                    ->withProperties([
                        'old' => ['modulos' => $before],
                        'attributes' => ['modulos' => $after],
                    ])
                    ->tap(function (Activity $activity) use ($empresa): void {
                        $activity->setAttribute('empresa_id', $empresa->id);
                    })
                    ->log('Módulos empresariales actualizados');
            }
        });
    }
}
