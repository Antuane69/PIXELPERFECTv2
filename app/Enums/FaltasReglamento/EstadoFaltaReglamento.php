<?php

namespace App\Enums\FaltasReglamento;

enum EstadoFaltaReglamento: string
{
    case Pendiente = 'PENDIENTE';
    case Autorizada = 'AUTORIZADA';
    case Rechazada = 'RECHAZADA';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Autorizada => 'Autorizada',
            self::Rechazada => 'Rechazada',
        };
    }
}
