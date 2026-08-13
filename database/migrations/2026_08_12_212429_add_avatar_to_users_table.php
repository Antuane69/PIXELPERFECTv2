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
            DB::statement('ALTER TABLE users ADD avatar MEDIUMBLOB NULL AFTER email');
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->binary('avatar')->nullable()->after('email');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_mime_type', 100)->nullable()->after('avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_mime_type');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users DROP COLUMN avatar');
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('avatar');
            });
        }
    }
};
