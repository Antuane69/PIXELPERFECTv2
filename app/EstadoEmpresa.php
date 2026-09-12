<?php

namespace App;

enum EstadoEmpresa: string
{
    case Prospecto = 'PROSPECTO';
    case Demo = 'DEMO';
    case Activa = 'ACTIVA';
    case Vencida = 'VENCIDA';
    case Desactivada = 'DESACTIVADA';

    public function permiteAcceso(): bool
    {
        return in_array($this, [self::Demo, self::Activa], true);
    }
}
