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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_empresarial_id')
                ->constrained('grupos_empresariales')
                ->restrictOnDelete();
            $table->string('nombre_legal', 180);
            $table->string('nombre_comercial', 180)->nullable();
            $table->string('slug', 180)->unique();
            $table->string('rfc', 13)->nullable()->unique();
            $table->string('correo_contacto', 180)->nullable();
            $table->string('telefono_contacto', 30)->nullable();
            $table->string('zona_horaria', 64)->default('America/Mexico_City');
            $table->char('moneda', 3)->default('MXN');
            $table->string('estado', 20)->default('PROSPECTO')->index();
            $table->timestamp('demo_ends_at')->nullable()->index();
            $table->timestamp('activada_at')->nullable();
            $table->timestamp('vence_at')->nullable()->index();
            $table->timestamp('desactivada_at')->nullable();
            $table->timestamp('retencion_hasta')->nullable()->index();
            $table->timestamps();

            $table->index(['grupo_empresarial_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
