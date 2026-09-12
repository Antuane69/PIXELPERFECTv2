<?php

namespace Database\Seeders;

use App\Models\GrupoEmpresarial;
use App\TipoGrupoEmpresarial;
use Illuminate\Database\Seeder;

class GrupoEmpresarialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GrupoEmpresarial::query()->updateOrCreate(
            ['slug' => 'pixel-perfect'],
            [
                'nombre' => 'Pixel Perfect',
                'tipo' => TipoGrupoEmpresarial::Individual,
                'activo' => true,
            ],
        );
    }
}
