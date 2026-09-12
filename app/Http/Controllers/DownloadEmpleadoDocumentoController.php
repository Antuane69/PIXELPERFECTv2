<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\Empresa;
use App\Services\Empleados\EmpleadoPrivatePath;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadEmpleadoDocumentoController extends Controller
{
    public function __construct(private EmpleadoPrivatePath $privatePath) {}

    /**
     * Download an employee document after scoped binding and authorization.
     */
    public function __invoke(Empresa $empresa, Empleado $empleado, EmpleadoDocumento $documento): StreamedResponse
    {
        abort_unless($documento->empresa_id === $empresa->id, 404);
        Gate::authorize('view', $empleado);
        abort_unless($this->privatePath->belongsToEmployee($documento->ruta, $empresa, $empleado), 404);

        abort_unless(Storage::disk($documento->disco)->exists($documento->ruta), 404);

        return Storage::disk($documento->disco)->download(
            $documento->ruta,
            $documento->nombre_original,
            ['Content-Type' => $documento->mime_type],
        );
    }
}
