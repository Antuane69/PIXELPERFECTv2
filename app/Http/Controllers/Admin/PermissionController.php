<?php

namespace App\Http\Controllers\Admin;

use App\AlcancePermiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Permissions\StorePermissionRequest;
use App\Http\Requests\Admin\Permissions\UpdatePermissionRequest;
use App\Models\Modulo;
use App\Models\Permission;
use App\Services\Platform\ManagePermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['search', 'alcance', 'modulo_id', 'per_page', 'page'];

    public function __construct(private readonly ManagePermissions $managePermissions) {}

    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', Permission::class);

        $search = $request->string('search')->squish()->toString();
        $scope = AlcancePermiso::tryFrom(Str::upper($request->string('alcance')->toString()));
        $moduleId = $request->integer('modulo_id');
        $moduleId = $moduleId > 0 ? $moduleId : null;
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $permissions = Permission::query()
            ->select(['id', 'name', 'guard_name', 'alcance', 'modulo_id', 'created_at'])
            ->with('modulo:id,clave,nombre')
            ->withCount('roles')
            ->where('guard_name', 'web')
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when($scope instanceof AlcancePermiso, fn (Builder $query) => $query->where('alcance', $scope))
            ->when($moduleId !== null, fn (Builder $query) => $query->where('modulo_id', $moduleId))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'alcance' => $permission->alcance->value,
                'modulo_id' => $permission->modulo_id,
                'modulo' => $permission->modulo === null ? null : [
                    'id' => $permission->modulo->id,
                    'clave' => $permission->modulo->clave,
                    'nombre' => $permission->modulo->nombre,
                ],
                'roles_count' => $permission->roles_count,
                'created_at' => $permission->created_at?->toISOString(),
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'permissions' => $permissions,
            ]);
        }

        return Inertia::render('admin/permisos/index', [
            'permissions' => $permissions,
            'modules' => Modulo::query()
                ->select(['id', 'clave', 'nombre', 'activo'])
                ->orderBy('orden')
                ->orderBy('id')
                ->get(),
            'scopes' => AlcancePermiso::values(),
            'filters' => [
                'search' => $search,
                'alcance' => $scope?->value,
                'moduloId' => $moduleId,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function store(StorePermissionRequest $request): RedirectResponse|JsonResponse
    {
        $permission = $this->managePermissions->save($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'permission' => $this->serializePermission($permission),
                'message' => 'Permiso creado correctamente.',
            ], 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permiso creado correctamente.']);

        return $this->redirectToResourceIndex($request, 'platform.permisos.index', self::INDEX_QUERY_PARAMETERS);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse|JsonResponse
    {
        $permission = $this->managePermissions->save($request->validated(), $permission);

        if ($request->expectsJson()) {
            return response()->json([
                'permission' => $this->serializePermission($permission),
                'message' => 'Permiso actualizado correctamente.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permiso actualizado correctamente.']);

        return $this->redirectToResourceIndex($request, 'platform.permisos.index', self::INDEX_QUERY_PARAMETERS);
    }

    public function destroy(Request $request, Permission $permission): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $permission);
        $this->managePermissions->delete($permission);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $permission->id,
                'message' => 'Permiso eliminado correctamente.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permiso eliminado correctamente.']);

        return $this->redirectToResourceIndex($request, 'platform.permisos.index', self::INDEX_QUERY_PARAMETERS);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     alcance: string,
     *     modulo_id: int|null,
     *     modulo: array{id: int, clave: string, nombre: string}|null,
     *     roles_count: int,
     *     created_at: string|null
     * }
     */
    private function serializePermission(Permission $permission): array
    {
        if (! $permission->relationLoaded('modulo')) {
            $permission->load('modulo');
        }

        if (! array_key_exists('roles_count', $permission->getAttributes())) {
            $permission->loadCount('roles');
        }

        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'alcance' => $permission->alcance->value,
            'modulo_id' => $permission->modulo_id,
            'modulo' => $permission->modulo === null ? null : [
                'id' => $permission->modulo->id,
                'clave' => $permission->modulo->clave,
                'nombre' => $permission->modulo->nombre,
            ],
            'roles_count' => $permission->roles_count,
            'created_at' => $permission->created_at?->toISOString(),
        ];
    }
}
