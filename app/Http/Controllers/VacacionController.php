<?php

namespace App\Http\Controllers;

use App\Actions\Vacaciones\CrearVacacion;
use App\Actions\Vacaciones\ResolverVacacion;
use App\EstadoVacacion;
use App\Http\Requests\Vacaciones\AprobarVacacionRequest;
use App\Http\Requests\Vacaciones\CambiarPaginaVacacionesRequest;
use App\Http\Requests\Vacaciones\FiltrarVacacionesRequest;
use App\Http\Requests\Vacaciones\RechazarVacacionRequest;
use App\Http\Requests\Vacaciones\StoreVacacionRequest;
use App\Models\DiaFestivo;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Vacacion;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VacacionController extends Controller
{
    private const PAGE_SIZE = 15;

    public function __construct(
        private readonly EmpresaContext $empresaContext,
    ) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('viewAny', Vacacion::class);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $sessionKey = $this->sessionKey($empresa->id);
        $filters = $request->session()->get("{$sessionKey}.filters", [
            'search' => '',
            'estado' => null,
        ]);
        $filters = is_array($filters) ? $filters : ['search' => '', 'estado' => null];
        $filters = [
            'search' => is_string($filters['search'] ?? null) ? $filters['search'] : '',
            'estado' => is_string($filters['estado'] ?? null) ? $filters['estado'] : null,
        ];
        $puedeRevisar = $user->can('vacaciones.review');
        $query = $this->queryVisibleTo($empresa, $user, $puedeRevisar)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];
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
            ->with($this->vacationRelations())
            ->orderByRaw("CASE estado WHEN 'PENDIENTE' THEN 0 WHEN 'AUTORIZADA' THEN 1 WHEN 'VENCIDA' THEN 2 ELSE 3 END")
            ->orderBy('fecha_inicio')
            ->orderByDesc('id');

        $page = max((int) $request->session()->get("{$sessionKey}.page", 1), 1);
        $vacaciones = $query->paginate(self::PAGE_SIZE, ['*'], 'page', $page);

        if ($page > $vacaciones->lastPage() && $vacaciones->lastPage() > 0) {
            $page = $vacaciones->lastPage();
            $request->session()->put("{$sessionKey}.page", $page);
            $vacaciones = $query->paginate(self::PAGE_SIZE, ['*'], 'page', $page);
        }

        $vacaciones->through(fn (Vacacion $vacacion): array => $this->serializeVacacion($vacacion, $puedeRevisar));

        $empleados = $this->empleadosParaSolicitud($empresa, $user);
        $opcionesEmpleados = $this->opcionesEmpleados($empresa, $empleados);

        $proximas = null;

        if ($puedeRevisar) {
            $proximasPage = max((int) $request->session()->get("{$sessionKey}.proximas_page", 1), 1);
            $proximas = Vacacion::query()
                ->where('empresa_id', $empresa->id)
                ->whereIn('estado', [EstadoVacacion::Pendiente->value, EstadoVacacion::Autorizada->value])
                ->whereDate('fecha_fin', '>=', now($empresa->zona_horaria)->toDateString())
                ->with($this->vacationRelations())
                ->orderBy('fecha_inicio')
                ->orderByDesc('id')
                ->paginate(self::PAGE_SIZE, ['*'], 'page', $proximasPage);

            if ($proximasPage > $proximas->lastPage() && $proximas->lastPage() > 0) {
                $proximasPage = $proximas->lastPage();
                $request->session()->put("{$sessionKey}.proximas_page", $proximasPage);
                $proximas = Vacacion::query()
                    ->where('empresa_id', $empresa->id)
                    ->whereIn('estado', [EstadoVacacion::Pendiente->value, EstadoVacacion::Autorizada->value])
                    ->whereDate('fecha_fin', '>=', now($empresa->zona_horaria)->toDateString())
                    ->with($this->vacationRelations())
                    ->orderBy('fecha_inicio')
                    ->orderByDesc('id')
                    ->paginate(self::PAGE_SIZE, ['*'], 'page', $proximasPage);
            }

            $proximas->through(fn (Vacacion $vacacion): array => $this->serializeVacacion($vacacion, true));
        }

        $puedeAdministrarFestivos = $user->can('vacaciones.manage_holidays');

        $festivosPage = max((int) $request->session()->get("{$sessionKey}.festivos_page", 1), 1);
        $festivosQuery = $empresa->diasFestivos()
            ->select(['id', 'empresa_id', 'nombre', 'fecha'])
            ->orderBy('fecha')
            ->orderBy('id');
        $diasFestivos = $festivosQuery->paginate(self::PAGE_SIZE, ['*'], 'page', $festivosPage);

        if ($festivosPage > $diasFestivos->lastPage() && $diasFestivos->lastPage() > 0) {
            $festivosPage = $diasFestivos->lastPage();
            $request->session()->put("{$sessionKey}.festivos_page", $festivosPage);
            $diasFestivos = $festivosQuery->paginate(self::PAGE_SIZE, ['*'], 'page', $festivosPage);
        }

        $diasFestivos->through(static fn (DiaFestivo $diaFestivo): array => [
            'id' => $diaFestivo->id,
            'nombre' => $diaFestivo->nombre,
            'fecha' => $diaFestivo->fecha->toDateString(),
        ]);

        return Inertia::render('vacaciones/index', [
            'vacaciones' => $this->paginatorWithoutUrls($vacaciones),
            'proximas' => $proximas ? $this->paginatorWithoutUrls($proximas) : null,
            'diasFestivos' => $this->paginatorWithoutUrls($diasFestivos),
            'filters' => $filters,
            'empleados' => $opcionesEmpleados,
            'selfEmployeeId' => $this->empleadoPropio($empresa, $user)?->id,
            'permissions' => [
                'canCreate' => $user->can('vacaciones.create'),
                'canCreateForOthers' => $user->can('vacaciones.create_for_others'),
                'canReview' => $puedeRevisar,
                'canManageHolidays' => $puedeAdministrarFestivos,
            ],
            'timezone' => $empresa->zona_horaria,
        ]);
    }

    public function filtrar(FiltrarVacacionesRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $sessionKey = $this->sessionKey($empresaId);
        $request->session()->put("{$sessionKey}.filters", [
            'search' => $request->validated('search') ?? '',
            'estado' => $request->validated('estado'),
        ]);
        $request->session()->put("{$sessionKey}.page", 1);

        return to_route('empresas.vacaciones.index');
    }

    public function cambiarPagina(CambiarPaginaVacacionesRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $sessionKey = $this->sessionKey($empresaId);
        $listado = $request->validated('listado', 'solicitudes');
        $key = match ($listado) {
            'proximas' => 'proximas_page',
            'festivos' => 'festivos_page',
            default => 'page',
        };
        $request->session()->put("{$sessionKey}.{$key}", (int) $request->validated('page'));

        return to_route('empresas.vacaciones.index');
    }

    public function store(StoreVacacionRequest $request, CrearVacacion $crearVacacion): RedirectResponse
    {
        $solicitante = $request->user();
        abort_unless($solicitante instanceof User, 401);

        $crearVacacion->handle($solicitante, $request->vacationData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud de vacaciones enviada al administrador.',
        ]);

        return to_route('empresas.vacaciones.index');
    }

    public function autorizar(
        AprobarVacacionRequest $request,
        Vacacion $vacacion,
        ResolverVacacion $resolverVacacion,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $resolverVacacion->handle($vacacion, $actor, EstadoVacacion::Autorizada);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Vacaciones autorizadas y saldo actualizado.',
        ]);

        return to_route('empresas.vacaciones.index');
    }

    public function rechazar(
        RechazarVacacionRequest $request,
        Vacacion $vacacion,
        ResolverVacacion $resolverVacacion,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $resolverVacacion->handle(
            $vacacion,
            $actor,
            EstadoVacacion::Rechazada,
            $request->validated('comentarios_rechazo'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'La solicitud de vacaciones fue rechazada.',
        ]);

        return to_route('empresas.vacaciones.index');
    }

    /** @return Builder<Vacacion> */
    private function queryVisibleTo(Empresa $empresa, User $user, bool $puedeRevisar): Builder
    {
        $query = Vacacion::query()->where('empresa_id', $empresa->id);

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
        if (! $user->can('vacaciones.create_for_others')) {
            $empleado = $this->empleadoPropio($empresa, $user);

            return $empleado ? [$empleado] : [];
        }

        return Empleado::query()
            ->where('empresa_id', $empresa->id)
            ->orderBy('nombre')
            ->get(['id', 'empresa_id', 'nombre', 'dias_vacaciones', 'dias_descanso'])
            ->all();
    }

    /** @param array<int, Empleado> $empleados
     * @return list<array{id: int, nombre: string, diasVacaciones: int|null, ultimaVacacion: string|null}>
     */
    private function opcionesEmpleados(Empresa $empresa, array $empleados): array
    {
        if ($empleados === []) {
            return [];
        }

        $today = now($empresa->zona_horaria)->toDateString();
        $latest = Vacacion::query()
            ->selectRaw('empleado_id, MAX(fecha_fin) AS ultima_fecha')
            ->where('empresa_id', $empresa->id)
            ->whereIn('empleado_id', array_map(static fn (Empleado $empleado): int => $empleado->id, $empleados))
            ->whereIn('estado', [EstadoVacacion::Autorizada->value, EstadoVacacion::Vencida->value])
            ->whereDate('fecha_fin', '<', $today)
            ->groupBy('empleado_id')
            ->pluck('ultima_fecha', 'empleado_id');

        return array_values(array_map(static function (Empleado $empleado) use ($latest): array {
            $ultimaVacacion = $latest->get($empleado->id);

            return [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre,
                'diasVacaciones' => $empleado->dias_vacaciones,
                'ultimaVacacion' => is_string($ultimaVacacion) ? $ultimaVacacion : null,
            ];
        }, $empleados));
    }

    /** @return list<string> */
    private function vacationRelations(): array
    {
        return [
            'empleado:id,empresa_id,nombre,dias_vacaciones',
            'solicitante:id,name,email',
            'resueltoPor:id,name',
            'empleadosCobertura:id,empresa_id,nombre',
        ];
    }

    /** @return array<string, mixed> */
    private function serializeVacacion(Vacacion $vacacion, bool $puedeRevisar): array
    {
        return [
            'id' => $vacacion->id,
            'empleado' => $vacacion->empleado->nombre,
            'solicitante' => $vacacion->solicitante_user_id === null
                ? $vacacion->solicitante_nombre
                : $vacacion->solicitante->name,
            'fechaInicio' => $vacacion->fecha_inicio->toDateString(),
            'fechaFin' => $vacacion->fecha_fin->toDateString(),
            'diasSolicitados' => $vacacion->dias_solicitados,
            'saldoAlSolicitar' => $vacacion->saldo_dias_al_solicitar,
            'ultimaVacacion' => $vacacion->fecha_ultima_vacacion_al_solicitar?->toDateString(),
            'estado' => $vacacion->estado->value,
            'estadoLabel' => $vacacion->estado->label(),
            'comentarios' => $vacacion->comentarios,
            'comentariosRechazo' => $vacacion->comentarios_rechazo,
            'empleadosCobertura' => $vacacion->empleadosCobertura->pluck('nombre')->values()->all(),
            'resueltoPor' => $vacacion->resuelto_por_user_id === null
                ? null
                : $vacacion->resueltoPor->name,
            'resueltoAt' => $vacacion->resuelto_at?->toISOString(),
            'puedeResolver' => $puedeRevisar && $vacacion->estado === EstadoVacacion::Pendiente,
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
        return "vacaciones.{$empresaId}";
    }
}
