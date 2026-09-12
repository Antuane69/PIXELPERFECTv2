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
        $empresaId = DB::table('empresas')
            ->where('slug', 'pixel-perfect')
            ->value('id');

        if ($empresaId === null && DB::table('empleados')->exists()) {
            throw new RuntimeException('No existe la empresa inicial Pixel Perfect para asignar los empleados existentes.');
        }

        if ($empresaId !== null) {
            DB::table('empleados')
                ->whereNull('empresa_id')
                ->update(['empresa_id' => $empresaId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('empleados')->update(['empresa_id' => null]);
    }
};
