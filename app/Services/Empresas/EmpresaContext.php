<?php

namespace App\Services\Empresas;

use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Models\MembresiaEmpresa;
use LogicException;

class EmpresaContext
{
    public const SESSION_KEY = 'empresa_contexto_id';

    private ?Empresa $empresa = null;

    private ?MembresiaEmpresa $membresia = null;

    public function establecer(Empresa $empresa, ?MembresiaEmpresa $membresia): void
    {
        if ($membresia !== null && $empresa->id !== $membresia->empresa_id) {
            throw new LogicException('La membresía no pertenece a la empresa activa.');
        }

        $this->empresa = $empresa;
        $this->membresia = $membresia;
    }

    public function existe(): bool
    {
        return $this->empresa !== null;
    }

    public function limpiar(): void
    {
        $this->empresa = null;
        $this->membresia = null;
    }

    public function empresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function empresaRequerida(): Empresa
    {
        return $this->empresa ?? throw new LogicException('No existe empresa activa en este contexto.');
    }

    public function grupoEmpresarial(): ?GrupoEmpresarial
    {
        return $this->empresa?->grupoEmpresarial;
    }

    public function membresia(): ?MembresiaEmpresa
    {
        return $this->membresia;
    }
}
