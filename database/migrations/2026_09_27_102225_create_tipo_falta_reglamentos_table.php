<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_faltas_reglamento', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'nombre'], 'tipos_faltas_reglamento_empresa_nombre_unique');
            $table->unique(['empresa_id', 'id'], 'tipos_faltas_reglamento_empresa_id_unique');
            $table->index(['empresa_id', 'activo', 'id'], 'tipos_faltas_reglamento_empresa_activo_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_faltas_reglamento');
    }
};
