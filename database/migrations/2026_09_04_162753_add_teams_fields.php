<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamKey = $columnNames['team_foreign_key'] ?? 'empresa_id';
        $modelKey = $columnNames['model_morph_key'] ?? 'model_id';
        $roleKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $permissionKey = $columnNames['permission_pivot_key'] ?? 'permission_id';

        throw_if(empty($tableNames), 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        if (! Schema::hasColumn($tableNames['roles'], $teamKey)) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey): void {
                $table->foreignId($teamKey)
                    ->nullable()
                    ->after('id')
                    ->constrained('empresas')
                    ->cascadeOnDelete();
                $table->dropUnique('roles_name_guard_name_unique');
                $table->unique([$teamKey, 'name', 'guard_name'], 'roles_empresa_name_guard_unique');
            });
        } else {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey): void {
                $table->foreign($teamKey)
                    ->references('id')
                    ->on('empresas')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn($tableNames['model_has_permissions'], $teamKey)) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use (
                $tableNames,
                $teamKey,
                $modelKey,
                $permissionKey,
            ): void {
                $table->foreignId($teamKey)
                    ->nullable()
                    ->constrained('empresas')
                    ->cascadeOnDelete();

                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign([$permissionKey]);
                }

                $table->dropPrimary();
                $table->unique(
                    [$teamKey, $permissionKey, $modelKey, 'model_type'],
                    'model_permissions_empresa_unique',
                );

                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign($permissionKey)
                        ->references('id')
                        ->on($tableNames['permissions'])
                        ->cascadeOnDelete();
                }
            });
        } else {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey): void {
                $table->foreign($teamKey)
                    ->references('id')
                    ->on('empresas')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn($tableNames['model_has_roles'], $teamKey)) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use (
                $tableNames,
                $teamKey,
                $modelKey,
                $roleKey,
            ): void {
                $table->foreignId($teamKey)
                    ->nullable()
                    ->constrained('empresas')
                    ->cascadeOnDelete();

                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign([$roleKey]);
                }

                $table->dropPrimary();
                $table->unique(
                    [$teamKey, $roleKey, $modelKey, 'model_type'],
                    'model_roles_empresa_unique',
                );

                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign($roleKey)
                        ->references('id')
                        ->on($tableNames['roles'])
                        ->cascadeOnDelete();
                }
            });
        } else {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey): void {
                $table->foreign($teamKey)
                    ->references('id')
                    ->on('empresas')
                    ->cascadeOnDelete();
            });
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Compatibility migration: columns may belong to original permission migration.
    }
};
