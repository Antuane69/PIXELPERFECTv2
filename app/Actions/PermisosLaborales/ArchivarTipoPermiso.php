<?php

namespace App\Actions\PermisosLaborales;

use App\Models\PermisosLaborales\TipoPermiso;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchivarTipoPermiso
{
    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function handle(TipoPermiso $tipoPermiso): void
    {
        $empresaId = $this->empresaContext->empresaRequerida()->id;

        DB::transaction(function () use ($tipoPermiso, $empresaId): void {
            $tipoPermiso = TipoPermiso::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($tipoPermiso->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($tipoPermiso->permisosLaborales()->exists()) {
                throw ValidationException::withMessages([
                    'tipoPermiso' => 'No se puede archivar un tipo asociado a solicitudes de permisos laborales.',
                ]);
            }

            $tipoPermiso->delete();
        });
    }
}
