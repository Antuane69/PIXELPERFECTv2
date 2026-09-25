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
        Schema::create('empleados_carpetas_usuarios', function (Blueprint $table): void {
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('empleado_carpeta_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->primary(
                ['empresa_id', 'empleado_carpeta_id', 'user_id'],
                'empleados_carpetas_usuarios_primary',
            );
            $table->index(['empresa_id', 'user_id'], 'empleados_carpetas_usuarios_empresa_user_index');
            $table->index(['user_id', 'empleado_carpeta_id'], 'empleados_carpetas_usuarios_user_carpeta_index');
            $table->foreign(['empresa_id', 'empleado_carpeta_id'], 'empleados_carpetas_usuarios_carpeta_foreign')
                ->references(['empresa_id', 'id'])
                ->on('empleados_carpetas')
                ->cascadeOnDelete();
            $table->foreign(['empresa_id', 'user_id'], 'empleados_carpetas_usuarios_membresia_foreign')
                ->references(['empresa_id', 'user_id'])
                ->on('membresias_empresa')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados_carpetas_usuarios');
    }
};
