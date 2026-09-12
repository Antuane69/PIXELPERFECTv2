<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Empresa;
use App\Services\Empleados\EmpleadoPrivatePath;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowEmpleadoAvatarController extends Controller
{
    public function __construct(private EmpleadoPrivatePath $privatePath) {}

    /**
     * Stream a private employee avatar after authorization.
     */
    public function __invoke(Empresa $empresa, Empleado $empleado): StreamedResponse
    {
        abort_unless($empleado->empresa_id === $empresa->id, 404);
        Gate::authorize('view', $empleado);

        $path = $empleado->avatar;

        abort_unless(is_string($path) && $path !== '', 404);
        abort_unless($this->privatePath->belongsToEmployee($path, $empresa, $empleado), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        $mimeType = $disk->mimeType($path);
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        abort_if($extension === null, 404);

        return $disk->response(
            $path,
            "avatar-{$empleado->id}.{$extension}",
            [
                'Cache-Control' => 'private, max-age=300',
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }
}
