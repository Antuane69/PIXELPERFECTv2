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
        Schema::create('incapacidades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('empleado_id');
            $table->foreignId('solicitante_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelto_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('solicitante_nombre', 120);
            $table->string('solicitante_correo', 180);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 20)->default('PENDIENTE');
            $table->text('motivo');
            $table->text('comentarios_rechazo')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->string('nombre_original', 255)->nullable();
            $table->string('mime_type', 127)->nullable();
            $table->string('extension', 12)->nullable();
            $table->binary('archivo')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'id'], 'incapacidades_empresa_id_id_unique');
            $table->index(['empresa_id', 'estado', 'fecha_inicio'], 'incapacidades_empresa_estado_inicio_index');
            $table->index(['empresa_id', 'empleado_id', 'fecha_inicio'], 'incapacidades_empresa_empleado_inicio_index');
            $table->index(['empresa_id', 'fecha_fin', 'estado'], 'incapacidades_empresa_fin_estado_index');
            $table->index(['solicitante_user_id', 'created_at'], 'incapacidades_solicitante_created_index');
            $table->foreign(['empresa_id', 'empleado_id'], 'incapacidades_empresa_empleado_foreign')
                ->references(['empresa_id', 'id'])
                ->on('empleados')
                ->restrictOnDelete();
        });

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE incapacidades MODIFY archivo LONGBLOB NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incapacidades');
    }
};
