<?php

namespace App\Services\Platform;

use App\Actions\Empresas\CrearRolesPredeterminadosEmpresa;
use App\Actions\Empresas\RecordCompanyAccessActivity;
use App\Actions\Users\EnsureAdministratorRemains;
use App\EstadoMembresiaEmpresa;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagePlatformUsers
{
    public function __construct(
        private readonly CrearRolesPredeterminadosEmpresa $crearRolesPredeterminadosEmpresa,
        private readonly EnsureAdministratorRemains $ensureAdministratorRemains,
        private readonly RecordCompanyAccessActivity $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int|string>  $empresaIds
     */
    public function create(User $actor, array $data, array $empresaIds): User
    {
        $empresaIds = $this->normalizeIds($empresaIds);
        $empresas = $this->findCompanies($empresaIds);
        $previousTeamId = getPermissionsTeamId();

        try {
            $user = DB::transaction(function () use ($actor, $data, $empresas): User {
                $user = User::query()->create($data);

                foreach ($empresas as $empresa) {
                    $this->assignAdministrator($empresa, $actor, $user);
                }

                return $user;
            });
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        event(new Registered($user));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int|string>|null  $empresaIds
     */
    public function update(User $actor, User $user, array $data, ?array $empresaIds): void
    {
        if (blank($data['password'] ?? null)) {
            Arr::forget($data, 'password');
        }

        $previousTeamId = getPermissionsTeamId();

        try {
            $emailWasChanged = DB::transaction(function () use ($actor, $user, $data, $empresaIds): bool {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $currentMemberships = $lockedUser->membresiasEmpresa()
                    ->with('empresa')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('empresa_id');
                $desiredIds = $empresaIds === null
                    ? $currentMemberships->keys()->map(static fn (mixed $id): int => (int) $id)->all()
                    : $this->normalizeIds($empresaIds);
                $desiredIdLookup = array_fill_keys($desiredIds, true);

                foreach ($currentMemberships as $membership) {
                    if (isset($desiredIdLookup[(int) $membership->empresa_id])) {
                        continue;
                    }

                    $this->setTeam($membership->empresa_id);
                    $lockedUser->unsetRelation('roles');
                    $this->ensureAdministratorRemains->handle($lockedUser);
                }

                $lockedUser->fill($data);
                $emailWasChanged = $lockedUser->isDirty('email');

                if ($emailWasChanged) {
                    $lockedUser->forceFill(['email_verified_at' => null]);
                }

                $lockedUser->save();

                foreach ($currentMemberships as $membership) {
                    if (isset($desiredIdLookup[(int) $membership->empresa_id])) {
                        continue;
                    }

                    $this->removeMembership($membership->empresa, $actor, $lockedUser, $membership);
                }

                $empresas = $this->findCompanies($desiredIds);

                foreach ($empresas as $empresa) {
                    $this->assignAdministrator(
                        $empresa,
                        $actor,
                        $lockedUser,
                        $currentMemberships->get($empresa->id),
                    );
                }

                return $emailWasChanged;
            });
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        if ($emailWasChanged) {
            $user->refresh()->sendEmailVerificationNotification();
        }
    }

    public function remove(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages(['user' => 'No puedes eliminar tu propia cuenta.']);
        }

        $previousTeamId = getPermissionsTeamId();

        try {
            DB::transaction(function () use ($actor, $user): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $this->ensureAdministratorRemains->handleAcrossCompanies($lockedUser);
                $memberships = $lockedUser->membresiasEmpresa()
                    ->with('empresa')
                    ->lockForUpdate()
                    ->get();

                foreach ($memberships as $membership) {
                    $this->removeMembership($membership->empresa, $actor, $lockedUser, $membership);
                }

                $this->deletePermissionPivots($lockedUser);
                $lockedUser->delete();
            });
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * @param  array<int, int>  $empresaIds
     * @return Collection<int, Empresa>
     */
    private function findCompanies(array $empresaIds): Collection
    {
        $empresas = Empresa::query()
            ->whereIn('id', $empresaIds)
            ->orderBy('nombre_legal')
            ->get();

        if ($empresas->count() !== count($empresaIds)) {
            throw ValidationException::withMessages([
                'empresa_ids' => 'Una o más empresas seleccionadas no existen.',
            ]);
        }

        return $empresas;
    }

    private function assignAdministrator(
        Empresa $empresa,
        User $actor,
        User $user,
        ?MembresiaEmpresa $membership = null,
    ): void {
        $this->setTeam($empresa->id);
        $role = $this->crearRolesPredeterminadosEmpresa->handle($empresa);
        $membership ??= $user->membresiasEmpresa()
            ->where('empresa_id', $empresa->id)
            ->lockForUpdate()
            ->first();
        $creatingMembership = $membership === null;
        $before = $creatingMembership ? [] : $this->snapshot($user, $membership);

        if ($creatingMembership) {
            $membership = $user->membresiasEmpresa()->create([
                'empresa_id' => $empresa->id,
                'estado' => EstadoMembresiaEmpresa::Activa,
                'fecha_incorporacion' => now(),
                'invitado_por_user_id' => $actor->id,
            ]);
        } elseif (! $membership->estaActiva()) {
            $membership->update([
                'estado' => EstadoMembresiaEmpresa::Activa,
                'suspendida_at' => null,
            ]);
        }

        $user->unsetRelation('roles');
        $user->assignRole($role);

        $this->recordActivity->handle(
            $empresa,
            $actor,
            $user,
            $creatingMembership ? 'membership_created' : 'membership_updated',
            $creatingMembership ? [] : $before,
            $this->snapshot($user, $membership),
        );
    }

    private function removeMembership(
        Empresa $empresa,
        User $actor,
        User $user,
        MembresiaEmpresa $membership,
    ): void {
        $this->setTeam($empresa->id);
        $user->unsetRelation('roles');
        $before = $this->snapshot($user, $membership);
        $user->syncRoles([]);
        $membership->delete();
        $this->recordActivity->handle($empresa, $actor, $user, 'membership_deleted', $before, []);
    }

    /** @return array{membresia_id: int, estado: string, roles: list<int>} */
    private function snapshot(User $user, MembresiaEmpresa $membership): array
    {
        return [
            'membresia_id' => $membership->id,
            'estado' => $membership->estado->value,
            'roles' => array_values($user->roles()
                ->orderBy('roles.id')
                ->pluck('roles.id')
                ->map(static fn (mixed $roleId): int => (int) $roleId)
                ->all()),
        ];
    }

    /**
     * @param  array<int, int|string>  $identifiers
     * @return array<int, int>
     */
    private function normalizeIds(array $identifiers): array
    {
        return array_values(array_unique(array_map(
            static fn (int|string $identifier): int => (int) $identifier,
            $identifiers,
        )));
    }

    private function setTeam(int $empresaId): void
    {
        setPermissionsTeamId($empresaId);
    }

    private function deletePermissionPivots(User $user): void
    {
        $modelType = $user->getMorphClass();
        $modelId = $user->getKey();
        $modelKey = config('permission.column_names.model_morph_key', 'model_id');
        $tableNames = config('permission.table_names');

        foreach (['model_has_roles', 'model_has_permissions'] as $tableName) {
            DB::table($tableNames[$tableName])
                ->where($modelKey, $modelId)
                ->where('model_type', $modelType)
                ->delete();
        }
    }
}
