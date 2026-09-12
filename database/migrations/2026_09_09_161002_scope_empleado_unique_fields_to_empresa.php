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
        if (DB::table('empleados')->whereNull('empresa_id')->exists()) {
            throw new RuntimeException('Existen empleados sin empresa; no puede completarse el aislamiento empresarial.');
        }

        Schema::table('empleados', function (Blueprint $table): void {
            $table->dropUnique(['nombre_usuario']);
            $table->dropUnique(['correo']);
            $table->dropUnique(['curp']);
            $table->dropUnique(['rfc']);
            $table->dropUnique(['nss']);
            $table->unique(['empresa_id', 'nombre_usuario']);
            $table->unique(['empresa_id', 'correo']);
            $table->unique(['empresa_id', 'curp']);
            $table->unique(['empresa_id', 'rfc']);
            $table->unique(['empresa_id', 'nss']);
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['nombre_usuario', 'correo', 'curp', 'rfc', 'nss'] as $column) {
            if (DB::table('empleados')
                ->select($column)
                ->whereNotNull($column)
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->exists()) {
                throw new RuntimeException("No puede restaurarse la unicidad global: existen valores repetidos en {$column} entre empresas.");
            }
        }

        Schema::table('empleados', function (Blueprint $table): void {
            $table->unsignedBigInteger('empresa_id')->nullable()->change();
            $table->dropUnique(['empresa_id', 'nombre_usuario']);
            $table->dropUnique(['empresa_id', 'correo']);
            $table->dropUnique(['empresa_id', 'curp']);
            $table->dropUnique(['empresa_id', 'rfc']);
            $table->dropUnique(['empresa_id', 'nss']);
            $table->unique('nombre_usuario');
            $table->unique('correo');
            $table->unique('curp');
            $table->unique('rfc');
            $table->unique('nss');
        });
    }
};
