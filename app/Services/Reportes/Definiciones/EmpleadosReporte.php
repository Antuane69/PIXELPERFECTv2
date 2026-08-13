<?php

namespace App\Services\Reportes\Definiciones;

use App\Models\Empleado;
use App\Services\Reportes\Contracts\ReporteExportable;
use App\Services\Reportes\ExportConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmpleadosReporte implements ReporteExportable
{
    /** @param array<string, mixed> $filtros */
    public function autorizar(Authenticatable $usuario, array $filtros): void
    {
        Gate::forUser($usuario)->authorize('viewAny', Empleado::class);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function validarFiltros(array $filtros): array
    {
        return Validator::validate($filtros, [
            'search' => ['nullable', 'string', 'max:255'],
            'puesto_id' => ['nullable', 'integer', 'min:1'],
            'estado_civil' => ['nullable', 'string', 'max:30'],
            'archivados' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Empleado>
     */
    public function query(array $filtros): Builder
    {
        $search = Str::squish((string) ($filtros['search'] ?? ''));

        return Empleado::query()
            ->select([
                'id',
                'nombre',
                'nombre_usuario',
                'correo',
                'curp',
                'rfc',
                'nss',
                'puesto_id',
                'estado_civil',
                'sexo',
                'telefono',
                'salario_dia',
                'salario_quincena',
                'fecha_ingreso',
                'fecha_nacimiento',
                'deleted_at',
            ])
            ->with('puesto:id,nombre')
            ->when((bool) ($filtros['archivados'] ?? false), fn (Builder $query) => $query->onlyTrashed())
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('nombre', 'like', "%{$search}%")
                        ->orWhere('nombre_usuario', 'like', "%{$search}%")
                        ->orWhere('correo', 'like', "%{$search}%")
                        ->orWhere('curp', 'like', "%{$search}%")
                        ->orWhere('rfc', 'like', "%{$search}%")
                        ->orWhere('nss', 'like', "%{$search}%")
                        ->orWhere('telefono', 'like', "%{$search}%");
                });
            })
            ->when(
                isset($filtros['puesto_id']),
                fn (Builder $query) => $query->where('puesto_id', $filtros['puesto_id']),
            )
            ->when(
                filled($filtros['estado_civil'] ?? null),
                fn (Builder $query) => $query->where('estado_civil', $filtros['estado_civil']),
            )
            ->orderByDesc('id');
    }

    /** @param array<string, mixed> $filtros */
    public function config(array $filtros, string $formato): ExportConfig
    {
        $columns = $formato === 'pdf'
            ? [
                'id' => 'ID',
                'nombre' => 'Empleado',
                'puesto' => 'Puesto',
                'correo' => 'Correo',
                'telefono' => 'Teléfono',
                'fecha_ingreso' => 'Ingreso',
                'estado' => 'Estado',
            ]
            : [
                'id' => 'ID',
                'nombre' => 'Empleado',
                'nombre_usuario' => 'Usuario',
                'correo' => 'Correo',
                'curp' => 'CURP',
                'rfc' => 'RFC',
                'nss' => 'NSS',
                'puesto' => 'Puesto',
                'estado_civil' => 'Estado civil',
                'sexo' => 'Sexo',
                'telefono' => 'Teléfono',
                'salario_dia' => 'Salario diario',
                'salario_quincena' => 'Salario quincenal',
                'fecha_nacimiento' => 'Nacimiento',
                'fecha_ingreso' => 'Ingreso',
                'estado' => 'Estado',
            ];

        return ExportConfig::make()
            ->title('Empleados')
            ->fileName('empleados_'.now()->format('Ymd_His'))
            ->sheetName('Empleados')
            ->columns($columns)
            ->formatters([
                'puesto' => fn (mixed $valor, Empleado $empleado): string => $empleado->getAttribute('puesto_id') === null
                    ? 'Sin puesto'
                    : $empleado->puesto->nombre,
                'salario_dia' => fn (mixed $valor): string => $this->moneda($valor),
                'salario_quincena' => fn (mixed $valor): string => $this->moneda($valor),
                'fecha_nacimiento' => fn (mixed $valor): string => $valor?->format('d/m/Y') ?? '',
                'fecha_ingreso' => fn (mixed $valor): string => $valor?->format('d/m/Y') ?? '',
                'estado' => fn (mixed $valor, Empleado $empleado): string => $empleado->trashed() ? 'Archivado' : 'Vigente',
            ])
            ->columnWidths([
                'id' => 10,
                'nombre' => 32,
                'nombre_usuario' => 22,
                'correo' => 34,
                'curp' => 24,
                'rfc' => 20,
                'nss' => 18,
                'puesto' => 28,
                'estado_civil' => 18,
                'sexo' => 16,
                'telefono' => 18,
                'salario_dia' => 20,
                'salario_quincena' => 22,
                'fecha_nacimiento' => 16,
                'fecha_ingreso' => 16,
                'estado' => 14,
            ])
            ->excelMaxColumnWidth(38);
    }

    private function moneda(mixed $valor): string
    {
        return $valor === null ? '' : '$'.number_format((float) $valor, 2).' MXN';
    }
}
