<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $storedGrupoId = DB::table('grupos_empresariales')
            ->where('slug', 'pixel-perfect')
            ->value('id');

        $grupoId = is_numeric($storedGrupoId)
            ? (int) $storedGrupoId
            : DB::table('grupos_empresariales')->insertGetId([
                'nombre' => 'Pixel Perfect',
                'slug' => 'pixel-perfect',
                'tipo' => 'INDIVIDUAL',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $storedEmpresaId = DB::table('empresas')
            ->where('slug', 'pixel-perfect')
            ->value('id');

        $empresaId = is_numeric($storedEmpresaId)
            ? (int) $storedEmpresaId
            : DB::table('empresas')->insertGetId([
                'grupo_empresarial_id' => $grupoId,
                'nombre_legal' => 'Pixel Perfect',
                'nombre_comercial' => 'Pixel Perfect',
                'slug' => 'pixel-perfect',
                'zona_horaria' => 'America/Mexico_City',
                'moneda' => 'MXN',
                'estado' => 'ACTIVA',
                'activada_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('roles')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
        DB::table('model_has_roles')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
        DB::table('model_has_permissions')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $users) use ($empresaId, $now): void {
                $memberships = $users->map(fn (object $user): array => [
                    'empresa_id' => $empresaId,
                    'user_id' => $user->id,
                    'estado' => 'ACTIVA',
                    'fecha_incorporacion' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('membresias_empresa')->insertOrIgnore($memberships);
            });

        $storedAdministratorRoleId = DB::table('roles')
            ->where('empresa_id', $empresaId)
            ->where('name', 'Administrador')
            ->where('guard_name', 'web')
            ->value('id');

        if (is_numeric($storedAdministratorRoleId)) {
            $storedPlatformAdministratorId = DB::table('model_has_roles')
                ->where('empresa_id', $empresaId)
                ->where('role_id', (int) $storedAdministratorRoleId)
                ->where('model_type', 'App\\Models\\User')
                ->orderBy('model_id')
                ->value('model_id');

            if (is_numeric($storedPlatformAdministratorId)) {
                DB::table('users')
                    ->where('id', (int) $storedPlatformAdministratorId)
                    ->update(['es_superadministrador_plataforma' => true]);
            }
        }
    }

    /**
     * Backfill remains because reversing it could delete tenant data created after migration.
     */
    public function down(): void {}
};
