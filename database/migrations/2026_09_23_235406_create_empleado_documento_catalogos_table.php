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
        Schema::create('empleados_documentos_catalogo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->unsignedBigInteger('empleado_carpeta_id');
            $table->string('nombre', 180);
            $table->longText('contenido_html');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'id'], 'empleados_documentos_catalogo_empresa_id_unique');
            $table->index(
                ['empresa_id', 'empleado_carpeta_id', 'deleted_at', 'nombre'],
                'empleados_documentos_catalogo_carpeta_nombre_index',
            );
            $table->foreign(['empresa_id', 'empleado_carpeta_id'], 'empleados_documentos_catalogo_carpeta_foreign')
                ->references(['empresa_id', 'id'])
                ->on('empleados_carpetas')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados_documentos_catalogo');
    }
};
