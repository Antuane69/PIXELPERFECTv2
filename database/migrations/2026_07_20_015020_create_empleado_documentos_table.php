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
        Schema::create('empleado_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tipo_documento_empleado_id')
                ->constrained('tipo_documento_empleados')
                ->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('disco', 50)->default('local');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('tamano');
            $table->date('vence_el')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['empleado_id', 'tipo_documento_empleado_id'],
                'empleado_documentos_empleado_tipo_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleado_documentos');
    }
};
