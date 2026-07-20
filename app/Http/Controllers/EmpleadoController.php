<?php

namespace App\Http\Controllers;

use App\Actions\Empleados\DeleteEmpleado;
use App\Actions\Empleados\RestoreEmpleado;
use App\Actions\Empleados\SaveEmpleado;
use App\Http\Requests\Empleados\StoreEmpleadoRequest;
use App\Http\Requests\Empleados\UpdateEmpleadoRequest;
use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\Puesto;
use App\Models\TipoDocumentoEmpleado;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmpleadoController extends Controller
{
    public function __construct(
        private readonly SaveEmpleado $saveEmpleado,
        private readonly DeleteEmpleado $deleteEmpleado,
        private readonly RestoreEmpleado $restoreEmpleado,
    ) {}

    /**
     * Display a paginated employee listing.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Empleado::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = $this->perPage($request);
        $archivados = $request->boolean('archivados');

        $empleados = Empleado::query()
            ->select([
                'id',
                'nombre',
                'nombre_usuario',
                'correo',
                'curp',
                'rfc',
                'nss',
                'num_clinica_ss',
                'puesto_id',
                'estado_civil',
                'sexo',
                'domicilio',
                'telefono',
                'avatar',
                'salario_dia',
                'salario_quincena',
                'salario_vacaciones_finiquito',
                'aguinaldo',
                'prima_vacacional',
                'dias_vacaciones',
                'dias_liquidacion',
                'dias_descanso',
                'fecha_ingreso',
                'fecha_nacimiento',
                'fecha_contrato_siguiente',
                'fecha_contrato_indefinido',
                'fecha_ultimo_aviso',
                'fecha_evaluacion',
                'fecha_inicio_contrato',
                'fecha_termino_contrato',
                'created_at',
                'updated_at',
                'deleted_at',
            ])
            ->with([
                'puesto:id,nombre',
                'documentos' => fn ($query) => $query
                    ->select([
                        'id',
                        'empleado_id',
                        'tipo_documento_empleado_id',
                        'nombre_original',
                        'mime_type',
                        'tamano',
                        'vence_el',
                    ])
                    ->orderBy('id'),
                'documentos.tipoDocumento:id,nombre',
            ])
            ->when($archivados, fn (Builder $query) => $query->onlyTrashed())
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
                $request->integer('puesto_id') > 0,
                fn (Builder $query) => $query->where('puesto_id', $request->integer('puesto_id')),
            )
            ->when(
                $request->filled('estado_civil'),
                fn (Builder $query) => $query->where(
                    'estado_civil',
                    $request->string('estado_civil')->toString(),
                ),
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Empleado $empleado): array => $this->employeeData($empleado));

        return Inertia::render('empleados/index', [
            'empleados' => $empleados,
            'puestos' => Puesto::query()
                ->select(['id', 'nombre', 'activo'])
                ->orderBy('nombre')
                ->get(),
            'tiposDocumento' => TipoDocumentoEmpleado::query()
                ->select([
                    'id',
                    'nombre',
                    'documentos_aceptados',
                    'es_renovable',
                    'frecuencia_cantidad',
                    'frecuencia_tipo',
                    'activo',
                ])
                ->orderBy('nombre')
                ->get(),
            'filters' => [
                'search' => $search,
                'puestoId' => $request->integer('puesto_id') ?: null,
                'estadoCivil' => $request->string('estado_civil')->toString() ?: null,
                'archivados' => $archivados,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created employee and its files.
     */
    public function store(StoreEmpleadoRequest $request): RedirectResponse
    {
        Gate::authorize('create', Empleado::class);

        $this->saveEmpleado->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empleado creado correctamente.']);

        return to_route('empleados.index');
    }

    /**
     * Restore the specified archived employee and its private records.
     */
    public function restore(Empleado $empleado): RedirectResponse
    {
        Gate::authorize('restore', $empleado);

        $this->restoreEmpleado->handle($empleado);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empleado restaurado correctamente.']);

        return to_route('empleados.index', ['archivados' => true]);
    }

    /**
     * Update the specified employee and its files.
     */
    public function update(UpdateEmpleadoRequest $request, Empleado $empleado): RedirectResponse
    {
        Gate::authorize('update', $empleado);

        $this->saveEmpleado->handle($request->validated(), $empleado);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empleado actualizado correctamente.',
        ]);

        return to_route('empleados.index');
    }

    /**
     * Soft delete the specified employee while preserving its private records.
     */
    public function destroy(Empleado $empleado): RedirectResponse
    {
        Gate::authorize('delete', $empleado);

        $this->deleteEmpleado->handle($empleado);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empleado eliminado correctamente.']);

        return to_route('empleados.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeData(Empleado $empleado): array
    {
        return [
            'id' => $empleado->id,
            'nombre' => $empleado->nombre,
            'nombre_usuario' => $empleado->nombre_usuario,
            'correo' => $empleado->correo,
            'curp' => $empleado->curp,
            'rfc' => $empleado->rfc,
            'nss' => $empleado->nss,
            'num_clinica_ss' => $empleado->num_clinica_ss,
            'puesto_id' => $empleado->puesto_id,
            'estado_civil' => $empleado->estado_civil,
            'sexo' => $empleado->sexo,
            'domicilio' => $empleado->domicilio,
            'telefono' => $empleado->telefono,
            'avatar_url' => $empleado->trashed() || $empleado->avatar === null
                ? null
                : route('empleados.avatar', $empleado, absolute: false),
            'salario_dia' => $this->decimal($empleado->salario_dia),
            'salario_quincena' => $this->decimal($empleado->salario_quincena),
            'salario_vacaciones_finiquito' => $this->decimal($empleado->salario_vacaciones_finiquito),
            'aguinaldo' => $this->decimal($empleado->aguinaldo),
            'prima_vacacional' => $this->decimal($empleado->prima_vacacional),
            'dias_vacaciones' => $empleado->dias_vacaciones,
            'dias_liquidacion' => $empleado->dias_liquidacion,
            'dias_descanso' => $empleado->dias_descanso,
            'fecha_ingreso' => $this->date($empleado->fecha_ingreso),
            'fecha_nacimiento' => $this->date($empleado->fecha_nacimiento),
            'fecha_contrato_siguiente' => $this->date($empleado->fecha_contrato_siguiente),
            'fecha_contrato_indefinido' => $this->date($empleado->fecha_contrato_indefinido),
            'fecha_ultimo_aviso' => $this->date($empleado->fecha_ultimo_aviso),
            'fecha_evaluacion' => $this->date($empleado->fecha_evaluacion),
            'fecha_inicio_contrato' => $this->date($empleado->fecha_inicio_contrato),
            'fecha_termino_contrato' => $this->date($empleado->fecha_termino_contrato),
            'puesto' => $empleado->puesto === null ? null : [
                'id' => $empleado->puesto->id,
                'nombre' => $empleado->puesto->nombre,
            ],
            'documentos' => $empleado->documentos
                ->map(fn (EmpleadoDocumento $documento): array => [
                    'id' => $documento->id,
                    'tipo_documento_empleado_id' => $documento->tipo_documento_empleado_id,
                    'tipo' => $documento->tipoDocumento === null ? null : [
                        'id' => $documento->tipoDocumento->id,
                        'nombre' => $documento->tipoDocumento->nombre,
                    ],
                    'nombre_original' => $documento->nombre_original,
                    'mime_type' => $documento->mime_type,
                    'tamano' => $documento->tamano,
                    'vence_el' => $this->date($documento->vence_el),
                    'download_url' => $empleado->trashed()
                        ? null
                        : route('empleados.documentos.download', [
                            'empleado' => $empleado,
                            'documento' => $documento,
                        ], absolute: false),
                ])
                ->values()
                ->all(),
            'created_at' => $empleado->created_at?->toISOString(),
            'updated_at' => $empleado->updated_at?->toISOString(),
            'deleted_at' => $empleado->deleted_at?->toISOString(),
        ];
    }

    private function decimal(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value === null ? null : (string) $value;
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
