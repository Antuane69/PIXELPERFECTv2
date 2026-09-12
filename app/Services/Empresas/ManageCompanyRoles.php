<?php

namespace App\Services\Empresas;

use App\Actions\Empresas\RecordCompanyAccessActivity;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageCompanyRoles
{
    public function __construct(private readonly RecordCompanyAccessActivity $recordActivity) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int|string>  $permissions
     */
    public function save(Empresa $empresa, User $actor, array $data, array $permissions, ?Role $role = null): void
    {
        DB::transaction(function () use ($empresa, $actor, $data, $permissions, $role): void {
            $role = $role === null ? new Role : $empresa->roles()->lockForUpdate()->findOrFail($role->id);
            $creating = ! $role->exists;

            if (! $creating) {
                $this->ensureMutable($role);
            }

            $before = $creating ? [] : $this->snapshot($role);
            $role->fill([...$data, 'empresa_id' => $empresa->id, 'guard_name' => 'web']);
            $role->save();
            $role->syncPermissions($permissions);
            $this->recordActivity->handle($empresa, $actor, $role, $creating ? 'role_created' : 'role_updated', $before, $this->snapshot($role));
        });
    }

    public function delete(Empresa $empresa, User $actor, Role $role): void
    {
        DB::transaction(function () use ($empresa, $actor, $role): void {
            $role = $empresa->roles()->lockForUpdate()->findOrFail($role->id);
            $this->ensureMutable($role);

            if ($role->users()->exists()) {
                throw ValidationException::withMessages(['role' => 'No puedes eliminar un rol que tiene usuarios asignados.']);
            }

            $before = $this->snapshot($role);
            $role->delete();
            $this->recordActivity->handle($empresa, $actor, $role, 'role_deleted', $before, []);
        });
    }

    private function ensureMutable(Role $role): void
    {
        if ($role->esProtegido()) {
            throw ValidationException::withMessages(['role' => 'El rol Administrador no se puede modificar ni eliminar.']);
        }
    }

    /** @return array{name: string, permissions: list<int>} */
    private function snapshot(Role $role): array
    {
        return [
            'name' => $role->name,
            'permissions' => $role->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all(),
        ];
    }
}
