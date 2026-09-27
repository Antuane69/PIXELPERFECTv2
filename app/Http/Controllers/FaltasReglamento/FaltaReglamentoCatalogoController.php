<?php

namespace App\Http\Controllers\FaltasReglamento;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaltasReglamento\CambiarPaginaCatalogoFaltaRequest;
use App\Http\Requests\FaltasReglamento\FiltrarCatalogoFaltasRequest;
use App\Http\Requests\FaltasReglamento\StoreFaltaReglamentoCatalogoRequest;
use App\Http\Requests\FaltasReglamento\UpdateFaltaReglamentoCatalogoRequest;
use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\FaltasReglamento\TipoFaltaReglamento;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FaltaReglamentoCatalogoController extends Controller
{
    private const PAGE_SIZE = 15;

    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('viewAny', FaltaReglamentoCatalogo::class);
        $sessionKey = $this->sessionKey($empresa->id);
        $storedFilters = $request->session()->get("{$sessionKey}.filters", []);
        $storedFilters = is_array($storedFilters) ? $storedFilters : [];
        $tipoId = $storedFilters['tipo_falta_reglamento_id'] ?? null;
        $filters = [
            'search' => is_string($storedFilters['search'] ?? null) ? $storedFilters['search'] : '',
            'activo' => is_bool($storedFilters['activo'] ?? null) ? $storedFilters['activo'] : null,
            'archivados' => ($storedFilters['archivados'] ?? false) === true,
            'tipoFaltaReglamentoId' => is_int($tipoId) ? $tipoId : null,
        ];

        $query = FaltaReglamentoCatalogo::query()
            ->select(['id', 'empresa_id', 'tipo_falta_reglamento_id', 'nombre', 'descripcion', 'activo', 'deleted_at'])
            ->whereBelongsTo($empresa)
            ->with(['tipoFalta:id,empresa_id,nombre,activo,deleted_at'])
            ->when($filters['archivados'], fn (Builder $query) => $query->onlyTrashed())
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('nombre', 'like', "%{$filters['search']}%")
                    ->orWhere('descripcion', 'like', "%{$filters['search']}%");
            }))
            ->when($filters['activo'] !== null, fn (Builder $query) => $query->where('activo', $filters['activo']))
            ->when($filters['tipoFaltaReglamentoId'] !== null, fn (Builder $query) => $query
                ->where('tipo_falta_reglamento_id', $filters['tipoFaltaReglamentoId']))
            ->orderByDesc('id');

        $page = max((int) $request->session()->get("{$sessionKey}.page", 1), 1);
        $faltasCatalogo = $query->paginate(self::PAGE_SIZE, ['*'], 'page', $page);

        if ($page > $faltasCatalogo->lastPage()) {
            $page = $faltasCatalogo->lastPage();
            $request->session()->put("{$sessionKey}.page", $page);
            $faltasCatalogo = $query->paginate(self::PAGE_SIZE, ['*'], 'page', $page);
        }

        $faltasCatalogo->through(static fn (FaltaReglamentoCatalogo $falta): array => [
            'id' => $falta->id,
            'tipoFaltaReglamentoId' => $falta->tipo_falta_reglamento_id,
            'tipoFalta' => $falta->tipoFalta->nombre,
            'nombre' => $falta->nombre,
            'descripcion' => $falta->descripcion,
            'activo' => $falta->activo,
            'archivadoAt' => $falta->deleted_at?->toISOString(),
        ]);

        $tiposFalta = TipoFaltaReglamento::query()
            ->whereBelongsTo($empresa)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
        $tiposFaltaFiltro = TipoFaltaReglamento::withTrashed()
            ->where('empresa_id', $empresa->id)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'activo', 'deleted_at']);

        return Inertia::render('catalogo-faltas-reglamento/index', [
            'faltasCatalogo' => $this->paginatorWithoutUrls($faltasCatalogo),
            'tiposFalta' => $tiposFalta->map(static fn (TipoFaltaReglamento $tipo): array => [
                'id' => $tipo->id,
                'nombre' => $tipo->nombre,
            ])->values(),
            'tiposFaltaFiltro' => $tiposFaltaFiltro->map(static fn (TipoFaltaReglamento $tipo): array => [
                'id' => $tipo->id,
                'nombre' => $tipo->nombre,
                'activo' => $tipo->activo,
                'archivadoAt' => $tipo->deleted_at?->toISOString(),
            ])->values(),
            'filters' => $filters,
        ]);
    }

    public function filtrar(FiltrarCatalogoFaltasRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $tipoId = $request->validated('tipo_falta_reglamento_id');
        $request->session()->put($this->sessionKey($empresaId).'.filters', [
            'search' => $request->validated('search') ?? '',
            'activo' => $request->input('activo') === null ? null : $request->boolean('activo'),
            'archivados' => $request->boolean('archivados'),
            'tipo_falta_reglamento_id' => is_numeric($tipoId) ? (int) $tipoId : null,
        ]);
        $request->session()->put($this->sessionKey($empresaId).'.page', 1);

        return to_route('empresas.catalogo-faltas-reglamento.index');
    }

    public function cambiarPagina(CambiarPaginaCatalogoFaltaRequest $request): RedirectResponse
    {
        Gate::authorize('viewAny', FaltaReglamentoCatalogo::class);
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $request->session()->put($this->sessionKey($empresaId).'.page', (int) $request->validated('page'));

        return to_route('empresas.catalogo-faltas-reglamento.index');
    }

    public function store(StoreFaltaReglamentoCatalogoRequest $request): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        FaltaReglamentoCatalogo::query()->create([
            'empresa_id' => $empresa->id,
            ...$request->validated(),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Falta del catálogo creada correctamente.']);

        return to_route('empresas.catalogo-faltas-reglamento.index');
    }

    public function update(
        UpdateFaltaReglamentoCatalogoRequest $request,
        FaltaReglamentoCatalogo $faltaReglamentoCatalogo,
    ): RedirectResponse {
        $faltaReglamentoCatalogo->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Falta del catálogo actualizada correctamente.']);

        return to_route('empresas.catalogo-faltas-reglamento.index');
    }

    public function destroy(FaltaReglamentoCatalogo $faltaReglamentoCatalogo): RedirectResponse
    {
        Gate::authorize('delete', $faltaReglamentoCatalogo);
        $faltaReglamentoCatalogo->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Falta del catálogo archivada correctamente.']);

        return to_route('empresas.catalogo-faltas-reglamento.index');
    }

    public function restore(FaltaReglamentoCatalogo $faltaReglamentoCatalogo): RedirectResponse
    {
        Gate::authorize('restore', $faltaReglamentoCatalogo);
        $faltaReglamentoCatalogo->restore();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Falta del catálogo restaurada correctamente.']);

        return to_route('empresas.catalogo-faltas-reglamento.index');
    }

    /**
     * @param  ConcreteLengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, mixed>
     */
    private function paginatorWithoutUrls(ConcreteLengthAwarePaginator $paginator): array
    {
        $data = $paginator->toArray();
        $data['links'] = array_map(static function (array $link): array {
            $query = is_string($link['url'] ?? null) ? parse_url($link['url'], PHP_URL_QUERY) : null;
            parse_str(is_string($query) ? $query : '', $parameters);

            return [
                'url' => null,
                'label' => (string) $link['label'],
                'active' => (bool) $link['active'],
                'page' => isset($parameters['page']) ? (int) $parameters['page'] : null,
            ];
        }, $data['links']);

        return $data;
    }

    private function sessionKey(int $empresaId): string
    {
        return "faltas_reglamento.catalogo.{$empresaId}";
    }
}
