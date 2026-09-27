<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Modulo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuloSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $definitions = [
            ['clave' => 'usuarios', 'nombre' => 'Usuarios', 'descripcion' => 'Cuentas y membresías empresariales.', 'orden' => 10],
            ['clave' => 'roles', 'nombre' => 'Roles', 'descripcion' => 'Roles y permisos propios de empresa.', 'orden' => 20],
            ['clave' => 'puestos', 'nombre' => 'Puestos', 'descripcion' => 'Catálogo de puestos y salarios empresariales.', 'orden' => 30],
            ['clave' => 'empleados', 'nombre' => 'Empleados', 'descripcion' => 'Expedientes, documentos y archivos de empleados.', 'orden' => 40],
            ['clave' => 'vacaciones', 'nombre' => 'Vacaciones', 'descripcion' => 'Solicitudes, autorizaciones y saldo de vacaciones.', 'orden' => 50],
            ['clave' => 'permisos_laborales', 'nombre' => 'Permisos laborales', 'descripcion' => 'Solicitudes, autorizaciones y vencimientos de permisos laborales.', 'orden' => 60],
            ['clave' => 'incapacidades', 'nombre' => 'Incapacidades', 'descripcion' => 'Solicitudes, justificantes, autorizaciones y vencimientos de incapacidades.', 'orden' => 70],
            ['clave' => 'faltas_reglamento', 'nombre' => 'Faltas al reglamento', 'descripcion' => 'Catálogos, reportes, revisión y evidencia de faltas laborales.', 'orden' => 80],
        ];

        foreach ($definitions as $definition) {
            $module = Modulo::query()->firstOrCreate(
                ['clave' => $definition['clave']],
                [...$definition, 'activo' => true],
            );

            $module->update([
                'nombre' => $definition['nombre'],
                'descripcion' => $definition['descripcion'],
                'orden' => $definition['orden'],
            ]);
        }

        $moduleIds = Modulo::query()
            ->whereIn('clave', array_column($definitions, 'clave'))
            ->where('activo', true)
            ->pluck('id');
        $now = now();

        Empresa::query()->select('id')->each(function (Empresa $empresa) use ($moduleIds, $now): void {
            $rows = $moduleIds->map(static fn (int $moduleId): array => [
                'empresa_id' => $empresa->id,
                'modulo_id' => $moduleId,
                'habilitado' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DB::table('empresa_modulo')->insertOrIgnore($rows);
        });
    }
}
