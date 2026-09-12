<?php

namespace App\Services\Empresas;

use App\Actions\Empresas\RecordCompanyAccessActivity;
use App\Actions\Users\EnsureAdministratorRemains;
use App\Actions\Users\EnsureAdministratorRoleAssignmentIsAuthorized;
use App\EstadoMembresiaEmpresa;
use App\Models\Empresa;
use App\Models\MembresiaEmpresa;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageCompanyUsers
{
    public function __construct(
        private readonly EnsureAdministratorRemains $ensureAdministratorRemains,
        private readonly EnsureAdministratorRoleAssignmentIsAuthorized $ensureRoleAssignment,
        private readonly RecordCompanyAccessActivity $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int|string>  $roles
     */
    public function create(Empresa $empresa, User $actor, array $data, array $roles): void
    {
        $this->ensureRoleAssignment->handle($actor, $roles);

        $user = DB::transaction(function () use ($empresa, $actor, $data, $roles): User {
            $user = User::query()->create($data);
            $membership = MembresiaEmpresa::query()->create([
                'empresa_id' => $empresa->id,
                'user_id' => $user->id,
                'estado' => EstadoMembresiaEmpresa::Activa,
                'fecha_incorporacion' => now(),
                'invitado_por_user_id' => $actor->id,
            ]);
            $user->syncRoles($roles);
            $this->recordActivity->handle($empresa, $actor, $user, 'membership_created', [], $this->snapshot($user, $membership));

            return $user;
        });

        event(new Registered($user));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int|string>|null  $roles
     */
    public function update(Empresa $empresa, User $actor, User $user, array $data, ?array $roles): void
    {
        if (! $actor->es_superadministrador_plataforma) {
            Arr::forget($data, ['name', 'email', 'password']);
        }

        if (blank($data['password'] ?? null)) {
            Arr::forget($data, 'password');
        }

        $emailWasChanged = DB::transaction(function () use ($empresa, $actor, $user, $data, $roles): bool {
            if ($roles !== null) {
                $this->ensureRoleAssignment->handle($actor, $roles, $user);
                $this->ensureAdministratorRemains->handle($user, $roles);
            }

            $membership = $user->membresiasEmpresa()->where('empresa_id', $empresa->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($user, $membership);
            $user->fill($data);
            $emailWasChanged = $user->isDirty('email');

            if ($emailWasChanged) {
                $user->forceFill(['email_verified_at' => null]);
            }

            $user->save();

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            $this->recordActivity->handle($empresa, $actor, $user, 'roles_updated', $before, $this->snapshot($user, $membership));

            return $emailWasChanged;
        });

        if ($emailWasChanged) {
            $user->sendEmailVerificationNotification();
        }
    }

    public function remove(Empresa $empresa, User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages(['user' => 'No puedes eliminar tu propia cuenta.']);
        }

        DB::transaction(function () use ($empresa, $actor, $user): void {
            $this->ensureAdministratorRemains->handle($user);
            $membership = $user->membresiasEmpresa()->where('empresa_id', $empresa->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($user, $membership);
            $user->syncRoles([]);
            $membership->delete();
            $this->recordActivity->handle($empresa, $actor, $user, 'membership_deleted', $before, []);
        });
    }

    /** @return array{membresia_id: int, estado: string, roles: list<int>} */
    private function snapshot(User $user, MembresiaEmpresa $membership): array
    {
        return [
            'membresia_id' => $membership->id,
            'estado' => $membership->estado->value,
            'roles' => $user->roles()->orderBy('roles.id')->pluck('roles.id')->all(),
        ];
    }
}
