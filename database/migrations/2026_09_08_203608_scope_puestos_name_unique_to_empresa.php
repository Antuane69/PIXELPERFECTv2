<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('puestos')->whereNull('empresa_id')->exists()) {
            throw new RuntimeException('Existen puestos sin empresa; no puede completarse el aislamiento empresarial.');
        }

        Schema::table('puestos', function (Blueprint $table) {
            $table->dropUnique(['nombre']);
            $table->unique(['empresa_id', 'nombre']);
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $duplicateNameExists = DB::table('puestos')
            ->select('nombre')
            ->groupBy('nombre')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateNameExists) {
            throw new RuntimeException('No puede restaurarse la unicidad global: existen nombres de puesto repetidos entre empresas.');
        }

        Schema::table('puestos', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->change();
            $table->dropUnique(['empresa_id', 'nombre']);
            $table->unique('nombre');
        });
    }
};
