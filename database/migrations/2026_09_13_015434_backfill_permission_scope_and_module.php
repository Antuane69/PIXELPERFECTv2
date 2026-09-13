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
        $moduleIds = DB::table('modulos')->pluck('id', 'clave');

        foreach ([
            'users.%' => 'usuarios',
            'roles.%' => 'roles',
            'puestos.%' => 'puestos',
            'empleados.%' => 'empleados',
            'tipos_documento.%' => 'empleados',
        ] as $permissionPattern => $moduleKey) {
            $moduleId = $moduleIds->get($moduleKey);

            if ($moduleId !== null) {
                DB::table('permissions')
                    ->where('name', 'like', $permissionPattern)
                    ->update(['alcance' => 'EMPRESA', 'modulo_id' => $moduleId]);
            }
        }

        DB::table('permissions')
            ->where('name', 'like', 'logs.%')
            ->update(['alcance' => 'PLATAFORMA', 'modulo_id' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->update([
            'alcance' => 'EMPRESA',
            'modulo_id' => null,
        ]);
    }
};
