<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowEmpleadoAvatarController extends Controller
{
    /**
     * Stream a private employee avatar after authorization.
     */
    public function __invoke(Empleado $empleado): StreamedResponse
    {
        Gate::authorize('view', $empleado);

        $path = $empleado->avatar;

        abort_unless(is_string($path) && $path !== '', 404);

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
