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
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('nombre_usuario', 60)->unique();
            $table->string('correo', 120)->unique();
            $table->char('curp', 18)->unique();
            $table->string('rfc', 13)->unique();
            $table->char('nss', 11)->nullable()->unique();
            $table->string('num_clinica_ss', 120)->nullable();
            $table->foreignId('puesto_id')->constrained()->restrictOnDelete();
            $table->string('estado_civil', 20);
            $table->string('sexo', 20);
            $table->string('domicilio', 250);
            $table->string('telefono', 10)->index();
            $table->string('avatar')->nullable();
            $table->decimal('salario_dia', 12, 2)->nullable();
            $table->decimal('salario_quincena', 12, 2)->nullable();
            $table->decimal('salario_vacaciones_finiquito', 12, 2)->nullable();
            $table->decimal('aguinaldo', 12, 2)->nullable();
            $table->decimal('prima_vacacional', 12, 2)->nullable();
            $table->unsignedSmallInteger('dias_vacaciones')->nullable();
            $table->unsignedSmallInteger('dias_liquidacion')->nullable();
            $table->json('dias_descanso')->nullable();
            $table->date('fecha_ingreso')->index();
            $table->date('fecha_nacimiento');
            $table->date('fecha_contrato_siguiente')->nullable();
            $table->date('fecha_contrato_indefinido')->nullable();
            $table->date('fecha_ultimo_aviso')->nullable();
            $table->date('fecha_evaluacion')->nullable();
            $table->date('fecha_inicio_contrato')->nullable();
            $table->date('fecha_termino_contrato')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
