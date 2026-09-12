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
        Schema::create('empresa_modulo', function (Blueprint $table) {
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('modulo_id')->constrained('modulos')->restrictOnDelete();
            $table->boolean('habilitado')->default(true);
            $table->timestamps();

            $table->primary(['empresa_id', 'modulo_id']);
            $table->index(['empresa_id', 'habilitado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_modulo');
    }
};
