<?php

namespace App\Actions\Empresas;

use App\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\TipoGrupoEmpresarial;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrearEmpresa
{
    public function __construct(
        private CrearRolesPredeterminadosEmpresa $crearRolesPredeterminadosEmpresa,
        private HabilitarModulosPredeterminadosEmpresa $habilitarModulosPredeterminadosEmpresa,
    ) {}

    public function handle(
        string $nombreLegal,
        ?string $nombreComercial,
        ?int $grupoEmpresarialId,
        ?string $rfc,
        ?string $correoContacto,
        ?string $telefonoContacto,
        string $zonaHoraria,
        string $moneda,
        EstadoEmpresa $estado,
        ?CarbonInterface $demoEndsAt,
    ): Empresa {
        return DB::transaction(function () use (
            $nombreLegal,
            $nombreComercial,
            $grupoEmpresarialId,
            $rfc,
            $correoContacto,
            $telefonoContacto,
            $zonaHoraria,
            $moneda,
            $estado,
            $demoEndsAt,
        ): Empresa {
            $nombreVisible = $nombreComercial ?: $nombreLegal;

            if ($grupoEmpresarialId === null) {
                $grupo = GrupoEmpresarial::query()->create([
                    'nombre' => $nombreVisible,
                    'slug' => $this->slugUnico(GrupoEmpresarial::class, $nombreVisible),
                    'tipo' => TipoGrupoEmpresarial::Individual,
                    'activo' => true,
                ]);
            } else {
                $grupo = GrupoEmpresarial::query()->lockForUpdate()->findOrFail($grupoEmpresarialId);

                if ($grupo->tipo === TipoGrupoEmpresarial::Individual && $grupo->empresas()->exists()) {
                    $grupo->update(['tipo' => TipoGrupoEmpresarial::Corporativo]);
                }
            }

            $empresa = Empresa::query()->create([
                'grupo_empresarial_id' => $grupo->id,
                'nombre_legal' => $nombreLegal,
                'nombre_comercial' => $nombreComercial,
                'slug' => $this->slugUnico(Empresa::class, $nombreVisible),
                'rfc' => $rfc,
                'correo_contacto' => $correoContacto,
                'telefono_contacto' => $telefonoContacto,
                'zona_horaria' => $zonaHoraria,
                'moneda' => $moneda,
                'estado' => $estado,
                'demo_ends_at' => $demoEndsAt,
                'activada_at' => $estado === EstadoEmpresa::Activa ? now() : null,
            ]);

            $this->crearRolesPredeterminadosEmpresa->handle($empresa);
            $this->habilitarModulosPredeterminadosEmpresa->handle($empresa);

            return $empresa;
        });
    }

    /**
     * @param  class-string<Empresa|GrupoEmpresarial>  $modelClass
     */
    private function slugUnico(string $modelClass, string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'empresa';
        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
