<?php

namespace App\Http\Controllers\FaltasReglamento;

use App\Actions\FaltasReglamento\CrearFaltaReglamento;
use App\Actions\FaltasReglamento\ResolverFaltaReglamento;
use App\Enums\FaltasReglamento\EstadoFaltaReglamento;
use App\Http\Controllers\Controller;
use App\Http\Requests\FaltasReglamento\AprobarFaltaReglamentoRequest;
use App\Http\Requests\FaltasReglamento\CambiarPaginaFaltasReglamentoRequest;
use App\Http\Requests\FaltasReglamento\FiltrarFaltasReglamentoRequest;
use App\Http\Requests\FaltasReglamento\RechazarFaltaReglamentoRequest;
use App\Http\Requests\FaltasReglamento\StoreFaltaReglamentoRequest;
use App\Jobs\FaltasReglamento\EnviarNotificacionFaltaReglamento;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\FaltasReglamento\FaltaReglamento;
use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\FaltasReglamento\TipoFaltaReglamento;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FaltaReglamentoController extends Controller
{
    private const PAGE_SIZE = 15;

    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('viewAny', FaltaReglamento::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $canReview = $user->can('faltas_reglamento.review');
        $sessionKey = $this->sessionKey($empresa->id);
        $storedFilters = $request->session()->get("{$sessionKey}.filters", []);
        $storedFilters = is_array($storedFilters) ? $storedFilters : [];
        $filters = [
            'search' => is_string($storedFilters['search'] ?? null) ? $storedFilters['search'] : '',
            'estado' => in_array($storedFilters['estado'] ?? null, array_map(
                static fn (EstadoFaltaReglamento $estado): string => $estado->value,
                EstadoFaltaReglamento::cases(),
            ), true)
                ? $storedFilters['estado']
                : null,
            'empleadoId' => is_numeric($storedFilters['empleado_id'] ?? null) ? (int) $storedFilters['empleado_id'] : null,
            'tipoFaltaReglamentoId' => is_numeric($storedFilters['tipo_falta_reglamento_id'] ?? null)
                ? (int) $storedFilters['tipo_falta_reglamento_id']
                : null,
            'fechaDesde' => is_string($storedFilters['fecha_desde'] ?? null) ? $storedFilters['fecha_desde'] : null,
            'fechaHasta' => is_string($storedFilters['fecha_hasta'] ?? null) ? $storedFilters['fecha_hasta'] : null,
        ];

        $query = $this->queryVisibleTo($empresa, $user, $canReview)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('empleado', fn (Builder $empleado) => $empleado->where('nombre', 'like', "%{$search}%"))
                        ->orWhere('solicitante_nombre', 'like', "%{$search}%")
                        ->orWhere('solicitante_correo', 'like', "%{$search}%")
                        ->orWhereHas('faltaCatalogo', fn (Builder $catalogo) => $catalogo
                            ->where('nombre', 'like', "%{$search}%")
                            ->orWhereHas('tipoFalta', fn (Builder $tipo) => $tipo->where('nombre', 'like', "%{$search}%")));
                });
            })
            ->when($filters['estado'] !== null, fn (Builder $query) => $query->where('estado', $filters['estado']))
            ->when($canReview && $filters['empleadoId'] !== null, fn (Builder $query) => $query->where('empleado_id', $filters['empleadoId']))
            ->when($filters['tipoFaltaReglamentoId'] !== null, fn (Builder $query) => $query->whereHas(
                'faltaCatalogo',
                fn (Builder $catalogo) => $catalogo->where('tipo_falta_reglamento_id', $filters['tipoFaltaReglamentoId']),
            ))
            ->when($filters['fechaDesde'] !== null, fn (Builder $query) => $query->whereDate('fecha_ocurrencia', '>=', $filters['fechaDesde']))
            ->when($filters['fechaHasta'] !== null, fn (Builder $query) => $query->whereDate('fecha_ocurrencia', '<=', $filters['fechaHasta']))
            ->with([
                'empleado:id,empresa_id,nombre,user_id',
                'empleado.tiposFaltaReglamento:id,nombre',
                'faltaCatalogo:id,empresa_id,tipo_falta_reglamento_id,nombre,deleted_at',
                'faltaCatalogo.tipoFalta:id,empresa_id,nombre,deleted_at',
                'solicitante:id,name,email',
                'resueltoPor:id,name',
                'evidencias:id,empresa_id,falta_reglamento_id,nombre,mime_type,file_extension',
            ])
            ->orderByRaw("CASE estado WHEN 'PENDIENTE' THEN 0 WHEN 'AUTORIZADA' THEN 1 ELSE 2 END")
            ->orderByDesc('fecha_ocurrencia')
            ->orderByDesc('id');

        $page = max((int) $request->session()->get("{$sessionKey}.page", 1), 1);
        $faltas = $query->paginate(self::PAGE_SIZE, ['*'], 'page', $page);

        if ($page > $faltas->lastPage()) {
            $page = $faltas->lastPage();
            $request->session()->put("{$sessionKey}.page", $page);
            $faltas = $query->paginate(self::PAGE_SIZE, ['*'], 'page', $page);
        }

        $faltas->through(fn (FaltaReglamento $falta): array => $this->serializeFalta($falta));
        $empleadoPropio = $this->empleadoPropio($empresa, $user);
        $empleados = ($canReview || $user->can('faltas_reglamento.create_for_others'))
            ? Empleado::query()->whereBelongsTo($empresa)->orderBy('nombre')->get(['id', 'nombre'])
            : ($empleadoPropio === null ? collect() : collect([$empleadoPropio]));
        $empleadosFiltro = $canReview
            ? Empleado::withTrashed()->whereBelongsTo($empresa)->orderBy('nombre')->get(['id', 'nombre', 'deleted_at'])
            : collect();
        $tiposFalta = TipoFaltaReglamento::withTrashed()
            ->where('empresa_id', $empresa->id)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'deleted_at']);
        $tiposFaltaDisponibles = TipoFaltaReglamento::query()
            ->whereBelongsTo($empresa)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
        $faltasDisponibles = FaltaReglamentoCatalogo::query()
            ->whereBelongsTo($empresa)
            ->where('activo', true)
            ->whereHas('tipoFalta', static fn (Builder $query) => $query
                ->where('activo', true)
                ->whereNull('deleted_at'))
            ->with('tipoFalta:id,nombre')
            ->orderBy('tipo_falta_reglamento_id')
            ->orderBy('nombre')
            ->get(['id', 'tipo_falta_reglamento_id', 'nombre']);

        return Inertia::render('faltas-reglamento/index', [
            'faltas' => $this->paginatorWithoutUrls($faltas),
            'filters' => $filters,
            'empleados' => $empleados->map(static fn (Empleado $empleado): array => [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre,
            ])->values(),
            'empleadosFiltro' => $empleadosFiltro->map(static fn (Empleado $empleado): array => [
                'id' => $empleado->id,
                'nombre' => $empleado->deleted_at === null
                    ? $empleado->nombre
                    : "{$empleado->nombre} (archivado)",
            ])->values(),
            'tiposFalta' => $tiposFalta->map(static fn (TipoFaltaReglamento $tipo): array => [
                'id' => $tipo->id,
                'nombre' => $tipo->nombre,
                'archivado' => $tipo->deleted_at !== null,
            ])->values(),
            'tiposFaltaDisponibles' => $tiposFaltaDisponibles->map(static fn (TipoFaltaReglamento $tipo): array => [
                'id' => $tipo->id,
                'nombre' => $tipo->nombre,
            ])->values(),
            'faltasDisponibles' => $faltasDisponibles->map(static fn (FaltaReglamentoCatalogo $falta): array => [
                'id' => $falta->id,
                'tipoFaltaReglamentoId' => $falta->tipo_falta_reglamento_id,
                'tipoFalta' => $falta->tipoFalta->nombre,
                'nombre' => $falta->nombre,
            ])->values(),
            'selfEmployeeId' => $empleadoPropio?->id,
            'permissions' => [
                'canCreate' => $user->can('faltas_reglamento.create'),
                'canCreateForOthers' => $user->can('faltas_reglamento.create_for_others'),
                'canReview' => $canReview,
            ],
        ]);
    }

    public function filtrar(FiltrarFaltasReglamentoRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $validated = $request->validated();
        $request->session()->put($this->sessionKey($empresaId).'.filters', [
            'search' => $validated['search'] ?? '',
            'estado' => $validated['estado'] ?? null,
            'empleado_id' => isset($validated['empleado_id']) ? (int) $validated['empleado_id'] : null,
            'tipo_falta_reglamento_id' => isset($validated['tipo_falta_reglamento_id'])
                ? (int) $validated['tipo_falta_reglamento_id']
                : null,
            'fecha_desde' => $validated['fecha_desde'] ?? null,
            'fecha_hasta' => $validated['fecha_hasta'] ?? null,
        ]);
        $request->session()->put($this->sessionKey($empresaId).'.page', 1);

        return to_route('empresas.faltas-reglamento.index');
    }

    public function cambiarPagina(CambiarPaginaFaltasReglamentoRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $request->session()->put($this->sessionKey($empresaId).'.page', (int) $request->validated('page'));

        return to_route('empresas.faltas-reglamento.index');
    }

    public function store(StoreFaltaReglamentoRequest $request, CrearFaltaReglamento $crearFaltaReglamento): RedirectResponse
    {
        $solicitante = $request->user();
        abort_unless($solicitante instanceof User, 401);
        $falta = $crearFaltaReglamento->handle($solicitante, $request->faltaData());
        EnviarNotificacionFaltaReglamento::dispatch($falta->id, 'solicitud');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reporte enviado para revisión del administrador.']);

        return to_route('empresas.faltas-reglamento.index');
    }

    public function autorizar(
        AprobarFaltaReglamentoRequest $request,
        FaltaReglamento $faltaReglamento,
        ResolverFaltaReglamento $resolver,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $falta = $resolver->handle($faltaReglamento, $actor, EstadoFaltaReglamento::Autorizada);
        EnviarNotificacionFaltaReglamento::dispatch($falta->id, 'resolucion');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Falta al reglamento autorizada.']);

        return to_route('empresas.faltas-reglamento.index');
    }

    public function rechazar(
        RechazarFaltaReglamentoRequest $request,
        FaltaReglamento $faltaReglamento,
        ResolverFaltaReglamento $resolver,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $falta = $resolver->handle(
            $faltaReglamento,
            $actor,
            EstadoFaltaReglamento::Rechazada,
            $request->validated('comentarios_rechazo'),
        );
        EnviarNotificacionFaltaReglamento::dispatch($falta->id, 'resolucion');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reporte rechazado y guardado en el histórico.']);

        return to_route('empresas.faltas-reglamento.index');
    }

    /** @return Builder<FaltaReglamento> */
    private function queryVisibleTo(Empresa $empresa, User $user, bool $canReview): Builder
    {
        $query = FaltaReglamento::query()->where('empresa_id', $empresa->id);

        if ($canReview) {
            return $query;
        }

        $empleadoId = $this->empleadoVinculado($empresa, $user)?->id;

        return $query->where(function (Builder $query) use ($user, $empleadoId): void {
            $query->where('solicitante_user_id', $user->id);

            if ($empleadoId !== null) {
                $query->orWhere('empleado_id', $empleadoId);
            }
        });
    }

    private function empleadoPropio(Empresa $empresa, User $user): ?Empleado
    {
        return Empleado::query()
            ->whereBelongsTo($empresa)
            ->where('user_id', $user->id)
            ->first();
    }

    private function empleadoVinculado(Empresa $empresa, User $user): ?Empleado
    {
        return Empleado::withTrashed()
            ->whereBelongsTo($empresa)
            ->where('user_id', $user->id)
            ->first();
    }

    /** @return array<string, mixed> */
    private function serializeFalta(FaltaReglamento $falta): array
    {
        $tipo = $falta->faltaCatalogo->tipoFalta;
        $empleado = $falta->empleado;
        $conteoTipo = data_get(
            $empleado->tiposFaltaReglamento->firstWhere('id', $tipo->id),
            'pivot.cantidad',
        );

        return [
            'id' => $falta->id,
            'empleado' => $empleado->nombre,
            'falta' => $falta->faltaCatalogo->nombre,
            'tipoFalta' => $tipo->nombre,
            'fechaOcurrencia' => $falta->fecha_ocurrencia->toDateString(),
            'estado' => $falta->estado->value,
            'estadoLabel' => $falta->estado->label(),
            'solicitante' => $falta->solicitante_nombre,
            'comentarios' => $falta->comentarios,
            'comentariosRechazo' => $falta->comentarios_rechazo,
            'resueltoPor' => $falta->resueltoPor?->name,
            'resueltoAt' => $falta->resuelto_at?->toISOString(),
            'conteoTipo' => (int) ($conteoTipo ?? 0),
            'puedeResolver' => Gate::allows('review', $falta),
            'evidencias' => $falta->evidencias->map(static fn ($evidencia): array => [
                'id' => $evidencia->id,
                'nombre' => $evidencia->nombre,
                'mimeType' => $evidencia->mime_type,
                'fileExtension' => $evidencia->file_extension,
            ])->values(),
        ];
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
        return "faltas_reglamento.solicitudes.{$empresaId}";
    }
}
