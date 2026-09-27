<?php

namespace App\Enums\Incapacidades;

enum EstadoIncapacidad: string
{
    case Pendiente = 'PENDIENTE';
    case Autorizada = 'AUTORIZADA';
    case Vencida = 'VENCIDA';
    case Rechazada = 'RECHAZADA';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Autorizada => 'Autorizada',
            self::Vencida => 'Vencida',
            self::Rechazada => 'Rechazada',
        };
    }
}
