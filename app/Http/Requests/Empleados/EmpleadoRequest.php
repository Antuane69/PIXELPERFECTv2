<?php

namespace App\Http\Requests\Empleados;

use App\Models\Empleado;
use App\Models\Puesto;
use App\Models\TipoDocumentoEmpleado;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

abstract class EmpleadoRequest extends FormRequest
{
    private const DOCUMENT_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'doc',
        'docx',
        'xls',
        'xlsx',
    ];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isCreating() ? 'required' : 'sometimes';
        $nullable = $this->isCreating() ? 'nullable' : 'sometimes';
        $today = now()->toDateString();
        $minimumBirthDate = now()->subYears(16)->toDateString();
        $currentPuestoId = $this->boundEmpleado()?->puesto_id;
        $existingDocumentTypeIds = $this->boundEmpleado()?->documentos()
            ->pluck('tipo_documento_empleado_id')
            ->all() ?? [];

        $rules = [
            'nombre' => [$required, 'required', 'string', 'min:3', 'max:120'],
            'nombre_usuario' => [
                $required,
                'required',
                'string',
                'min:4',
                'max:60',
                'regex:/^[a-z0-9._-]+$/',
                $this->uniqueRule('nombre_usuario'),
            ],
            'correo' => [
                $required,
                'required',
                'email:rfc',
                'max:120',
                $this->uniqueRule('correo'),
            ],
            'curp' => [
                $required,
                'required',
                'string',
                'size:18',
                'regex:/^[A-Z][AEIOUX][A-Z]{2}[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])[HM][A-Z]{2}[BCDFGHJKLMNPQRSTVWXYZ]{3}[A-Z0-9][0-9]$/',
                $this->uniqueRule('curp'),
            ],
            'rfc' => [
                $required,
                'required',
                'string',
                'max:13',
                'regex:/^[A-Z&Ñ]{3,4}\d{6}[A-Z\d]{3}$/u',
                $this->uniqueRule('rfc'),
            ],
            'nss' => [
                $nullable,
                'nullable',
                'string',
                'regex:/^[0-9]{11}$/',
                $this->uniqueRule('nss'),
            ],
            'num_clinica_ss' => [$nullable, 'nullable', 'string', 'max:120'],
            'puesto_id' => [
                $required,
                'required',
                'integer',
                Rule::exists(Puesto::class, 'id')->where(
                    static function ($query) use ($currentPuestoId): void {
                        $query->whereNull('deleted_at')->where(
                            static function ($query) use ($currentPuestoId): void {
                                $query->where('activo', true);

                                if ($currentPuestoId !== null) {
                                    $query->orWhere('id', $currentPuestoId);
                                }
                            },
                        );
                    },
                ),
            ],
            'estado_civil' => [
                $required,
                'required',
                'string',
                Rule::in(['soltero', 'casado', 'divorciado', 'union_libre', 'viudo']),
            ],
            'sexo' => [
                $required,
                'required',
                'string',
                Rule::in(['masculino', 'femenino', 'otro']),
            ],
            'domicilio' => [$required, 'required', 'string', 'min:10', 'max:250'],
            'telefono' => [$required, 'required', 'string', 'regex:/^[0-9]{10}$/'],
            'avatar' => [$nullable, 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'salario_dia' => [
                $nullable,
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999999.99',
            ],
            'salario_quincena' => [
                $nullable,
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999999.99',
            ],
            'dias_vacaciones' => [$required, 'integer', 'min:2', 'max:3650'],
            'dias_descanso' => ['sometimes', 'array', 'max:7'],
            'dias_descanso.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo']),
            ],
            'fecha_ingreso' => [$required, 'required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'fecha_nacimiento' => [
                $required,
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.$minimumBirthDate,
            ],
            'periodo_prueba_meses' => [$required, 'integer', 'min:1', 'max:6'],
            'documentos' => ['sometimes', 'array'],
            'documentos.*' => ['array:tipo_documento_empleado_id,archivo,vence_el'],
            'documentos.*.tipo_documento_empleado_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(TipoDocumentoEmpleado::class, 'id')->where(
                    static function ($query) use ($existingDocumentTypeIds): void {
                        $query->whereNull('deleted_at')->where(
                            static function ($query) use ($existingDocumentTypeIds): void {
                                $query->where('activo', true);

                                if ($existingDocumentTypeIds !== []) {
                                    $query->orWhereIn('id', $existingDocumentTypeIds);
                                }
                            },
                        );
                    },
                ),
            ],
            'documentos.*.archivo' => [
                'nullable',
                'file',
                'mimes:'.implode(',', self::DOCUMENT_EXTENSIONS),
                'max:10240',
            ],
            'documentos.*.vence_el' => ['nullable', 'date_format:Y-m-d'],
        ];

        if (! $this->isCreating()) {
            $rules += [
                'salario_vacaciones_finiquito' => [
                    'sometimes',
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:9999999999.99',
                ],
                'aguinaldo' => [
                    'sometimes',
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:9999999999.99',
                ],
                'prima_vacacional' => [
                    'sometimes',
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:9999999999.99',
                ],
                'dias_liquidacion' => [
                    'sometimes',
                    'nullable',
                    'integer',
                    'min:0',
                    'max:3650',
                ],
            ];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateDocuments($validator);
            },
        ];
    }

    abstract protected function isCreating(): bool;

    protected function boundEmpleado(): ?Empleado
    {
        $empleado = $this->route('empleado');

        return $empleado instanceof Empleado ? $empleado : null;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $normalized = [];

        foreach (['nombre', 'num_clinica_ss', 'domicilio'] as $field) {
            if (array_key_exists($field, $data)) {
                $normalized[$field] = $this->nullableTrimmedString($data[$field]);
            }
        }

        if (array_key_exists('nombre_usuario', $data)) {
            $normalized['nombre_usuario'] = $this->nullableTrimmedString($data['nombre_usuario'], true);
        }

        if (array_key_exists('correo', $data)) {
            $normalized['correo'] = $this->nullableTrimmedString($data['correo'], true);
        }

        foreach (['curp', 'rfc'] as $field) {
            if (array_key_exists($field, $data)) {
                $normalized[$field] = $this->nullableUppercaseString($data[$field]);
            }
        }

        foreach (['nss', 'telefono'] as $field) {
            if (array_key_exists($field, $data)) {
                $normalized[$field] = $this->nullableTrimmedString($data[$field]);
            }
        }

        foreach (['estado_civil', 'sexo'] as $field) {
            if (array_key_exists($field, $data)) {
                $normalized[$field] = $this->nullableTrimmedString($data[$field], true);
            }
        }

        foreach ([
            'salario_dia',
            'salario_quincena',
            'salario_vacaciones_finiquito',
            'aguinaldo',
            'prima_vacacional',
            'dias_vacaciones',
            'dias_liquidacion',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $normalized[$field] = null;
            }
        }

        if (isset($data['dias_descanso']) && is_array($data['dias_descanso'])) {
            $normalized['dias_descanso'] = array_map(
                static fn (mixed $day): string => str((string) $day)->trim()->lower()->toString(),
                $data['dias_descanso'],
            );
        } elseif ($this->boolean('dias_descanso_present')) {
            $normalized['dias_descanso'] = [];
        }

        $documentos = $this->input('documentos');

        if (is_array($documentos)) {
            $normalized['documentos'] = array_map(static function (mixed $documento): mixed {
                if (! is_array($documento)) {
                    return $documento;
                }

                if (($documento['vence_el'] ?? null) === '') {
                    $documento['vence_el'] = null;
                }

                return $documento;
            }, $documentos);
        }

        if ($this->isCreating()) {
            $normalized['dias_vacaciones'] = 2;

            if (! array_key_exists('periodo_prueba_meses', $data)) {
                $normalized['periodo_prueba_meses'] = 3;
            }
        }

        $this->merge($normalized);
    }

    private function uniqueRule(string $column): Unique
    {
        $rule = Rule::unique(Empleado::class, $column);
        $empleado = $this->boundEmpleado();

        if ($empleado !== null) {
            $rule->ignore($empleado);
        }

        return $rule;
    }

    private function validateDocuments(Validator $validator): void
    {
        $documentos = $this->all()['documentos'] ?? [];

        if (! is_array($documentos)) {
            $documentos = [];
        }

        $typeIds = collect($documentos)
            ->filter(fn (mixed $documento): bool => is_array($documento))
            ->pluck('tipo_documento_empleado_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $types = TipoDocumentoEmpleado::query()
            ->where(function ($query) use ($typeIds): void {
                $query->where('activo', true);

                if ($typeIds->isNotEmpty()) {
                    $query->orWhereIn('id', $typeIds);
                }
            })
            ->get(['id', 'nombre', 'documentos_aceptados', 'es_renovable', 'activo'])
            ->keyBy('id');

        foreach ($documentos as $index => $documento) {
            if (! is_array($documento)) {
                continue;
            }

            $type = $types->get((int) ($documento['tipo_documento_empleado_id'] ?? 0));

            if ($type === null) {
                continue;
            }

            if ($type->es_renovable && blank($documento['vence_el'] ?? null)) {
                $validator->errors()->add(
                    "documentos.{$index}.vence_el",
                    "Debes indicar la fecha de vencimiento para el tipo {$type->nombre}.",
                );
            }

            if (! ($documento['archivo'] ?? null) instanceof UploadedFile) {
                continue;
            }

            $configuredExtensions = $type->getAttribute('documentos_aceptados');

            if (! is_array($configuredExtensions)) {
                $configuredExtensions = [];
            }

            $allowedExtensions = collect($configuredExtensions)
                ->map(fn (mixed $extension): string => str((string) $extension)->trim()->lower()->toString())
                ->filter(
                    static fn (string $extension): bool => in_array(
                        $extension,
                        self::DOCUMENT_EXTENSIONS,
                        true,
                    ),
                )
                ->unique()
                ->values();

            if ($allowedExtensions->isEmpty()) {
                $validator->errors()->add(
                    "documentos.{$index}.archivo",
                    "El tipo {$type->nombre} no tiene extensiones de archivo válidas configuradas.",
                );

                continue;
            }

            $fileValidator = ValidatorFacade::make(
                ['archivo' => $documento['archivo']],
                ['archivo' => ['file', 'mimes:'.$allowedExtensions->implode(',')]],
            );

            if ($fileValidator->fails()) {
                $validator->errors()->add(
                    "documentos.{$index}.archivo",
                    'El archivo no corresponde con las extensiones permitidas para este tipo de documento.',
                );
            }
        }

        $activeTypes = $types->where('activo', true);
        $documentosByType = collect($documentos)
            ->filter(fn (mixed $documento): bool => is_array($documento))
            ->keyBy(fn (array $documento): int => (int) ($documento['tipo_documento_empleado_id'] ?? 0));
        $empleado = $this->boundEmpleado();
        $existingTypeIds = $empleado === null
            ? collect()
            : $empleado->documentos()
                ->whereIn('tipo_documento_empleado_id', $activeTypes->keys())
                ->pluck('tipo_documento_empleado_id');

        foreach ($activeTypes as $type) {
            $documento = $documentosByType->get($type->id);
            $hasNewFile = is_array($documento)
                && ($documento['archivo'] ?? null) instanceof UploadedFile;

            if (! $hasNewFile && ! $existingTypeIds->contains($type->id)) {
                $validator->errors()->add(
                    'documentos',
                    "Debes adjuntar un archivo para el tipo de documento activo: {$type->nombre}.",
                );
            }
        }
    }

    private function nullableTrimmedString(mixed $value, bool $lowercase = false): ?string
    {
        $normalized = str((string) $value)->trim();

        if ($lowercase) {
            $normalized = $normalized->lower();
        }

        $result = $normalized->toString();

        return $result === '' ? null : $result;
    }

    private function nullableUppercaseString(mixed $value): ?string
    {
        $normalized = str((string) $value)->trim()->upper()->toString();

        return $normalized === '' ? null : $normalized;
    }
}
