<?php

namespace App\Http\Controllers\PermisosLaborales;

use App\Actions\PermisosLaborales\CrearPermisoLaboral;
use App\Actions\PermisosLaborales\ResolverPermisoLaboral;
use App\Enums\PermisosLaborales\EstadoPermisoLaboral;
use App\Http\Controllers\Controller;
use App\Http\Requests\PermisosLaborales\AprobarPermisoLaboralRequest;
use App\Http\Requests\PermisosLaborales\CambiarPaginaPermisosLaboralesRequest;
use App\Http\Requests\PermisosLaborales\FiltrarPermisosLaboralesRequest;
use App\Http\Requests\PermisosLaborales\RechazarPermisoLaboralRequest;
use App\Http\Requests\PermisosLaborales\StorePermisoLaboralRequest;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\PermisosLaborales\PermisoLaboral;
use App\Models\PermisosLaborales\TipoPermiso;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PermisoLaboralController extends Controller
{
    private const PAGE_SIZE = 15;

    private const PERMISO_LIST_COLUMNS = [
        'id',
        'empresa_id',
        'empleado_id',
        'tipo_permiso_id',
        'solicitante_user_id',
        'solicitante_nombre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'comentarios',
        'comentarios_rechazo',
        'resuelto_por_user_id',
        'resuelto_at',
    ];

    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('viewAny', PermisoLaboral::class);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $sessionKey = $this->sessionKey($empresa->id);
        $filters = $request->session()->get("{$sessionKey}.filters", ['search' => '', 'estado' => null]);
        $filters = is_array($filters) ? $filters : ['search' => '', 'estado' => null];
        $filters = [
            'search' => is_string($filters['search'] ?? null) ? $filters['search'] : '',
            'estado' => is_string($filters['estado'] ?? null) ? $filters['estado'] : null,
        ];
        $puedeRevisar = $user->can('permisos_laborales.review');
        $query = $this->queryVisibleTo($empresa, $user, $puedeRevisar)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = Str::squish($filters['search']);
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereHas('empleado', fn (Builder $empleado) => $empleado
                            ->where('nombre', 'like', "%{$search}%"))
                        ->orWhere('solicitante_nombre', 'like', "%{$search}%")
                        ->orWhere('solicitante_correo', 'like', "%{$search}%");
                });
            })
            ->when($filters['estado'] !== null, fn (Builder $query) => $query
                ->where('estado', $filters['estado']))
            ->with($this->permisoRelations())
            ->orderByRaw("CASE estado WHEN 'PENDIENTE' THEN 0 WHEN 'AUTORIZADO' THEN 1 WHEN 'VENCIDO' THEN 2 ELSE 3 END")
            ->orderBy('fecha_inicio')
            ->orderByDesc('id');

        $page = max((int) $request->session()->get("{$sessionKey}.page", 1), 1);
        $permisos = $query->paginate(self::PAGE_SIZE, self::PERMISO_LIST_COLUMNS, 'page', $page);

        if ($page > $permisos->lastPage() && $permisos->lastPage() > 0) {
            $page = $permisos->lastPage();
            $request->session()->put("{$sessionKey}.page", $page);
            $permisos = $query->paginate(self::PAGE_SIZE, self::PERMISO_LIST_COLUMNS, 'page', $page);
        }

        $permisos->through(fn (PermisoLaboral $permisoLaboral): array => $this->serializePermiso($permisoLaboral, $puedeRevisar, $user));

        $proximas = null;

        if ($puedeRevisar) {
            $proximasPage = max((int) $request->session()->get("{$sessionKey}.proximas_page", 1), 1);
            $proximas = PermisoLaboral::query()
                ->where('empresa_id', $empresa->id)
                ->whereIn('estado', [EstadoPermisoLaboral::Pendiente->value, EstadoPermisoLaboral::Autorizado->value])
                ->where('fecha_fin', '>=', now($empresa->zona_horaria)->toDateString())
                ->with($this->permisoRelations())
                ->orderBy('fecha_inicio')
                ->orderByDesc('id')
                ->paginate(self::PAGE_SIZE, self::PERMISO_LIST_COLUMNS, 'page', $proximasPage);

            if ($proximasPage > $proximas->lastPage() && $proximas->lastPage() > 0) {
                $proximasPage = $proximas->lastPage();
                $request->session()->put("{$sessionKey}.proximas_page", $proximasPage);
                $proximas = PermisoLaboral::query()
                    ->where('empresa_id', $empresa->id)
                    ->whereIn('estado', [EstadoPermisoLaboral::Pendiente->value, EstadoPermisoLaboral::Autorizado->value])
                    ->where('fecha_fin', '>=', now($empresa->zona_horaria)->toDateString())
                    ->with($this->permisoRelations())
                    ->orderBy('fecha_inicio')
                    ->orderByDesc('id')
                    ->paginate(self::PAGE_SIZE, self::PERMISO_LIST_COLUMNS, 'page', $proximasPage);
            }

            $proximas->through(fn (PermisoLaboral $permisoLaboral): array => $this->serializePermiso($permisoLaboral, true, $user));
        }

        $empleados = $this->empleadosParaSolicitud($empresa, $user);
        $empleadosCobertura = $user->can('permisos_laborales.create')
            ? ($user->can('permisos_laborales.create_for_others')
                ? $empleados
                : $this->todosLosEmpleados($empresa))
            : [];
        $tiposPermiso = TipoPermiso::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('permisos-laborales/index', [
            'permisos' => $this->paginatorWithoutUrls($permisos),
            'proximas' => $proximas ? $this->paginatorWithoutUrls($proximas) : null,
            'filters' => $filters,
            'empleados' => array_map(static fn (Empleado $empleado): array => [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre,
            ], $empleados),
            'empleadosCobertura' => array_map(static fn (Empleado $empleado): array => [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre,
            ], $empleadosCobertura),
            'tiposPermiso' => $tiposPermiso->map(static fn (TipoPermiso $tipoPermiso): array => [
                'id' => $tipoPermiso->id,
                'nombre' => $tipoPermiso->nombre,
            ])->values()->all(),
            'selfEmployeeId' => $this->empleadoPropio($empresa, $user)?->id,
            'permissions' => [
                'canCreate' => $user->can('permisos_laborales.create'),
                'canCreateForOthers' => $user->can('permisos_laborales.create_for_others'),
                'canReview' => $puedeRevisar,
            ],
        ]);
    }

    public function filtrar(FiltrarPermisosLaboralesRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $sessionKey = $this->sessionKey($empresaId);
        $request->session()->put("{$sessionKey}.filters", [
            'search' => $request->validated('search') ?? '',
            'estado' => $request->validated('estado'),
        ]);
        $request->session()->put("{$sessionKey}.page", 1);

        return to_route('empresas.permisos-laborales.index');
    }

    public function cambiarPagina(CambiarPaginaPermisosLaboralesRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $sessionKey = $this->sessionKey($empresaId);
        $key = $request->validated('listado') === 'proximas' ? 'proximas_page' : 'page';
        $request->session()->put("{$sessionKey}.{$key}", (int) $request->validated('page'));

        return to_route('empresas.permisos-laborales.index');
    }

    public function store(StorePermisoLaboralRequest $request, CrearPermisoLaboral $crearPermisoLaboral): RedirectResponse
    {
        $solicitante = $request->user();
        abort_unless($solicitante instanceof User, 401);

        $crearPermisoLaboral->handle($solicitante, $request->permisoData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud de permiso laboral enviada al administrador.',
        ]);

        return to_route('empresas.permisos-laborales.index');
    }

    public function autorizar(
        AprobarPermisoLaboralRequest $request,
        PermisoLaboral $permisoLaboral,
        ResolverPermisoLaboral $resolverPermisoLaboral,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $resolverPermisoLaboral->handle($permisoLaboral, $actor, EstadoPermisoLaboral::Autorizado);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Permiso laboral autorizado.',
        ]);

        return to_route('empresas.permisos-laborales.index');
    }

    public function rechazar(
        RechazarPermisoLaboralRequest $request,
        PermisoLaboral $permisoLaboral,
        ResolverPermisoLaboral $resolverPermisoLaboral,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $resolverPermisoLaboral->handle(
            $permisoLaboral,
            $actor,
            EstadoPermisoLaboral::Rechazado,
            $request->validated('comentarios_rechazo'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'La solicitud de permiso laboral fue rechazada.',
        ]);

        return to_route('empresas.permisos-laborales.index');
    }

    /** @return Builder<PermisoLaboral> */
    private function queryVisibleTo(Empresa $empresa, User $user, bool $puedeRevisar): Builder
    {
        $query = PermisoLaboral::query()->where('empresa_id', $empresa->id);

        if ($puedeRevisar) {
            return $query;
        }

        $empleadoId = $this->empleadoPropio($empresa, $user)?->id;

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
            ->where('empresa_id', $empresa->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /** @return array<int, Empleado> */
    private function empleadosParaSolicitud(Empresa $empresa, User $user): array
    {
        if (! $user->can('permisos_laborales.create_for_others')) {
            $empleado = $this->empleadoPropio($empresa, $user);

            return $empleado ? [$empleado] : [];
        }

        return $this->todosLosEmpleados($empresa);
    }

    /** @return array<int, Empleado> */
    private function todosLosEmpleados(Empresa $empresa): array
    {
        return Empleado::query()
            ->where('empresa_id', $empresa->id)
            ->orderBy('nombre')
            ->get(['id', 'empresa_id', 'nombre'])
            ->all();
    }

    /** @return array<int, string> */
    private function permisoRelations(): array
    {
        return [
            'tipoPermiso:id,empresa_id,nombre,descripcion,activo,deleted_at',
            'empleado:id,empresa_id,nombre,user_id',
            'solicitante:id,name,email',
            'resueltoPor:id,name',
            'empleadosCobertura:id,empresa_id,nombre',
        ];
    }

    /** @return array<string, mixed> */
    private function serializePermiso(PermisoLaboral $permisoLaboral, bool $puedeRevisar, User $user): array
    {
        return [
            'id' => $permisoLaboral->id,
            'empleado' => $permisoLaboral->empleado->nombre,
            'tipoPermiso' => $permisoLaboral->tipoPermiso?->nombre,
            'solicitante' => $permisoLaboral->solicitante_user_id === null
                ? $permisoLaboral->solicitante_nombre
                : $permisoLaboral->solicitante->name,
            'fechaInicio' => $permisoLaboral->fecha_inicio->toDateString(),
            'fechaFin' => $permisoLaboral->fecha_fin->toDateString(),
            'estado' => $permisoLaboral->estado->value,
            'estadoLabel' => $permisoLaboral->estado->label(),
            'comentarios' => $permisoLaboral->comentarios,
            'comentariosRechazo' => $permisoLaboral->comentarios_rechazo,
            'empleadosCobertura' => $permisoLaboral->empleadosCobertura->pluck('nombre')->values()->all(),
            'resueltoPor' => $permisoLaboral->resuelto_por_user_id === null
                ? null
                : $permisoLaboral->resueltoPor->name,
            'resueltoAt' => $permisoLaboral->resuelto_at?->toISOString(),
            'puedeResolver' => $puedeRevisar
                && $permisoLaboral->solicitante_user_id !== $user->id
                && $permisoLaboral->estado === EstadoPermisoLaboral::Pendiente,
        ];
    }

    /**
     * @template TItem
     *
     * @param  ConcreteLengthAwarePaginator<int, TItem>  $paginator
     * @return array<string, mixed>
     */
    private function paginatorWithoutUrls(ConcreteLengthAwarePaginator $paginator): array
    {
        $data = $paginator->toArray();
        $data['links'] = array_map(static function (array $link): array {
            $page = null;

            if (is_string($link['url'] ?? null)) {
                $query = parse_url($link['url'], PHP_URL_QUERY);
                parse_str(is_string($query) ? $query : '', $parameters);
                $page = isset($parameters['page']) ? (int) $parameters['page'] : null;
            }

            return [
                'url' => null,
                'label' => (string) $link['label'],
                'active' => (bool) $link['active'],
                'page' => $page,
            ];
        }, $data['links']);

        return $data;
    }

    private function sessionKey(int $empresaId): string
    {
        return "permisos_laborales.{$empresaId}";
    }
}
