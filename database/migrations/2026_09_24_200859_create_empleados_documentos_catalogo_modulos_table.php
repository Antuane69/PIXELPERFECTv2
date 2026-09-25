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
        Schema::create('empleados_documentos_catalogo_modulos', function (Blueprint $table): void {
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('empleado_documento_catalogo_id');
            $table->foreignId('modulo_id')->constrained('modulos')->restrictOnDelete();

            $table->primary(
                ['empresa_id', 'empleado_documento_catalogo_id', 'modulo_id'],
                'empleados_documentos_catalogo_modulos_primary',
            );
            $table->index(
                ['modulo_id', 'empresa_id'],
                'empleados_documentos_catalogo_modulos_modulo_empresa_index',
            );
            $table->foreign(
                ['empresa_id', 'empleado_documento_catalogo_id'],
                'empleados_documentos_catalogo_modulos_documento_foreign',
            )
                ->references(['empresa_id', 'id'])
                ->on('empleados_documentos_catalogo')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados_documentos_catalogo_modulos');
    }
};
