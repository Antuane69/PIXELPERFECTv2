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
        Schema::create('empleados_carpetas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('creado_por_id')->constrained('users')->restrictOnDelete();
            $table->string('nombre');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'id'], 'empleados_carpetas_empresa_id_id_unique');
            $table->index(
                ['empresa_id', 'creado_por_id', 'deleted_at'],
                'empleados_carpetas_empresa_creador_deleted_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados_carpetas');
    }
};
