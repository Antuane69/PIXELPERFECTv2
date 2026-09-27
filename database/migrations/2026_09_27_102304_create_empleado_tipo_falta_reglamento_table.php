<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleado_tipo_falta_reglamento', function (Blueprint $table): void {
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('tipo_falta_reglamento_id');
            $table->unsignedInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique(['empresa_id', 'empleado_id', 'tipo_falta_reglamento_id'], 'empleado_tipo_falta_reglamento_empresa_empleado_tipo_unique');
            $table->foreign(['empresa_id', 'empleado_id'], 'empleado_tipo_falta_reglamento_empresa_empleado_foreign')
                ->references(['empresa_id', 'id'])->on('empleados')->cascadeOnDelete();
            $table->foreign(['empresa_id', 'tipo_falta_reglamento_id'], 'empleado_tipo_falta_reglamento_empresa_tipo_foreign')
                ->references(['empresa_id', 'id'])->on('tipos_faltas_reglamento')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_tipo_falta_reglamento');
    }
};
