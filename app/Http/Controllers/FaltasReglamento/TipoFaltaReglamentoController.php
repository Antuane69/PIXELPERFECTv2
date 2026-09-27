<?php

namespace App\Http\Controllers\FaltasReglamento;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaltasReglamento\CambiarPaginaCatalogoFaltaRequest;
use App\Http\Requests\FaltasReglamento\FiltrarTiposFaltaRequest;
use App\Http\Requests\FaltasReglamento\StoreTipoFaltaReglamentoRequest;
use App\Http\Requests\FaltasReglamento\UpdateTipoFaltaReglamentoRequest;
use App\Models\FaltasReglamento\TipoFaltaReglamento;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TipoFaltaReglamentoController extends Controller
{
    private const PAGE_SIZE = 15;

    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('viewAny', TipoFaltaReglamento::class);
        $sessionKey = $this->sessionKey($empresa->id);
        $storedFilters = $request->session()->get("{$sessionKey}.filters", []);
        $storedFilters = is_array($storedFilters) ? $storedFilters : [];
        $filters = [
            'search' => is_string($storedFilters['search'] ?? null) ? $storedFilters['search'] : '',
            'activo' => is_bool($storedFilters['activo'] ?? null) ? $storedFilters['activo'] : null,
            'archivados' => ($storedFilters['archivados'] ?? false) === true,
        ];

        $query = TipoFaltaReglamento::query()
            ->select(['id', 'empresa_id', 'nombre', 'descripcion', 'activo', 'deleted_at'])
            ->whereBelongsTo($empresa)
            ->when($filters['archivados'], fn (Builder $query) => $query->onlyTrashed())
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('nombre', 'like', "%{$filters['search']}%")
                    ->orWhere('descripcion', 'like', "%{$filters['search']}%");
            }))
            ->when($filters['activo'] !== null, fn (Builder $query) => $query->where('activo', $filters['activo']))
            ->orderByDesc('id');

        $page = max((int) $request->session()->get("{$sessionKey}.page", 1), 1);
        $tiposFalta = $query->paginate(self::PAGE_SIZE, ['*'], 'page', $page);

        if ($page > $tiposFalta->lastPage()) {
            $page = $tiposFalta->lastPage();
            $request->session()->put("{$sessionKey}.page", $page);
            $tiposFalta = $query->paginate(self::PAGE_SIZE, ['*'], 'page', $page);
        }

        $tiposFalta->through(static fn (TipoFaltaReglamento $tipo): array => [
            'id' => $tipo->id,
            'nombre' => $tipo->nombre,
            'descripcion' => $tipo->descripcion,
            'activo' => $tipo->activo,
            'archivadoAt' => $tipo->deleted_at?->toISOString(),
        ]);

        return Inertia::render('tipos-falta-reglamento/index', [
            'tiposFalta' => $this->paginatorWithoutUrls($tiposFalta),
            'filters' => $filters,
        ]);
    }

    public function filtrar(FiltrarTiposFaltaRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $request->session()->put($this->sessionKey($empresaId).'.filters', [
            'search' => $request->validated('search') ?? '',
            'activo' => $request->input('activo') === null ? null : $request->boolean('activo'),
            'archivados' => $request->boolean('archivados'),
        ]);
        $request->session()->put($this->sessionKey($empresaId).'.page', 1);

        return to_route('empresas.tipos-falta-reglamento.index');
    }

    public function cambiarPagina(CambiarPaginaCatalogoFaltaRequest $request): RedirectResponse
    {
        Gate::authorize('viewAny', TipoFaltaReglamento::class);
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $request->session()->put($this->sessionKey($empresaId).'.page', (int) $request->validated('page'));

        return to_route('empresas.tipos-falta-reglamento.index');
    }

    public function store(StoreTipoFaltaReglamentoRequest $request): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        TipoFaltaReglamento::query()->create([
            'empresa_id' => $empresa->id,
            ...$request->validated(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tipo de falta creado correctamente.']);

        return to_route('empresas.tipos-falta-reglamento.index');
    }

    public function update(UpdateTipoFaltaReglamentoRequest $request, TipoFaltaReglamento $tipoFaltaReglamento): RedirectResponse
    {
        $tipoFaltaReglamento->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tipo de falta actualizado correctamente.']);

        return to_route('empresas.tipos-falta-reglamento.index');
    }

    public function destroy(TipoFaltaReglamento $tipoFaltaReglamento): RedirectResponse
    {
        Gate::authorize('delete', $tipoFaltaReglamento);
        $tipoFaltaReglamento->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tipo de falta archivado correctamente.']);

        return to_route('empresas.tipos-falta-reglamento.index');
    }

    public function restore(TipoFaltaReglamento $tipoFaltaReglamento): RedirectResponse
    {
        Gate::authorize('restore', $tipoFaltaReglamento);
        $tipoFaltaReglamento->restore();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tipo de falta restaurado correctamente.']);

        return to_route('empresas.tipos-falta-reglamento.index');
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
        return "faltas_reglamento.tipos.{$empresaId}";
    }
}
