<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadEmpleadoDocumentoController extends Controller
{
    /**
     * Download an employee document after scoped binding and authorization.
     */
    public function __invoke(Empleado $empleado, EmpleadoDocumento $documento): StreamedResponse
    {
        Gate::authorize('view', $empleado);

        abort_unless(Storage::disk($documento->disco)->exists($documento->ruta), 404);

        return Storage::disk($documento->disco)->download(
            $documento->ruta,
            $documento->nombre_original,
            ['Content-Type' => $documento->mime_type],
        );
    }
}
