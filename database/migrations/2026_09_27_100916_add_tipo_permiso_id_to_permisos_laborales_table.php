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
        Schema::table('permisos_laborales', function (Blueprint $table): void {
            $table->unsignedBigInteger('tipo_permiso_id')->nullable()->after('empresa_id');
            $table->index(['empresa_id', 'tipo_permiso_id'], 'permisos_laborales_empresa_tipo_index');
            $table->foreign(['empresa_id', 'tipo_permiso_id'], 'permisos_laborales_empresa_tipo_foreign')
                ->references(['empresa_id', 'id'])
                ->on('tipos_permisos')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permisos_laborales', function (Blueprint $table): void {
            $table->dropForeign('permisos_laborales_empresa_tipo_foreign');
            $table->dropIndex('permisos_laborales_empresa_tipo_index');
            $table->dropColumn('tipo_permiso_id');
        });
    }
};
