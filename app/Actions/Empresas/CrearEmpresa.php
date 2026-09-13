<?php

namespace App\Actions\Empresas;

use App\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Services\ImageCompressor;
use App\TipoGrupoEmpresarial;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrearEmpresa
{
    public function __construct(
        private CrearRolesPredeterminadosEmpresa $crearRolesPredeterminadosEmpresa,
        private HabilitarModulosPredeterminadosEmpresa $habilitarModulosPredeterminadosEmpresa,
        private ImageCompressor $imageCompressor,
    ) {}

    public function handle(
        string $nombreLegal,
        ?string $nombreComercial,
        ?int $grupoEmpresarialId,
        ?string $rfc,
        ?string $correoContacto,
        ?string $codigoPaisContacto,
        ?string $telefonoContacto,
        string $zonaHoraria,
        string $moneda,
        EstadoEmpresa $estado,
        ?CarbonInterface $demoEndsAt,
        ?UploadedFile $logo = null,
    ): Empresa {
        $compressedLogo = $logo === null ? null : $this->imageCompressor->compressIfImage($logo);

        if ($logo !== null && $compressedLogo === null) {
            throw new \RuntimeException('No se pudo comprimir el logo de la empresa.');
        }

        return DB::transaction(function () use (
            $nombreLegal,
            $nombreComercial,
            $grupoEmpresarialId,
            $rfc,
            $correoContacto,
            $codigoPaisContacto,
            $telefonoContacto,
            $zonaHoraria,
            $moneda,
            $estado,
            $demoEndsAt,
            $compressedLogo,
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
                'codigo_pais_contacto' => $codigoPaisContacto,
                'telefono_contacto' => $telefonoContacto,
                'logo' => $compressedLogo['contents'] ?? null,
                'logo_mime_type' => $compressedLogo['mime_type'] ?? null,
                'zona_horaria' => $zonaHoraria,
                'moneda' => $moneda,
                'estado' => $estado,
                'demo_ends_at' => $demoEndsAt,
                'activada_at' => $estado === EstadoEmpresa::Activa ? now() : null,
            ]);

            $this->habilitarModulosPredeterminadosEmpresa->handle($empresa);
            $this->crearRolesPredeterminadosEmpresa->handle($empresa);

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
