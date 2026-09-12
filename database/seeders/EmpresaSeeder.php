<?php

namespace Database\Seeders;

use App\EstadoEmpresa;
use App\EstadoMembresiaEmpresa;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Models\MembresiaEmpresa;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $grupo = GrupoEmpresarial::query()->where('slug', 'pixel-perfect')->firstOrFail();

        $empresa = Empresa::query()->updateOrCreate(
            ['slug' => 'pixel-perfect'],
            [
                'grupo_empresarial_id' => $grupo->id,
                'nombre_legal' => 'Pixel Perfect',
                'nombre_comercial' => 'Pixel Perfect',
                'zona_horaria' => 'America/Mexico_City',
                'moneda' => 'MXN',
                'estado' => EstadoEmpresa::Activa,
                'activada_at' => now(),
            ],
        );

        $administrador = User::query()
            ->where('es_superadministrador_plataforma', true)
            ->oldest('id')
            ->first();

        if ($administrador instanceof User) {
            MembresiaEmpresa::query()->updateOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'user_id' => $administrador->id,
                ],
                [
                    'estado' => EstadoMembresiaEmpresa::Activa,
                    'fecha_incorporacion' => now(),
                    'suspendida_at' => null,
                ],
            );
        }
    }
}
