<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faltas_reglamento', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('falta_reglamento_catalogo_id');
            $table->foreignId('solicitante_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelto_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('solicitante_nombre', 255);
            $table->string('solicitante_correo', 255);
            $table->date('fecha_ocurrencia');
            $table->string('estado', 20)->default('PENDIENTE');
            $table->text('comentarios')->nullable();
            $table->text('comentarios_rechazo')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'id'], 'faltas_reglamento_empresa_id_unique');
            $table->index(['empresa_id', 'estado', 'fecha_ocurrencia'], 'faltas_reglamento_empresa_estado_fecha_index');
            $table->index(['empresa_id', 'empleado_id', 'fecha_ocurrencia'], 'faltas_reglamento_empresa_empleado_fecha_index');
            $table->index(['empresa_id', 'falta_reglamento_catalogo_id'], 'faltas_reglamento_empresa_catalogo_index');
            $table->index(['solicitante_user_id', 'created_at'], 'faltas_reglamento_solicitante_created_index');
            $table->foreign(['empresa_id', 'empleado_id'], 'faltas_reglamento_empresa_empleado_foreign')
                ->references(['empresa_id', 'id'])->on('empleados')->restrictOnDelete();
            $table->foreign(['empresa_id', 'falta_reglamento_catalogo_id'], 'faltas_reglamento_empresa_catalogo_foreign')
                ->references(['empresa_id', 'id'])->on('catalogo_faltas_reglamento')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faltas_reglamento');
    }
};
