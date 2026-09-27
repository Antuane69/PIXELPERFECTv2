<?php

namespace App\Http\Controllers\FaltasReglamento;

use App\Http\Controllers\Controller;
use App\Models\FaltasReglamento\FaltaReglamento;
use App\Models\FaltasReglamento\FaltaReglamentoEvidencia;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FaltaReglamentoEvidenciaController extends Controller
{
    public function download(
        FaltaReglamento $faltaReglamento,
        FaltaReglamentoEvidencia $evidencia,
    ): StreamedResponse {
        Gate::authorize('view', $faltaReglamento);

        abort_unless(
            $evidencia->empresa_id === $faltaReglamento->empresa_id
                && $evidencia->falta_reglamento_id === $faltaReglamento->id,
            404,
        );

        $contents = $evidencia->archivo;

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $evidencia->nombre,
            [
                'Content-Type' => $evidencia->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
