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
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('alcance', 20)->default('EMPRESA')->after('guard_name')->index();
            $table->foreignId('modulo_id')
                ->nullable()
                ->after('alcance')
                ->constrained('modulos')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modulo_id');
            $table->dropColumn('alcance');
        });
    }
};
