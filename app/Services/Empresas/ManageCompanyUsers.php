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
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

class ManageCompanyUsers
{
    public function __construct(
        private readonly EnsureAdministratorRemains $ensureAdministratorRemains,
        private readonly EnsureAdministratorRoleAssignmentIsAuthorized $ensureRoleAssignment,
        private readonly RecordCompanyAccessActivity $recordActivity,
        private readonly EnableTwoFactorAuthentication $enableTwoFactorAuthentication,
        private readonly DisableTwoFactorAuthentication $disableTwoFactorAuthentication,
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
            $user->syncRoles($this->normalizeIdentifiers($roles));
            $this->recordActivity->handle($empresa, $actor, $user, 'membership_created', [], $this->snapshot($user, $membership));

            return $user;
        });

        event(new Registered($user));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int|string>|null  $roles
     */
    public function update(
        Empresa $empresa,
        User $actor,
        User $user,
        array $data,
        ?array $roles,
    ): void {
        if (! $actor->es_superadministrador_plataforma) {
            Arr::forget($data, ['name', 'email', 'password']);
        }

        if (blank($data['password'] ?? null)) {
            Arr::forget($data, 'password');
        }

        $emailWasChanged = DB::transaction(function () use (
            $empresa,
            $actor,
            $user,
            $data,
            $roles,
        ): bool {
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

    public function setTwoFactorEnabled(Empresa $empresa, User $actor, User $user, bool $enabled): void
    {
        DB::transaction(function () use ($empresa, $actor, $user, $enabled): void {
            $membership = $user->membresiasEmpresa()
                ->where('empresa_id', $empresa->id)
                ->where('estado', EstadoMembresiaEmpresa::Activa->value)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedUser->hasEnabledTwoFactorAuthentication() === $enabled) {
                return;
            }

            $this->setTwoFactorState($lockedUser, $enabled);
            $this->recordActivity->handle(
                $empresa,
                $actor,
                $lockedUser,
                'two_factor_updated',
                ['two_factor_enabled' => ! $enabled],
                ['two_factor_enabled' => $enabled, 'membresia_id' => $membership->id],
            );
        });
    }

    public function sendPasswordReset(Empresa $empresa, User $actor, User $user): void
    {
        $membership = $user->membresiasEmpresa()
            ->where('empresa_id', $empresa->id)
            ->where('estado', EstadoMembresiaEmpresa::Activa->value)
            ->firstOrFail();
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'user' => 'No se pudo enviar el correo de restablecimiento.',
            ]);
        }

        $this->recordActivity->handle(
            $empresa,
            $actor,
            $user,
            'password_reset_requested',
            [],
            ['membresia_id' => $membership->id],
        );
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
            'roles' => array_values($user->roles()
                ->orderBy('roles.id')
                ->pluck('roles.id')
                ->map(static fn (mixed $roleId): int => (int) $roleId)
                ->all()),
        ];
    }

    /**
     * Browser form values arrive as strings; numeric strings must be resolved as IDs by Spatie.
     *
     * @param  array<int, int|string>  $identifiers
     * @return array<int, int|string>
     */
    private function normalizeIdentifiers(array $identifiers): array
    {
        return array_map(
            static fn (int|string $identifier): int|string => is_string($identifier) && ctype_digit($identifier)
                ? (int) $identifier
                : $identifier,
            $identifiers,
        );
    }

    private function setTwoFactorState(User $user, bool $enabled): void
    {
        if ($enabled) {
            ($this->enableTwoFactorAuthentication)($user, true);
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();

            return;
        }

        ($this->disableTwoFactorAuthentication)($user);
    }
}
