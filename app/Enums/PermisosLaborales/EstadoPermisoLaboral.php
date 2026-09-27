<?php

namespace App\Enums\PermisosLaborales;

enum EstadoPermisoLaboral: string
{
    case Pendiente = 'PENDIENTE';
    case Autorizado = 'AUTORIZADO';
    case Rechazado = 'RECHAZADO';
    case Vencido = 'VENCIDO';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Autorizado => 'Autorizado',
            self::Rechazado => 'Rechazado',
            self::Vencido => 'Vencido',
        };
    }
}
