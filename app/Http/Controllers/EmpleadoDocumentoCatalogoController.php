<?php

namespace App\Http\Controllers;

use App\Actions\Empleados\RenderEmpleadoDocumentoPdf;
use App\Actions\Empleados\SaveEmpleadoDocumentoCatalogo;
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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmpleadoDocumentoCatalogoController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = ['carpeta_id', 'search', 'archivados', 'per_page', 'page'];

    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function index(
        Request $request,
        EmpleadoDocumentoVariables $variables,
        ModulosRelacionablesDocumentoCatalogo $modulosRelacionables,
    ): Response {
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

        $requestedFolderId = $request->integer('carpeta_id');
        $selectedFolder = $requestedFolderId > 0
            ? EmpleadoCarpeta::query()
                ->whereBelongsTo($empresa)
                ->visiblesPara($usuario)
                ->find($requestedFolderId)
            : null;

        abort_if($requestedFolderId > 0 && $selectedFolder === null, 404);

        $search = $request->string('search')->squish()->toString();
        $archivados = $request->boolean('archivados');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

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
            ->paginate($perPage)
            ->withQueryString()
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

        return Inertia::render('empleados/documentos-catalogo/index', [
            'carpetas' => $carpetas,
            'carpetaSeleccionada' => $selectedFolder === null ? null : [
                'id' => $selectedFolder->id,
                'nombre' => $selectedFolder->nombre,
            ],
            'documentos' => $documentos,
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

        return $this->redirectToResourceIndex($request, 'empresas.empleados.documentos-catalogo.index', self::INDEX_QUERY_PARAMETERS);
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

        return $this->redirectToResourceIndex($request, 'empresas.empleados.documentos-catalogo.index', self::INDEX_QUERY_PARAMETERS);
    }

    public function destroy(Request $request, EmpleadoDocumentoCatalogo $empleadoDocumentoCatalogo): RedirectResponse
    {
        $this->assertActiveCompany($empleadoDocumentoCatalogo);
        Gate::authorize('delete', $empleadoDocumentoCatalogo);
        $empleadoDocumentoCatalogo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento archivado correctamente.']);

        return $this->redirectToResourceIndex($request, 'empresas.empleados.documentos-catalogo.index', self::INDEX_QUERY_PARAMETERS);
    }

    public function restore(Request $request, EmpleadoDocumentoCatalogo $empleadoDocumentoCatalogo): RedirectResponse
    {
        $this->assertActiveCompany($empleadoDocumentoCatalogo);
        Gate::authorize('restore', $empleadoDocumentoCatalogo);
        $empleadoDocumentoCatalogo->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento restaurado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'empresas.empleados.documentos-catalogo.index',
            self::INDEX_QUERY_PARAMETERS,
            ['archivados' => true],
        );
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
}
