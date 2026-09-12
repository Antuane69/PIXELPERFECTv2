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

        if ($empresaId === null && DB::table('puestos')->exists()) {
            throw new RuntimeException('No existe la empresa inicial Pixel Perfect para asignar los puestos existentes.');
        }

        if ($empresaId !== null) {
            DB::table('puestos')
                ->whereNull('empresa_id')
                ->update(['empresa_id' => $empresaId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('puestos')->update(['empresa_id' => null]);
    }
};
