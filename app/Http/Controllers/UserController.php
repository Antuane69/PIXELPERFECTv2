<?php

namespace App\Http\Controllers;

use App\Actions\Users\EnsureAdministratorRemains;
use App\Actions\Users\EnsureAdministratorRoleAssignmentIsAuthorized;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['search', 'per_page', 'page'];

    public function __construct(
        private readonly EnsureAdministratorRemains $ensureAdministratorRemains,
        private readonly EnsureAdministratorRoleAssignmentIsAuthorized $ensureAdministratorRoleAssignmentIsAuthorized,
    ) {}

    /**
     * Display a paginated user listing.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = $this->perPage($request);

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'email_verified_at',
                'two_factor_secret',
                'two_factor_confirmed_at',
                'created_at',
            ])
            ->with(['roles' => fn ($query) => $query
                ->select(['roles.id', 'name', 'guard_name'])
                ->orderBy('name')])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'roles' => $user->roles->pluck('name')->values()->all(),
                'created_at' => $user->created_at?->toISOString(),
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => Role::query()
                ->select(['id', 'name'])
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
            ],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validated();
        $roles = Arr::pull($data, 'roles');

        $this->ensureAdministratorRoleAssignmentIsAuthorized->handle($request->user(), $roles);

        $user = DB::transaction(function () use ($data, $roles): User {
            $user = User::query()->create($data);
            $user->syncRoles($roles);

            return $user;
        });

        event(new Registered($user));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario creado correctamente.']);

        return $this->redirectToResourceIndex($request, 'users.index', self::INDEX_QUERY_PARAMETERS);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validated();
        Arr::forget($data, 'roles');
        $roles = $request->validatedRoleIds();

        if ($roles !== null) {
            $this->ensureAdministratorRoleAssignmentIsAuthorized->handle($request->user(), $roles, $user);
        }

        if (blank($data['password'] ?? null)) {
            Arr::forget($data, 'password');
        }

        $emailWasChanged = false;

        DB::transaction(function () use ($user, $data, $roles, &$emailWasChanged): void {
            if ($roles !== null) {
                $this->ensureAdministratorRemains->handle($user, $roles);
            }

            $user->fill($data);
            $emailWasChanged = $user->isDirty('email');

            if ($emailWasChanged) {
                $user->forceFill(['email_verified_at' => null]);
            }

            $user->save();

            if ($roles !== null) {
                $user->syncRoles($roles);
            }
        });

        if ($emailWasChanged) {
            $user->sendEmailVerificationNotification();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado correctamente.']);

        return $this->redirectToResourceIndex($request, 'users.index', self::INDEX_QUERY_PARAMETERS);
    }

    /**
     * Delete the specified user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        if ($request->user()?->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'No puedes eliminar tu propia cuenta.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            $this->ensureAdministratorRemains->handle($user);
            $user->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario eliminado correctamente.']);

        return $this->redirectToResourceIndex($request, 'users.index', self::INDEX_QUERY_PARAMETERS);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
