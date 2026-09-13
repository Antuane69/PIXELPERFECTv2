<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlatformUserRequest;
use App\Http\Requests\Admin\UpdatePlatformUserRequest;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Platform\ManagePlatformUsers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PlatformUserController extends Controller
{
    public function __construct(
        private readonly ManagePlatformUsers $managePlatformUsers,
    ) {}

    private const INDEX_QUERY_PARAMETERS = ['search', 'per_page', 'page'];

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'email_verified_at',
                'es_superadministrador_plataforma',
                'two_factor_confirmed_at',
                'created_at',
            ])
            ->withCount('membresiasEmpresa')
            ->with(['empresas' => fn ($query) => $query->select([
                'empresas.id',
                'empresas.nombre_legal',
                'empresas.nombre_comercial',
            ])])
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $searchQuery) => $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            ))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'es_superadministrador_plataforma' => $user->es_superadministrador_plataforma,
                'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'empresas_count' => $user->membresias_empresa_count,
                'empresas' => $user->empresas->map(static fn (Empresa $empresa): array => [
                    'id' => $empresa->id,
                    'nombre' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
                ])->values()->all(),
                'created_at' => $user->created_at?->toISOString(),
            ]);

        return Inertia::render('admin/usuarios/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
            ],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(StorePlatformUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validated();
        $empresaIds = $data['empresa_ids'];
        unset($data['empresa_ids']);

        $this->managePlatformUsers->create($request->user(), $data, $empresaIds);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Administrador creado y asignado a las empresas seleccionadas.',
        ]);

        return $this->redirectToResourceIndex(
            $request,
            'platform.usuarios.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    public function update(UpdatePlatformUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validated();
        $empresaIds = $data['empresa_ids'] ?? null;
        unset($data['empresa_ids']);

        $this->managePlatformUsers->update($request->user(), $user, $data, $empresaIds);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'platform.usuarios.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->managePlatformUsers->remove($request->user(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario eliminado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'platform.usuarios.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }
}
