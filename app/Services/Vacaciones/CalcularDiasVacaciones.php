<?php

namespace App\Services\Vacaciones;

use App\EstadoVacacion;
use App\Models\Empleado;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class CalcularDiasVacaciones
{
    /**
     * @param  array<int, string>|null  $diasDescanso
     * @param  array<int, string>  $fechasFestivas
     */
    public function contarDiasSolicitados(
        string $fechaInicio,
        string $fechaFin,
        ?array $diasDescanso,
        array $fechasFestivas = [],
    ): int {
        $diasDescanso = array_values(array_unique([
            'sabado',
            'domingo',
            ...($diasDescanso ?? []),
        ]));
        $diasSemana = [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            7 => 'domingo',
        ];
        $inicio = CarbonImmutable::createFromFormat('!Y-m-d', $fechaInicio);
        $fin = CarbonImmutable::createFromFormat('!Y-m-d', $fechaFin);

        if ($inicio === null || $fin === null || $fin->lessThan($inicio)) {
            return 0;
        }

        $dias = 0;
        $fechasFestivas = array_fill_keys($fechasFestivas, true);

        foreach (CarbonPeriod::create($inicio, $fin) as $fecha) {
            if (
                ! in_array($diasSemana[$fecha->dayOfWeekIso], $diasDescanso, true)
                && ! isset($fechasFestivas[$fecha->toDateString()])
            ) {
                $dias++;
            }
        }

        return $dias;
    }

    public function fechaUltimaVacacion(Empleado $empleado, string $fechaActual): ?string
    {
        $fecha = $empleado->vacaciones()
            ->whereIn('estado', [EstadoVacacion::Autorizada->value, EstadoVacacion::Vencida->value])
            ->whereDate('fecha_fin', '<', $fechaActual)
            ->max('fecha_fin');

        return is_string($fecha) ? $fecha : null;
    }
}
