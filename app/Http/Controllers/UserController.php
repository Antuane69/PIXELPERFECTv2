<?php

namespace App\Http\Controllers;

use App\EstadoMembresiaEmpresa;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UpdateUserTwoFactorRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use App\Services\Empresas\ManageCompanyUsers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['search', 'per_page', 'page'];

    public function __construct(
        private readonly ManageCompanyUsers $manageUsers,
        private readonly EmpresaContext $empresaContext,
    ) {}

    /**
     * Display a paginated user listing.
     */
    public function index(Request $request): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
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
            ->whereHas('membresiasEmpresa', fn (Builder $query) => $query
                ->where('empresa_id', $empresa->id)
                ->where('estado', EstadoMembresiaEmpresa::Activa->value))
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
                ->where('empresa_id', $empresa->id)
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
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('create', User::class);

        $data = $request->validated();
        $roles = Arr::pull($data, 'roles');

        $this->manageUsers->create($empresa, $request->user(), $data, $roles);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario creado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.users.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('update', $user);

        $data = $request->validated();
        Arr::forget($data, 'roles');
        $this->manageUsers->update($empresa, $request->user(), $user, $data, $request->validatedRoleIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.users.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    /**
     * Update two-factor authentication for the specified user.
     */
    public function updateTwoFactor(UpdateUserTwoFactorRequest $request, User $user): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();

        $enabled = $request->boolean('enabled');
        $this->manageUsers->setTwoFactorEnabled($empresa, $request->user(), $user, $enabled);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $enabled
                ? 'Autenticación de dos factores activada.'
                : 'Autenticación de dos factores desactivada.',
        ]);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.users.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    /**
     * Send a password reset link to the specified user.
     */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('sendPasswordReset', $user);

        $this->manageUsers->sendPasswordReset($empresa, $request->user(), $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Correo de restablecimiento enviado correctamente.',
        ]);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.users.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    /**
     * Delete the specified user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('delete', $user);

        $this->manageUsers->remove($empresa, $request->user(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario retirado de la empresa correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.users.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
