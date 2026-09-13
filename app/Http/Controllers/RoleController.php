<?php

namespace App\Http\Controllers;

use App\AlcancePermiso;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Models\Modulo;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Empresas\EmpresaContext;
use App\Services\Empresas\ManageCompanyRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['search', 'per_page', 'page'];

    public function __construct(
        private readonly ManageCompanyRoles $manageRoles,
        private readonly EmpresaContext $empresaContext,
    ) {}

    /**
     * Display a paginated role listing.
     */
    public function index(Request $request): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('viewAny', Role::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = $this->perPage($request);
        $enabledModuleIds = $request->user()?->es_superadministrador_plataforma
            ? Modulo::query()->where('activo', true)->pluck('id')
            : $empresa->modulos()
                ->where('activo', true)
                ->wherePivot('habilitado', true)
                ->pluck('modulos.id');

        $roles = Role::query()
            ->select(['id', 'name', 'guard_name'])
            ->where('empresa_id', $empresa->id)
            ->where('guard_name', 'web')
            ->with(['permissions' => fn ($query) => $query
                ->select(['permissions.id', 'name', 'guard_name'])
                ->where('alcance', AlcancePermiso::Empresa)
                ->whereIn('modulo_id', $enabledModuleIds)
                ->orderBy('name')])
            ->withCount('users')
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'users_count' => $role->users_count,
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'permissions' => Permission::query()
                ->select(['id', 'name'])
                ->where('guard_name', 'web')
                ->where('alcance', AlcancePermiso::Empresa)
                ->whereIn('modulo_id', $enabledModuleIds)
                ->when(
                    ! $request->user()?->es_superadministrador_plataforma
                        && ! $request->user()?->hasRole('Administrador', 'web'),
                    fn (Builder $query) => $query->whereIn(
                        'id',
                        $request->user()?->getAllPermissions()->pluck('id')->all() ?? [],
                    ),
                )
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('create', Role::class);

        $data = $request->validated();
        $permissions = Arr::pull($data, 'permissions');

        $this->manageRoles->save($empresa, $request->user(), $data, $permissions);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol creado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.roles.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('update', $role);

        $data = $request->validated();
        $permissions = Arr::pull($data, 'permissions');

        $this->manageRoles->save($empresa, $request->user(), $data, $permissions, $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol actualizado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.roles.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    /**
     * Delete the specified role.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('delete', $role);

        $this->manageRoles->delete($empresa, $request->user(), $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol eliminado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.roles.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
