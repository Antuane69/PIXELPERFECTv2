<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('empleado_documentos')
            ->whereNull('empresa_id')
            ->update([
                'empresa_id' => DB::raw('(SELECT empleados.empresa_id FROM empleados WHERE empleados.id = empleado_documentos.empleado_id)'),
            ]);

        if (DB::table('empleado_documentos')->whereNull('empresa_id')->exists()) {
            throw new RuntimeException('Existen documentos sin empresa o sin empleado válido; no puede completarse el aislamiento empresarial.');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('empleado_documentos')->update(['empresa_id' => null]);
    }
};
