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
        Schema::table('empleados', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('empresa_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->unique(['empresa_id', 'user_id'], 'empleados_empresa_user_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropUnique('empleados_empresa_user_id_unique');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
