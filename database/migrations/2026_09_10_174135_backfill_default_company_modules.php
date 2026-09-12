<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $modules = [
            ['clave' => 'usuarios', 'nombre' => 'Usuarios', 'descripcion' => 'Cuentas y membresías empresariales.', 'orden' => 10],
            ['clave' => 'roles', 'nombre' => 'Roles', 'descripcion' => 'Roles y permisos propios de empresa.', 'orden' => 20],
            ['clave' => 'puestos', 'nombre' => 'Puestos', 'descripcion' => 'Catálogo de puestos y salarios empresariales.', 'orden' => 30],
            ['clave' => 'empleados', 'nombre' => 'Empleados', 'descripcion' => 'Expedientes, documentos y archivos de empleados.', 'orden' => 40],
        ];

        DB::table('modulos')->upsert(
            array_map(static fn (array $module): array => [
                ...$module,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $modules),
            ['clave'],
            ['nombre', 'descripcion', 'activo', 'orden', 'updated_at'],
        );

        $moduleIds = DB::table('modulos')
            ->whereIn('clave', array_column($modules, 'clave'))
            ->pluck('id');
        $rows = DB::table('empresas')
            ->pluck('id')
            ->crossJoin($moduleIds)
            ->map(static fn (array $ids): array => [
                'empresa_id' => $ids[0],
                'modulo_id' => $ids[1],
                'habilitado' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('empresa_modulo')->insertOrIgnore($rows);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $moduleIds = DB::table('modulos')
            ->whereIn('clave', ['usuarios', 'roles', 'puestos', 'empleados'])
            ->pluck('id');

        DB::table('empresa_modulo')->whereIn('modulo_id', $moduleIds)->delete();
        DB::table('modulos')->whereIn('id', $moduleIds)->delete();
    }
};
