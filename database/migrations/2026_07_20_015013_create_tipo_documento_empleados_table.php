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
        Schema::create('tipo_documento_empleados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('es_renovable')->default(false);
            $table->unsignedSmallInteger('frecuencia_cantidad')->nullable();
            $table->string('frecuencia_tipo', 10)->nullable();
            $table->json('documentos_aceptados');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_documento_empleados');
    }
};
