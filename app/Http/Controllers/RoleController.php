<?php

namespace App\Http\Controllers;

use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['search', 'per_page', 'page'];

    /**
     * Display a paginated role listing.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = $this->perPage($request);

        $roles = Role::query()
            ->select(['id', 'name', 'guard_name'])
            ->where('guard_name', 'web')
            ->with(['permissions' => fn ($query) => $query
                ->select(['permissions.id', 'name', 'guard_name'])
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
                ->when(
                    ! $request->user()?->hasRole('Administrador', 'web'),
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
        Gate::authorize('create', Role::class);

        $data = $request->validated();
        $permissions = Arr::pull($data, 'permissions');

        DB::transaction(function () use ($data, $permissions): void {
            $role = Role::query()->create([
                ...$data,
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($permissions);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol creado correctamente.']);

        return $this->redirectToResourceIndex($request, 'roles.index', self::INDEX_QUERY_PARAMETERS);
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);
        $this->ensureRoleIsMutable($role);

        $data = $request->validated();
        $permissions = Arr::pull($data, 'permissions');

        DB::transaction(function () use ($role, $data, $permissions): void {
            $role->update($data);
            $role->syncPermissions($permissions);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol actualizado correctamente.']);

        return $this->redirectToResourceIndex($request, 'roles.index', self::INDEX_QUERY_PARAMETERS);
    }

    /**
     * Delete the specified role.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);
        $this->ensureRoleIsMutable($role);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'No puedes eliminar un rol que tiene usuarios asignados.',
            ]);
        }

        DB::transaction(static function () use ($role): void {
            $role->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol eliminado correctamente.']);

        return $this->redirectToResourceIndex($request, 'roles.index', self::INDEX_QUERY_PARAMETERS);
    }

    private function ensureRoleIsMutable(Role $role): void
    {
        if ($role->name === 'Administrador') {
            throw ValidationException::withMessages([
                'role' => 'El rol Administrador no se puede modificar ni eliminar.',
            ]);
        }
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
