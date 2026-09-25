<?php

namespace App\Http\Controllers;

use App\Actions\Empleados\RenderEmpleadoDocumentoPdf;
use App\Actions\Empleados\SaveEmpleadoDocumentoCatalogo;
use App\Http\Requests\Empleados\ListEmpleadoDocumentoCatalogoRequest;
use App\Http\Requests\Empleados\PreviewEmpleadoDocumentoCatalogoRequest;
use App\Http\Requests\Empleados\StoreEmpleadoDocumentoCatalogoRequest;
use App\Http\Requests\Empleados\UpdateEmpleadoDocumentoCatalogoRequest;
use App\Models\EmpleadoCarpeta;
use App\Models\EmpleadoDocumentoCatalogo;
use App\Models\Modulo;
use App\Models\User;
use App\Services\Empleados\EmpleadoDocumentoVariables;
use App\Services\Empleados\ModulosRelacionablesDocumentoCatalogo;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmpleadoDocumentoCatalogoController extends Controller
{
    private const LISTING_SESSION_KEY = 'documentos_catalogo.listing';

    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function index(
        Request $request,
        EmpleadoDocumentoVariables $variables,
        ModulosRelacionablesDocumentoCatalogo $modulosRelacionables,
    ): Response|RedirectResponse {
        if ($request->query->count() > 0) {
            return to_route('empresas.empleados.documentos-catalogo.index');
        }

        $empresa = $this->empresaContext->empresaRequerida();
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        Gate::authorize('viewAny', EmpleadoDocumentoCatalogo::class);

        $modulosDisponibles = $modulosRelacionables->query($usuario, $empresa)
            ->select(['modulos.id', 'modulos.clave', 'modulos.nombre'])
            ->orderBy('modulos.orden')
            ->orderBy('modulos.nombre')
            ->get()
            ->map(static fn (Modulo $modulo): array => [
                'id' => $modulo->id,
                'clave' => $modulo->clave,
                'nombre' => $modulo->nombre,
            ])
            ->values()
            ->all();
        $modulosDisponiblesIds = array_column($modulosDisponibles, 'id');

        $carpetas = EmpleadoCarpeta::query()
            ->select(['id', 'empresa_id', 'nombre', 'creado_por_id'])
            ->whereBelongsTo($empresa)
            ->where('activo', true)
            ->visiblesPara($usuario)
            ->withCount(['documentosCatalogo' => fn (Builder $query): Builder => $query
                ->where('empresa_id', $empresa->id)])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get()
            ->map(static fn (EmpleadoCarpeta $carpeta): array => [
                'id' => $carpeta->id,
                'nombre' => $carpeta->nombre,
                'documentos_count' => $carpeta->documentos_catalogo_count,
            ])
            ->values()
            ->all();

        $listing = $request->session()->pull(self::LISTING_SESSION_KEY, []);
        $requestedFolderId = is_array($listing) ? (int) ($listing['carpeta_id'] ?? 0) : 0;
        $selectedFolder = $requestedFolderId > 0
            ? EmpleadoCarpeta::query()
                ->whereBelongsTo($empresa)
                ->where('activo', true)
                ->visiblesPara($usuario)
                ->find($requestedFolderId)
            : null;

        if ($selectedFolder === null) {
            $listing = [];
        }

        $search = is_string($listing['search'] ?? null) ? $listing['search'] : '';
        $archivados = (bool) ($listing['archivados'] ?? false);
        $perPage = min(max((int) ($listing['per_page'] ?? 15), 1), 100);
        $page = max((int) ($listing['page'] ?? 1), 1);

        $documentos = EmpleadoDocumentoCatalogo::query()
            ->select(['id', 'empresa_id', 'empleado_carpeta_id', 'nombre', 'updated_at', 'deleted_at'])
            ->whereBelongsTo($empresa)
            ->visiblesPara($empresa, $usuario)
            ->when(
                $selectedFolder !== null,
                fn (Builder $query): Builder => $query->where('empleado_carpeta_id', $selectedFolder->id),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->with([
                'carpeta:id,nombre',
                'modulos' => static function (Relation $relation) use ($modulosDisponiblesIds): void {
                    $relation->getQuery()
                        ->select(['modulos.id', 'modulos.clave', 'modulos.nombre'])
                        ->whereIn('modulos.id', $modulosDisponiblesIds);
                },
            ])
            ->when($archivados, fn (Builder $query): Builder => $query->onlyTrashed())
            ->when($search !== '', fn (Builder $query): Builder => $query->where('nombre', 'like', "%{$search}%"))
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(static fn (EmpleadoDocumentoCatalogo $documento): array => [
                'id' => $documento->id,
                'nombre' => $documento->nombre,
                'empleado_carpeta_id' => $documento->empleado_carpeta_id,
                'carpeta_nombre' => $documento->carpeta?->nombre,
                'modulos' => $documento->modulos->map(static fn (Modulo $modulo): array => [
                    'id' => $modulo->id,
                    'clave' => $modulo->clave,
                    'nombre' => $modulo->nombre,
                ])->values()->all(),
                'modulo_ids' => $documento->modulos->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all(),
                'updated_at' => $documento->updated_at?->toISOString(),
                'deleted_at' => $documento->deleted_at?->toISOString(),
            ]);
        $documentosData = $documentos->toArray();
        $documentosData['links'] = $this->paginationLinksWithoutUrls(
            $documentosData['links'],
            $documentos->currentPage(),
            $documentos->lastPage(),
        );
        unset($documentosData['first_page_url'], $documentosData['last_page_url']);
        $documentosData['next_page_url'] = null;
        $documentosData['prev_page_url'] = null;

        return Inertia::render('empleados/documentos-catalogo/index', [
            'carpetas' => $carpetas,
            'carpetaSeleccionada' => $selectedFolder === null ? null : [
                'id' => $selectedFolder->id,
                'nombre' => $selectedFolder->nombre,
            ],
            'documentos' => $documentosData,
            'modulosDisponibles' => $modulosDisponibles,
            'variables' => collect($variables->definitions())
                ->map(static fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'filters' => [
                'carpetaId' => $selectedFolder?->id,
                'search' => $search,
                'archivados' => $archivados,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function listDocuments(ListEmpleadoDocumentoCatalogoRequest $request): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        $listing = $request->listingData();

        EmpleadoCarpeta::query()
            ->whereBelongsTo($empresa)
            ->where('activo', true)
            ->visiblesPara($usuario)
            ->findOrFail($listing['carpeta_id']);

        $request->session()->flash(self::LISTING_SESSION_KEY, $listing);

        return to_route('empresas.empleados.documentos-catalogo.index');
    }

    public function show(
        Request $request,
        EmpleadoDocumentoCatalogo $empleadoDocumentoCatalogo,
        ModulosRelacionablesDocumentoCatalogo $modulosRelacionables,
    ): JsonResponse {
        $this->assertActiveCompany($empleadoDocumentoCatalogo);
        Gate::authorize('update', $empleadoDocumentoCatalogo);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        $empresa = $this->empresaContext->empresaRequerida();
        $modulosDisponiblesIds = $modulosRelacionables->query($usuario, $empresa)->pluck('modulos.id');
        $empleadoDocumentoCatalogo->load([
            'modulos' => static function (Relation $relation) use ($modulosDisponiblesIds): void {
                $relation->getQuery()
                    ->select(['modulos.id', 'modulos.clave', 'modulos.nombre'])
                    ->whereIn('modulos.id', $modulosDisponiblesIds);
            },
        ]);

        return response()->json([
            'documento' => [
                'id' => $empleadoDocumentoCatalogo->id,
                'nombre' => $empleadoDocumentoCatalogo->nombre,
                'empleado_carpeta_id' => $empleadoDocumentoCatalogo->empleado_carpeta_id,
                'contenido_html' => $empleadoDocumentoCatalogo->contenido_html,
                'modulo_ids' => $empleadoDocumentoCatalogo->modulos->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(
        StoreEmpleadoDocumentoCatalogoRequest $request,
        SaveEmpleadoDocumentoCatalogo $save,
    ): RedirectResponse {
        $empresa = $this->empresaContext->empresaRequerida();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $data = $request->documentoData();
        $carpeta = EmpleadoCarpeta::query()->whereBelongsTo($empresa)->findOrFail($data['empleado_carpeta_id']);
        Gate::authorize('createInFolder', [EmpleadoDocumentoCatalogo::class, $carpeta]);
        $save->create($empresa, $actor, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento creado correctamente.']);

        return to_route('empresas.empleados.documentos-catalogo.index');
    }

    public function update(
        UpdateEmpleadoDocumentoCatalogoRequest $request,
        EmpleadoDocumentoCatalogo $empleadoDocumentoCatalogo,
        SaveEmpleadoDocumentoCatalogo $save,
    ): RedirectResponse {
        $empresa = $this->empresaContext->empresaRequerida();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $this->assertActiveCompany($empleadoDocumentoCatalogo);
        $data = $request->documentoData();
        $carpeta = EmpleadoCarpeta::query()->whereBelongsTo($empresa)->findOrFail($data['empleado_carpeta_id']);
        Gate::authorize('moveToFolder', [EmpleadoDocumentoCatalogo::class, $carpeta]);
        $save->update($empresa, $empleadoDocumentoCatalogo, $actor, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento actualizado correctamente.']);

        return to_route('empresas.empleados.documentos-catalogo.index');
    }

    public function preview(
        PreviewEmpleadoDocumentoCatalogoRequest $request,
        RenderEmpleadoDocumentoPdf $renderPdf,
    ): HttpResponse {
        $empresa = $this->empresaContext->empresaRequerida();
        $data = $request->previewData();
        $carpeta = $data['empleado_carpeta_id'] === null
            ? null
            : EmpleadoCarpeta::query()
                ->whereBelongsTo($empresa)
                ->findOrFail($data['empleado_carpeta_id']);

        if ($data['documento_id'] !== null) {
            $documentoExistente = EmpleadoDocumentoCatalogo::query()
                ->whereBelongsTo($empresa)
                ->findOrFail($data['documento_id']);

            Gate::authorize('update', $documentoExistente);
            if ($carpeta instanceof EmpleadoCarpeta) {
                Gate::authorize('moveToFolder', [EmpleadoDocumentoCatalogo::class, $carpeta]);
            }
        } elseif ($carpeta instanceof EmpleadoCarpeta) {
            Gate::authorize('createInFolder', [EmpleadoDocumentoCatalogo::class, $carpeta]);
        } else {
            Gate::authorize('create', EmpleadoDocumentoCatalogo::class);
        }

        $documento = new EmpleadoDocumentoCatalogo([
            'empresa_id' => $empresa->id,
            'empleado_carpeta_id' => $carpeta?->id,
            'nombre' => $data['nombre'],
            'contenido_html' => $data['contenido_html'],
        ]);
        $pdf = $renderPdf->pdf($documento);
        $fileName = Str::slug($data['nombre']) ?: 'documento';

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$fileName.'-vista-previa.pdf"')
            ->header('Cache-Control', 'private, no-store');
    }

    public function destroy(EmpleadoDocumentoCatalogo $empleadoDocumentoCatalogo): RedirectResponse
    {
        $this->assertActiveCompany($empleadoDocumentoCatalogo);
        Gate::authorize('delete', $empleadoDocumentoCatalogo);
        $empleadoDocumentoCatalogo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento archivado correctamente.']);

        return to_route('empresas.empleados.documentos-catalogo.index');
    }

    public function restore(EmpleadoDocumentoCatalogo $empleadoDocumentoCatalogo): RedirectResponse
    {
        $this->assertActiveCompany($empleadoDocumentoCatalogo);
        Gate::authorize('restore', $empleadoDocumentoCatalogo);
        $empleadoDocumentoCatalogo->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento restaurado correctamente.']);

        return to_route('empresas.empleados.documentos-catalogo.index');
    }

    public function download(
        EmpleadoDocumentoCatalogo $empleadoDocumentoCatalogo,
        RenderEmpleadoDocumentoPdf $renderPdf,
    ): StreamedResponse {
        $this->assertActiveCompany($empleadoDocumentoCatalogo);
        Gate::authorize('download', $empleadoDocumentoCatalogo);
        $pdf = $renderPdf->pdf($empleadoDocumentoCatalogo);
        $fileName = Str::slug($empleadoDocumentoCatalogo->nombre) ?: 'documento';

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf;
            },
            $fileName.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function assertActiveCompany(EmpleadoDocumentoCatalogo $documento): void
    {
        abort_unless($documento->empresa_id === $this->empresaContext->empresaRequerida()->id, 404);
    }

    /**
     * @param  array<int, array{url: string|null, label: string, active: bool}>  $links
     * @return array<int, array{url: null, label: string, active: bool, page: int|null}>
     */
    private function paginationLinksWithoutUrls(array $links, int $currentPage, int $lastPage): array
    {
        return array_map(static function (array $link) use ($currentPage, $lastPage): array {
            $label = html_entity_decode($link['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $normalizedLabel = trim($label);
            $page = null;

            if (preg_match('/^\d+$/D', $normalizedLabel) === 1) {
                $page = (int) $normalizedLabel;
            } elseif (str_contains(strtolower($normalizedLabel), 'previous') || str_contains(strtolower($normalizedLabel), 'anterior')) {
                $page = $currentPage - 1;
            } elseif (str_contains(strtolower($normalizedLabel), 'next') || str_contains(strtolower($normalizedLabel), 'siguiente')) {
                $page = $currentPage + 1;
            }

            if ($page !== null && ($page < 1 || $page > $lastPage)) {
                $page = null;
            }

            return [
                'url' => null,
                'label' => $link['label'],
                'active' => $link['active'],
                'page' => $page,
            ];
        }, $links);
    }
}
