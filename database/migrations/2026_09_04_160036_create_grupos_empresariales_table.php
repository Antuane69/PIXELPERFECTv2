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
        Schema::create('grupos_empresariales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 160);
            $table->string('slug', 180)->unique();
            $table->string('tipo', 20)->default('INDIVIDUAL')->index();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos_empresariales');
    }
};
