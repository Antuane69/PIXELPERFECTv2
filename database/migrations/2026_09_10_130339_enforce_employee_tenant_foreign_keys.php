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
        Schema::table('puestos', function (Blueprint $table): void {
            $table->unique(['empresa_id', 'id'], 'puestos_empresa_id_id_unique');
        });

        Schema::table('empleados', function (Blueprint $table): void {
            $table->dropForeign(['puesto_id']);
            $table->unique(['empresa_id', 'id'], 'empleados_empresa_id_id_unique');
            $table->foreign(['empresa_id', 'puesto_id'], 'empleados_empresa_puesto_foreign')
                ->references(['empresa_id', 'id'])
                ->on('puestos')
                ->restrictOnDelete();
        });

        Schema::table('empleado_documentos', function (Blueprint $table): void {
            $table->dropForeign(['empleado_id']);
            $table->foreign(
                ['empresa_id', 'empleado_id'],
                'empleado_documentos_empresa_empleado_foreign',
            )
                ->references(['empresa_id', 'id'])
                ->on('empleados')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empleado_documentos', function (Blueprint $table): void {
            $table->dropForeign('empleado_documentos_empresa_empleado_foreign');
            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
        });

        Schema::table('empleados', function (Blueprint $table): void {
            $table->dropForeign('empleados_empresa_puesto_foreign');
            $table->foreign('puesto_id')->references('id')->on('puestos')->restrictOnDelete();
            $table->dropUnique('empleados_empresa_id_id_unique');
        });

        Schema::table('puestos', function (Blueprint $table): void {
            $table->dropUnique('puestos_empresa_id_id_unique');
        });
    }
};
