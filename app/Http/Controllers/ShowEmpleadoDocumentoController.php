<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowEmpleadoDocumentoController extends Controller
{
    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __invoke(Empleado $empleado, EmpleadoDocumento $documento): StreamedResponse
    {
        Gate::authorize('view', $empleado);

        abort_unless(in_array($documento->mime_type, self::IMAGE_MIME_TYPES, true), 404);

        $disk = Storage::disk($documento->disco);

        abort_unless($disk->exists($documento->ruta), 404);

        return $disk->response(
            $documento->ruta,
            $documento->nombre_original,
            [
                'Cache-Control' => 'private, max-age=300',
                'Content-Type' => $documento->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }
}
