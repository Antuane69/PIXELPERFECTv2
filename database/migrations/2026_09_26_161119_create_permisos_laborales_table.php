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
        Schema::create('permisos_laborales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('empleado_id');
            $table->foreignId('solicitante_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelto_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('solicitante_nombre', 255);
            $table->string('solicitante_correo', 255);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 20)->default('PENDIENTE');
            $table->text('comentarios')->nullable();
            $table->text('comentarios_rechazo')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'id'], 'permisos_laborales_empresa_id_id_unique');
            $table->index(['empresa_id', 'estado', 'fecha_inicio'], 'permisos_laborales_empresa_estado_inicio_index');
            $table->index(['empresa_id', 'empleado_id', 'fecha_inicio'], 'permisos_laborales_empresa_empleado_inicio_index');
            $table->index(['empresa_id', 'fecha_fin', 'estado'], 'permisos_laborales_empresa_fin_estado_index');
            $table->index(['solicitante_user_id', 'created_at'], 'permisos_laborales_solicitante_created_index');
            $table->foreign(['empresa_id', 'empleado_id'], 'permisos_laborales_empresa_empleado_foreign')
                ->references(['empresa_id', 'id'])
                ->on('empleados')
                ->restrictOnDelete();
        });

        Schema::create('permiso_laboral_empleado_cubre', function (Blueprint $table): void {
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('permiso_laboral_id');
            $table->unsignedBigInteger('empleado_id');
            $table->timestamps();

            $table->unique(
                ['empresa_id', 'permiso_laboral_id', 'empleado_id'],
                'permiso_laboral_cubre_empresa_permiso_empleado_unique',
            );
            $table->index(['empresa_id', 'empleado_id'], 'permiso_laboral_cubre_empresa_empleado_index');
            $table->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign(['empresa_id', 'permiso_laboral_id'], 'permiso_laboral_cubre_empresa_permiso_foreign')
                ->references(['empresa_id', 'id'])
                ->on('permisos_laborales')
                ->cascadeOnDelete();
            $table->foreign(['empresa_id', 'empleado_id'], 'permiso_laboral_cubre_empresa_empleado_foreign')
                ->references(['empresa_id', 'id'])
                ->on('empleados')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permiso_laboral_empleado_cubre');
        Schema::dropIfExists('permisos_laborales');
    }
};
