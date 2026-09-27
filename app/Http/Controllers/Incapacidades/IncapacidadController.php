<?php

namespace App\Http\Controllers\Incapacidades;

use App\Actions\Incapacidades\CrearIncapacidad;
use App\Actions\Incapacidades\ResolverIncapacidad;
use App\Enums\Incapacidades\EstadoIncapacidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incapacidades\AprobarIncapacidadRequest;
use App\Http\Requests\Incapacidades\CambiarPaginaIncapacidadesRequest;
use App\Http\Requests\Incapacidades\FiltrarIncapacidadesRequest;
use App\Http\Requests\Incapacidades\RechazarIncapacidadRequest;
use App\Http\Requests\Incapacidades\StoreIncapacidadRequest;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Incapacidad;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncapacidadController extends Controller
{
    private const PAGE_SIZE = 15;

    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('viewAny', Incapacidad::class);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $sessionKey = $this->sessionKey($empresa->id);
        $storedFilters = $request->session()->get("{$sessionKey}.filters", ['search' => '', 'estado' => null]);
        $storedFilters = is_array($storedFilters) ? $storedFilters : [];
        $estado = is_string($storedFilters['estado'] ?? null)
            ? EstadoIncapacidad::tryFrom($storedFilters['estado'])?->value
            : null;
        $filters = [
            'search' => is_string($storedFilters['search'] ?? null)
                ? mb_substr(trim($storedFilters['search']), 0, 120)
                : '',
            'estado' => $estado,
        ];
        $puedeRevisar = $user->can('incapacidades.review');
        $columns = [
            'id',
            'empresa_id',
            'empleado_id',
            'solicitante_user_id',
            'resuelto_por_user_id',
            'solicitante_nombre',
            'solicitante_correo',
            'fecha_inicio',
            'fecha_fin',
            'estado',
            'motivo',
            'comentarios_rechazo',
            'resuelto_at',
            'nombre_original',
            'mime_type',
            'extension',
            'created_at',
        ];
        $query = $this->queryVisibleTo($empresa, $user, $puedeRevisar)
            ->select($columns)
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
            ->with([
                'empleado:id,empresa_id,nombre,user_id',
                'solicitante:id,name,email',
                'resueltoPor:id,name',
            ])
            ->orderByRaw("CASE estado WHEN 'PENDIENTE' THEN 0 WHEN 'AUTORIZADA' THEN 1 WHEN 'VENCIDA' THEN 2 ELSE 3 END")
            ->orderBy('fecha_inicio')
            ->orderByDesc('id');

        $page = max((int) $request->session()->get("{$sessionKey}.page", 1), 1);
        $incapacidades = $query->paginate(self::PAGE_SIZE, $columns, 'page', $page);

        if ($page > $incapacidades->lastPage() && $incapacidades->lastPage() > 0) {
            $page = $incapacidades->lastPage();
            $request->session()->put("{$sessionKey}.page", $page);
            $incapacidades = $query->paginate(self::PAGE_SIZE, $columns, 'page', $page);
        }

        $incapacidades->through(fn (Incapacidad $incapacidad): array => $this->serializeIncapacidad(
            $incapacidad,
            $user,
        ));

        $empleados = $this->empleadosParaSolicitud($empresa, $user);

        return Inertia::render('incapacidades/index', [
            'incapacidades' => $this->paginatorWithoutUrls($incapacidades),
            'filters' => $filters,
            'empleados' => array_map(static fn (Empleado $empleado): array => [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre,
            ], $empleados),
            'selfEmployeeId' => $this->empleadoPropio($empresa, $user)?->id,
            'permissions' => [
                'canCreate' => $user->can('incapacidades.create'),
                'canCreateForOthers' => $user->can('incapacidades.create_for_others'),
                'canReview' => $puedeRevisar,
            ],
        ]);
    }

    public function filtrar(FiltrarIncapacidadesRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $sessionKey = $this->sessionKey($empresaId);
        $search = $request->validated('search');
        $estado = $request->validated('estado');
        $request->session()->put("{$sessionKey}.filters", [
            'search' => is_string($search) ? $search : '',
            'estado' => is_string($estado) ? $estado : null,
        ]);
        $request->session()->put("{$sessionKey}.page", 1);

        return to_route('empresas.incapacidades.index');
    }

    public function cambiarPagina(CambiarPaginaIncapacidadesRequest $request): RedirectResponse
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;
        $sessionKey = $this->sessionKey($empresaId);
        $request->session()->put("{$sessionKey}.page", (int) $request->validated('page'));

        return to_route('empresas.incapacidades.index');
    }

    public function store(StoreIncapacidadRequest $request, CrearIncapacidad $crearIncapacidad): RedirectResponse
    {
        $solicitante = $request->user();
        abort_unless($solicitante instanceof User, 401);

        $crearIncapacidad->handle($solicitante, $request->incapacidadData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud de incapacidad enviada al administrador.',
        ]);

        return to_route('empresas.incapacidades.index');
    }

    public function autorizar(
        AprobarIncapacidadRequest $request,
        Incapacidad $incapacidad,
        ResolverIncapacidad $resolverIncapacidad,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $resolverIncapacidad->handle($incapacidad, $actor, EstadoIncapacidad::Autorizada);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Incapacidad autorizada.',
        ]);

        return to_route('empresas.incapacidades.index');
    }

    public function rechazar(
        RechazarIncapacidadRequest $request,
        Incapacidad $incapacidad,
        ResolverIncapacidad $resolverIncapacidad,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $resolverIncapacidad->handle(
            $incapacidad,
            $actor,
            EstadoIncapacidad::Rechazada,
            $request->validated('comentarios_rechazo'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'La solicitud de incapacidad fue rechazada.',
        ]);

        return to_route('empresas.incapacidades.index');
    }

    public function descargarArchivo(Incapacidad $incapacidad): StreamedResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        abort_unless($incapacidad->empresa_id === $empresa->id, 404);
        Gate::authorize('view', $incapacidad);

        $archivo = Incapacidad::query()
            ->where('empresa_id', $empresa->id)
            ->whereKey($incapacidad->id)
            ->first(['id', 'nombre_original', 'mime_type', 'extension', 'archivo']);
        abort_unless($archivo instanceof Incapacidad && is_string($archivo->archivo), 404);

        $contenido = $archivo->archivo;
        $nombre = $archivo->nombre_original ?: 'justificante.'.$archivo->extension;

        return response()->streamDownload(
            static function () use ($contenido): void {
                echo $contenido;
            },
            $nombre,
            [
                'Cache-Control' => 'private, no-store',
                'Content-Length' => (string) strlen($contenido),
                'Content-Type' => $archivo->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /** @return Builder<Incapacidad> */
    private function queryVisibleTo(Empresa $empresa, User $user, bool $puedeRevisar): Builder
    {
        $query = Incapacidad::query()->where('empresa_id', $empresa->id);

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
        if (! $user->can('incapacidades.create_for_others')) {
            $empleado = $this->empleadoPropio($empresa, $user);

            return $empleado ? [$empleado] : [];
        }

        return Empleado::query()
            ->where('empresa_id', $empresa->id)
            ->orderBy('nombre')
            ->get(['id', 'empresa_id', 'nombre'])
            ->all();
    }

    /** @return array<string, mixed> */
    private function serializeIncapacidad(Incapacidad $incapacidad, User $user): array
    {
        return [
            'id' => $incapacidad->id,
            'empleado' => $incapacidad->empleado->nombre,
            'solicitante' => $incapacidad->solicitante_user_id === null
                ? $incapacidad->solicitante_nombre
                : $incapacidad->solicitante->name,
            'fechaInicio' => $incapacidad->fecha_inicio->toDateString(),
            'fechaFin' => $incapacidad->fecha_fin->toDateString(),
            'motivo' => $incapacidad->motivo,
            'estado' => $incapacidad->estado->value,
            'estadoLabel' => $incapacidad->estado->label(),
            'comentariosRechazo' => $incapacidad->comentarios_rechazo,
            'nombreArchivo' => $incapacidad->nombre_original,
            'mimeType' => $incapacidad->mime_type,
            'extension' => $incapacidad->extension,
            'resueltoPor' => $incapacidad->resuelto_por_user_id === null
                ? null
                : $incapacidad->resueltoPor->name,
            'resueltoAt' => $incapacidad->resuelto_at?->toISOString(),
            'puedeResolver' => $user->can('incapacidades.review')
                && $incapacidad->solicitante_user_id !== $user->id
                && $incapacidad->estado === EstadoIncapacidad::Pendiente,
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
        return "incapacidades.{$empresaId}";
    }
}
