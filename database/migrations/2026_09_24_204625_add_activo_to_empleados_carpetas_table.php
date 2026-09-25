<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('empleados_carpetas', function (Blueprint $table): void {
            $table->boolean('activo')->default(true)->after('nombre');
            $table->index(
                ['empresa_id', 'activo', 'deleted_at'],
                'empleados_carpetas_empresa_activo_deleted_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empleados_carpetas', function (Blueprint $table): void {
            $table->dropIndex('empleados_carpetas_empresa_activo_deleted_index');
            $table->dropColumn('activo');
        });
    }
};
