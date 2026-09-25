<?php

namespace App\Services\Empleados;

use App\Models\Empleado;
use DateTimeImmutable;
use DateTimeInterface;

class EmpleadoDocumentoVariables
{
    /** @var array<string, string> */
    private const DEFINITIONS = [
        'nombre_empleado' => 'Nombre completo',
        'nombre_usuario' => 'Nombre de usuario',
        'correo_empleado' => 'Correo',
        'curp' => 'CURP',
        'rfc' => 'RFC',
        'nss' => 'NSS',
        'numero_clinica' => 'Número de clínica IMSS',
        'puesto' => 'Puesto',
        'estado_civil' => 'Estado civil',
        'sexo' => 'Sexo',
        'domicilio' => 'Domicilio',
        'telefono' => 'Teléfono',
        'salario_dia' => 'Salario diario (MXN)',
        'salario_quincena' => 'Salario quincenal (MXN)',
        'salario_vacaciones_finiquito' => 'Salario para vacaciones y finiquito (MXN)',
        'aguinaldo' => 'Aguinaldo (MXN)',
        'prima_vacacional' => 'Prima vacacional (MXN)',
        'dias_vacaciones' => 'Días de vacaciones',
        'dias_liquidacion' => 'Días de liquidación',
        'dias_descanso' => 'Días de descanso',
        'fecha_ingreso' => 'Fecha de ingreso',
        'fecha_nacimiento' => 'Fecha de nacimiento',
        'periodo_prueba_meses' => 'Periodo de prueba (meses)',
        'fecha_contrato_siguiente' => 'Fecha del siguiente contrato',
        'fecha_contrato_indefinido' => 'Fecha de contrato indefinido',
        'fecha_ultimo_aviso' => 'Fecha del último aviso',
        'fecha_evaluacion' => 'Fecha de evaluación',
        'fecha_inicio_contrato' => 'Fecha de inicio de contrato',
        'fecha_termino_contrato' => 'Fecha de término de contrato',
        'nombre_empresa' => 'Nombre comercial de la empresa',
        'razon_social_empresa' => 'Razón social de la empresa',
    ];

    /**
     * @return array<string, string>
     */
    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * @return list<string>
     */
    public function unknownVariables(string $html): array
    {
        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/u', $html, $matches);
        $names = array_map(static fn (string $name): string => trim($name), $matches[1]);

        return array_values(array_unique(array_filter(
            $names,
            static fn (string $name): bool => ! array_key_exists($name, self::DEFINITIONS),
        )));
    }

    /**
     * @return array<string, string>
     */
    public function values(Empleado $empleado): array
    {
        $empleado->loadMissing(['empresa', 'puesto']);
        $empresa = $empleado->empresa;

        return [
            'nombre_empleado' => $empleado->nombre,
            'nombre_usuario' => $empleado->nombre_usuario,
            'correo_empleado' => $empleado->correo,
            'curp' => $empleado->curp,
            'rfc' => $empleado->rfc,
            'nss' => $empleado->nss ?? '',
            'numero_clinica' => $empleado->num_clinica_ss ?? '',
            'puesto' => $empleado->puesto->nombre,
            'estado_civil' => $empleado->estado_civil,
            'sexo' => $empleado->sexo,
            'domicilio' => $empleado->domicilio,
            'telefono' => $empleado->telefono,
            'salario_dia' => $this->formatMoney($empleado->salario_dia),
            'salario_quincena' => $this->formatMoney($empleado->salario_quincena),
            'salario_vacaciones_finiquito' => $this->formatMoney($empleado->salario_vacaciones_finiquito),
            'aguinaldo' => $this->formatMoney($empleado->aguinaldo),
            'prima_vacacional' => $this->formatMoney($empleado->prima_vacacional),
            'dias_vacaciones' => $empleado->dias_vacaciones === null ? '' : (string) $empleado->dias_vacaciones,
            'dias_liquidacion' => $empleado->dias_liquidacion === null ? '' : (string) $empleado->dias_liquidacion,
            'dias_descanso' => $this->formatRestDays($empleado->getAttribute('dias_descanso')),
            'fecha_ingreso' => $this->formatDate($empleado->fecha_ingreso),
            'fecha_nacimiento' => $this->formatDate($empleado->fecha_nacimiento),
            'periodo_prueba_meses' => $empleado->periodo_prueba_meses === null ? '' : (string) $empleado->periodo_prueba_meses,
            'fecha_contrato_siguiente' => $this->formatDate($empleado->fecha_contrato_siguiente),
            'fecha_contrato_indefinido' => $this->formatDate($empleado->fecha_contrato_indefinido),
            'fecha_ultimo_aviso' => $this->formatDate($empleado->fecha_ultimo_aviso),
            'fecha_evaluacion' => $this->formatDate($empleado->fecha_evaluacion),
            'fecha_inicio_contrato' => $this->formatDate($empleado->fecha_inicio_contrato),
            'fecha_termino_contrato' => $this->formatDate($empleado->fecha_termino_contrato),
            'nombre_empresa' => $empresa->nombre_comercial ?: $empresa->nombre_legal,
            'razon_social_empresa' => $empresa->nombre_legal,
        ];
    }

    private function formatMoney(int|float|string|null $amount): string
    {
        return $amount === null ? '' : '$'.number_format((float) $amount, 2, '.', ',');
    }

    private function formatDate(DateTimeInterface|string|null $date): string
    {
        if (is_string($date)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $date) ?: null;
        }

        return $date?->format('d/m/Y') ?? '';
    }

    private function formatRestDays(mixed $days): string
    {
        if (! is_array($days)) {
            return '';
        }

        return implode(', ', array_filter($days, 'is_string'));
    }
}
