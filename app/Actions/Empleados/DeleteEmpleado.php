<?php

namespace App\Actions\Empleados;

use App\Models\Empleado;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Support\Facades\DB;

class DeleteEmpleado
{
    public function __construct(private EmpresaContext $empresaContext) {}

    public function handle(Empleado $empleado): void
    {
        DB::transaction(function () use ($empleado): void {
            Empleado::query()
                ->whereKey($empleado->getKey())
                ->where('empresa_id', $this->empresaContext->empresaRequerida()->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();
        });
    }
}
