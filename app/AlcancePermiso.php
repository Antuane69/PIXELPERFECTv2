<?php

namespace App;

enum AlcancePermiso: string
{
    case Empresa = 'EMPRESA';
    case Plataforma = 'PLATAFORMA';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
