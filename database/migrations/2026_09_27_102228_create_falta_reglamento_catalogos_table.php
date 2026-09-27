<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_faltas_reglamento', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tipo_falta_reglamento_id');
            $table->string('nombre', 180);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'tipo_falta_reglamento_id', 'nombre'], 'catalogo_faltas_reglamento_empresa_tipo_nombre_unique');
            $table->unique(['empresa_id', 'id'], 'catalogo_faltas_reglamento_empresa_id_unique');
            $table->index(['empresa_id', 'tipo_falta_reglamento_id', 'activo', 'id'], 'catalogo_faltas_reglamento_empresa_tipo_activo_index');
            $table->foreign(['empresa_id', 'tipo_falta_reglamento_id'], 'catalogo_faltas_reglamento_empresa_tipo_foreign')
                ->references(['empresa_id', 'id'])->on('tipos_faltas_reglamento')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_faltas_reglamento');
    }
};
