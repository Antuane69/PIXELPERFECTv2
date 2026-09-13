<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE empresas ADD logo LONGBLOB NULL AFTER codigo_pais_contacto');
        } else {
            Schema::table('empresas', function (Blueprint $table): void {
                $table->binary('logo')->nullable()->after('codigo_pais_contacto');
            });
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->string('logo_mime_type', 100)->nullable()->after('logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            $table->dropColumn('logo_mime_type');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE empresas DROP COLUMN logo');
        } else {
            Schema::table('empresas', function (Blueprint $table): void {
                $table->dropColumn('logo');
            });
        }
    }
};
