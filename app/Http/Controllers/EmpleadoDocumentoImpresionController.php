<?php

namespace App\Http\Controllers;

use App\Actions\Empleados\DownloadEmpleadoDocumentos;
use App\Http\Requests\Empleados\PrintEmpleadoDocumentosRequest;
use App\Models\Empleado;
use App\Models\EmpleadoCarpeta;
use App\Models\EmpleadoDocumentoCatalogo;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmpleadoDocumentoImpresionController extends Controller
{
    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function seleccionar(Request $request, Empleado $empleado): Response
    {
        $empresa = $this->empresaContext->empresaRequerida();
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        $this->assertActiveCompany($empleado);
        Gate::authorize('view', $empleado);
        Gate::authorize('viewAny', EmpleadoDocumentoCatalogo::class);

        $requestedFolderId = $request->integer('carpeta_id');
        $selectedFolder = $requestedFolderId > 0
            ? EmpleadoCarpeta::query()
                ->whereBelongsTo($empresa)
                ->where('activo', true)
                ->visiblesPara($usuario)
                ->find($requestedFolderId)
            : null;

        abort_if($requestedFolderId > 0 && $selectedFolder === null, 404);

        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $carpetas = EmpleadoCarpeta::query()
            ->whereBelongsTo($empresa)
            ->where('activo', true)
            ->visiblesPara($usuario)
            ->whereHas('documentosCatalogo', fn (Builder $query): Builder => $query
                ->where('empresa_id', $empresa->id))
            ->withCount(['documentosCatalogo' => fn (Builder $query): Builder => $query
                ->where('empresa_id', $empresa->id)])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'empresa_id', 'nombre'])
            ->map(static fn (EmpleadoCarpeta $carpeta): array => [
                'id' => $carpeta->id,
                'nombre' => $carpeta->nombre,
                'documentos_count' => $carpeta->documentos_catalogo_count,
            ])
            ->values()
            ->all();

        $documentos = EmpleadoDocumentoCatalogo::query()
            ->select(['id', 'empresa_id', 'empleado_carpeta_id', 'nombre'])
            ->whereBelongsTo($empresa)
            ->visiblesPara($empresa, $usuario)
            ->when(
                $selectedFolder !== null,
                fn (Builder $query): Builder => $query->where('empleado_carpeta_id', $selectedFolder->id),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (EmpleadoDocumentoCatalogo $documento): array => [
                'id' => $documento->id,
                'nombre' => $documento->nombre,
            ]);

        return Inertia::render('empleados/documentos-catalogo/imprimir', [
            'empleado' => ['id' => $empleado->id, 'nombre' => $empleado->nombre],
            'carpetas' => $carpetas,
            'carpetaSeleccionada' => $selectedFolder === null ? null : [
                'id' => $selectedFolder->id,
                'nombre' => $selectedFolder->nombre,
            ],
            'documentos' => $documentos,
        ]);
    }

    public function descargar(
        PrintEmpleadoDocumentosRequest $request,
        Empleado $empleado,
        DownloadEmpleadoDocumentos $download,
    ): HttpResponse {
        $empresa = $this->empresaContext->empresaRequerida();
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        $this->assertActiveCompany($empleado);
        Gate::authorize('view', $empleado);

        $ids = array_map('intval', $request->validated('documento_ids'));
        $documentos = EmpleadoDocumentoCatalogo::query()
            ->visiblesPara($empresa, $usuario)
            ->whereIn('id', $ids)
            ->with('carpeta:id,nombre')
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        abort_unless($documentos->count() === count($ids), 404);

        return $download->handle($empleado, $documentos);
    }

    private function assertActiveCompany(Empleado $empleado): void
    {
        abort_unless($empleado->empresa_id === $this->empresaContext->empresaRequerida()->id, 404);
    }
}
