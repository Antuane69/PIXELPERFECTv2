<?php

namespace App\Http\Controllers;

use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Models\Empresa;
use App\Models\Role;
use App\Services\Empresas\ManageCompanyRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['search', 'per_page', 'page'];

    private const PERMISOS_EXCLUSIVOS_PLATAFORMA = [
        'logs.view',
        'logs.delete',
        'tipos_documento.view',
        'tipos_documento.create',
        'tipos_documento.update',
        'tipos_documento.delete',
    ];

    public function __construct(private readonly ManageCompanyRoles $manageRoles) {}

    /**
     * Display a paginated role listing.
     */
    public function index(Request $request, Empresa $empresa): Response
    {
        Gate::authorize('viewAny', Role::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = $this->perPage($request);

        $roles = Role::query()
            ->select(['id', 'name', 'guard_name'])
            ->where('empresa_id', $empresa->id)
            ->where('guard_name', 'web')
            ->with(['permissions' => fn ($query) => $query
                ->select(['permissions.id', 'name', 'guard_name'])
                ->whereNotIn('name', self::PERMISOS_EXCLUSIVOS_PLATAFORMA)
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
                ->whereNotIn('name', self::PERMISOS_EXCLUSIVOS_PLATAFORMA)
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
    public function store(StoreRoleRequest $request, Empresa $empresa): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $data = $request->validated();
        $permissions = Arr::pull($data, 'permissions');

        $this->manageRoles->save($empresa, $request->user(), $data, $permissions);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol creado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.roles.index',
            self::INDEX_QUERY_PARAMETERS,
            ['empresa' => $empresa],
        );
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Empresa $empresa, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);

        $data = $request->validated();
        $permissions = Arr::pull($data, 'permissions');

        $this->manageRoles->save($empresa, $request->user(), $data, $permissions, $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol actualizado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.roles.index',
            self::INDEX_QUERY_PARAMETERS,
            ['empresa' => $empresa],
        );
    }

    /**
     * Delete the specified role.
     */
    public function destroy(Request $request, Empresa $empresa, Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $this->manageRoles->delete($empresa, $request->user(), $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol eliminado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.roles.index',
            self::INDEX_QUERY_PARAMETERS,
            ['empresa' => $empresa],
        );
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
