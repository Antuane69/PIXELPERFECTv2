<?php

namespace App\Http\Controllers\PermisosLaborales;

use App\Actions\PermisosLaborales\ArchivarTipoPermiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\PermisosLaborales\CambiarPaginaTiposPermisoRequest;
use App\Http\Requests\PermisosLaborales\FiltrarTiposPermisoRequest;
use App\Http\Requests\PermisosLaborales\StoreTipoPermisoRequest;
use App\Http\Requests\PermisosLaborales\UpdateTipoPermisoRequest;
use App\Models\PermisosLaborales\TipoPermiso;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TipoPermisoController extends Controller
{
    private const PAGE_SIZE = 15;

    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('viewAny', TipoPermiso::class);

        $sessionKey = $this->sessionKey($empresa->id);
        $storedFilters = $request->session()->get("{$sessionKey}.filters", []);
        $storedFilters = is_array($storedFilters) ? $storedFilters : [];
        $filters = [
            'search' => is_string($storedFilters['search'] ?? null) ? $storedFilters['search'] : '',
            'activo' => is_bool($storedFilters['activo'] ?? null) ? $storedFilters['activo'] : null,
            'archivados' => is_bool($storedFilters['archivados'] ?? null) ? $storedFilters['archivados'] : false,
        ];

        $query = TipoPermiso::query()
            ->select(['id', 'empresa_id', 'nombre', 'descripcion', 'activo', 'deleted_at'])
            ->whereBelongsTo($empresa)
            ->when($filters['archivados'], fn (Builder $query) => $query->onlyTrashed())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('nombre', 'like', "%{$search}%")
                        ->orWhere('descripcion', 'like', "%{$search}%");
                });
            })
            ->when($filters['activo'] !== null, fn (Builder $query) => $query->where('activo', $filters['activo']))
            ->orderByDesc('id');

        $page = max((int) $request->session()->get("{$sessionKey}.page", 1), 1);
        $columns = ['id', 'empresa_id', 'nombre', 'descripcion', 'activo', 'deleted_at'];
        $tiposPermiso = $query->paginate(self::PAGE_SIZE, $columns, 'page', $page);

        if ($page > $tiposPermiso->lastPage()) {
            $page = $tiposPermiso->lastPage();
            $request->session()->put("{$sessionKey}.page", $page);
            $tiposPermiso = $query->paginate(self::PAGE_SIZE, $columns, 'page', $page);
        }

        $tiposPermiso->through(static fn (TipoPermiso $tipoPermiso): array => [
            'id' => $tipoPermiso->id,
            'nombre' => $tipoPermiso->nombre,
            'descripcion' => $tipoPermiso->descripcion,
            'activo' => $tipoPermiso->activo,
            'archivadoAt' => $tipoPermiso->deleted_at?->toISOString(),
        ]);

        return Inertia::render('tipos-permisos/index', [
            'tiposPermiso' => $this->paginatorWithoutUrls($tiposPermiso->toArray()),
            'filters' => $filters,
        ]);
    }

    public function store(StoreTipoPermisoRequest $request): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        TipoPermiso::query()->create([
            'empresa_id' => $empresa->id,
            ...$request->validated(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo de permiso creado correctamente.',
        ]);

        return to_route('empresas.tipos-permisos.index');
    }

    public function update(
        UpdateTipoPermisoRequest $request,
        TipoPermiso $tipoPermiso,
    ): RedirectResponse {
        $tipoPermiso->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo de permiso actualizado correctamente.',
        ]);

        return to_route('empresas.tipos-permisos.index');
    }

    public function destroy(
        TipoPermiso $tipoPermiso,
        ArchivarTipoPermiso $archivarTipoPermiso,
    ): RedirectResponse {
        Gate::authorize('delete', $tipoPermiso);
        $archivarTipoPermiso->handle($tipoPermiso);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo de permiso archivado correctamente.',
        ]);

        return to_route('empresas.tipos-permisos.index');
    }

    public function restore(Request $request, TipoPermiso $tipoPermiso): RedirectResponse
    {
        Gate::authorize('restore', $tipoPermiso);
        $tipoPermiso->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo de permiso restaurado correctamente.',
        ]);

        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $request->session()->put($this->sessionKey($empresaId).'.filters.archivados', true);

        return to_route('empresas.tipos-permisos.index');
    }

    public function filtrar(FiltrarTiposPermisoRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $validated = $request->validated();
        $sessionKey = $this->sessionKey($empresaId);
        $request->session()->put("{$sessionKey}.filters", [
            'search' => $validated['search'] ?? '',
            'activo' => array_key_exists('activo', $validated) && $validated['activo'] !== null
                ? $request->boolean('activo')
                : null,
            'archivados' => $request->boolean('archivados'),
        ]);
        $request->session()->put("{$sessionKey}.page", 1);

        return to_route('empresas.tipos-permisos.index');
    }

    public function cambiarPagina(CambiarPaginaTiposPermisoRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $request->session()->put(
            $this->sessionKey($empresaId).'.page',
            (int) $request->validated('page'),
        );

        return to_route('empresas.tipos-permisos.index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function paginatorWithoutUrls(array $data): array
    {
        if (! is_array($data['links'] ?? null)) {
            return $data;
        }

        $data['links'] = array_map(static function (mixed $link): array {
            if (! is_array($link)) {
                return [
                    'url' => null,
                    'label' => '',
                    'active' => false,
                    'page' => null,
                ];
            }

            $page = null;

            if (is_string($link['url'] ?? null)) {
                $query = parse_url($link['url'], PHP_URL_QUERY);
                parse_str(is_string($query) ? $query : '', $parameters);
                $page = isset($parameters['page']) ? (int) $parameters['page'] : null;
            }

            return [
                'url' => null,
                'label' => is_string($link['label'] ?? null) ? $link['label'] : '',
                'active' => (bool) ($link['active'] ?? false),
                'page' => $page,
            ];
        }, $data['links']);

        return $data;
    }

    private function sessionKey(int $empresaId): string
    {
        return "tipos_permisos.{$empresaId}";
    }
}
