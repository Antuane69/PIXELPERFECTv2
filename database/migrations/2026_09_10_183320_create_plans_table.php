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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->decimal('precio_mensual', 12, 2);
            $table->char('color', 7)->default('#7C3AED');
            $table->string('icono', 50)->default('CrownOutlined');
            $table->text('modulos_incluidos')->nullable();
            $table->unsignedSmallInteger('limite_usuarios')->nullable();
            $table->unsignedSmallInteger('periodo_gracia_dias')->default(15);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
