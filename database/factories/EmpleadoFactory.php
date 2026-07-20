<?php

namespace Database\Factories;

use App\Models\Empleado;
use App\Models\Puesto;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Empleado>
 */
class EmpleadoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fechaNacimiento = CarbonImmutable::instance(fake()->dateTimeBetween('-60 years', '-18 years'));
        $fechaIngreso = CarbonImmutable::instance(fake()->dateTimeBetween('-10 years', 'now'));
        $fechaInicioContrato = $fechaIngreso;
        $fechaTerminoContrato = $fechaInicioContrato->addMonths(3);
        $fechaContratoSiguiente = $fechaTerminoContrato;
        $fechaContratoIndefinido = $fechaContratoSiguiente->addMonths(3);
        $salarioDia = fake()->randomFloat(2, 250, 5000);

        return [
            'nombre' => fake()->name(),
            'nombre_usuario' => Str::lower(Str::limit(fake()->unique()->userName(), 60, '')),
            'correo' => fake()->unique()->safeEmail(),
            'curp' => Str::upper(fake()->unique()->regexify(
                '[A-Z][AEIOUX][A-Z]{2}[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])[HM][A-Z]{2}[BCDFGHJKLMNPQRSTVWXYZ]{3}[A-Z0-9][0-9]',
            )),
            'rfc' => Str::upper(fake()->unique()->regexify('[A-Z]{4}[0-9]{6}[A-Z0-9]{3}')),
            'nss' => fake()->unique()->numerify('###########'),
            'num_clinica_ss' => 'Clínica '.fake()->numberBetween(1, 200),
            'puesto_id' => Puesto::factory(),
            'estado_civil' => fake()->randomElement([
                'soltero',
                'casado',
                'divorciado',
                'union_libre',
                'viudo',
            ]),
            'sexo' => fake()->randomElement(['masculino', 'femenino', 'otro']),
            'domicilio' => fake()->streetAddress(),
            'telefono' => fake()->numerify('##########'),
            'avatar' => null,
            'salario_dia' => $salarioDia,
            'salario_quincena' => round($salarioDia * 15, 2),
            'salario_vacaciones_finiquito' => fake()->randomFloat(2, 0, 100000),
            'aguinaldo' => fake()->randomFloat(2, 0, 100000),
            'prima_vacacional' => fake()->randomFloat(2, 0, 100000),
            'dias_vacaciones' => fake()->numberBetween(0, 30),
            'dias_liquidacion' => fake()->numberBetween(0, 90),
            'dias_descanso' => fake()->randomElements(
                ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'],
                fake()->numberBetween(1, 2),
            ),
            'fecha_ingreso' => $fechaIngreso->toDateString(),
            'fecha_nacimiento' => $fechaNacimiento->toDateString(),
            'fecha_contrato_siguiente' => $fechaContratoSiguiente->toDateString(),
            'fecha_contrato_indefinido' => $fechaContratoIndefinido->toDateString(),
            'fecha_ultimo_aviso' => null,
            'fecha_evaluacion' => $fechaIngreso->addMonth()->toDateString(),
            'fecha_inicio_contrato' => $fechaInicioContrato->toDateString(),
            'fecha_termino_contrato' => $fechaTerminoContrato->toDateString(),
        ];
    }

    public function withoutNss(): static
    {
        return $this->state(fn (array $attributes): array => [
            'nss' => null,
            'num_clinica_ss' => null,
        ]);
    }

    public function withAvatar(): static
    {
        return $this->state(fn (array $attributes): array => [
            'avatar' => 'empleados/avatars/'.Str::uuid().'.webp',
        ]);
    }
}
