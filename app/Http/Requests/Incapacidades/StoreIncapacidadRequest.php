<?php

namespace App\Http\Requests\Incapacidades;

use App\Models\Empleado;
use App\Models\Incapacidad;
use App\Services\Empresas\EmpresaContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use LogicException;

class StoreIncapacidadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Incapacidad::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Closure|ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $empresa = app(EmpresaContext::class)->empresaRequerida();
        $puedeElegirEmpleado = $this->user()?->can('incapacidades.create_for_others') ?? false;

        return [
            'empleado_id' => $puedeElegirEmpleado
                ? [
                    'required',
                    'integer',
                    Rule::exists(Empleado::class, 'id')->where(static fn ($query) => $query
                        ->where('empresa_id', $empresa->id)
                        ->whereNull('deleted_at')),
                ]
                : ['prohibited'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'motivo' => ['required', 'string', 'min:3', 'max:2000'],
            'archivo' => [
                'nullable',
                'file',
                'max:10240',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $mimeType = $value->getMimeType();
                    $extension = strtolower($value->getClientOriginalExtension());

                    if (is_string($mimeType) && str_starts_with($mimeType, 'image/')) {
                        return;
                    }

                    $documentMimeTypes = [
                        'doc' => ['application/msword', 'application/x-ole-storage'],
                        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                        'pdf' => ['application/pdf'],
                    ];

                    if (in_array($mimeType, $documentMimeTypes[$extension] ?? [], true)) {
                        return;
                    }

                    $fail('Adjunta una imagen, un documento Word o un PDF válido.');
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('motivo'))) {
            $this->merge(['motivo' => trim($this->input('motivo'))]);
        }
    }

    /**
     * @return array{empleado_id?: int, fecha_inicio: string, fecha_fin: string, motivo: string, archivo?: UploadedFile}
     */
    public function incapacidadData(): array
    {
        $validated = $this->validated();
        $fechaInicio = $validated['fecha_inicio'] ?? null;
        $fechaFin = $validated['fecha_fin'] ?? null;
        $motivo = $validated['motivo'] ?? null;

        if (! is_string($fechaInicio) || ! is_string($fechaFin) || ! is_string($motivo)) {
            throw new LogicException('La solicitud validada de incapacidad está incompleta.');
        }

        $data = [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'motivo' => $motivo,
        ];

        if (array_key_exists('empleado_id', $validated)) {
            $empleadoId = $validated['empleado_id'];

            if (! is_int($empleadoId) && ! is_string($empleadoId)) {
                throw new LogicException('El empleado validado de la incapacidad no es válido.');
            }

            $data['empleado_id'] = (int) $empleadoId;
        }

        if (array_key_exists('archivo', $validated) && $validated['archivo'] !== null) {
            $archivo = $validated['archivo'];

            if (! $archivo instanceof UploadedFile) {
                throw new LogicException('El justificante validado no es un archivo válido.');
            }

            $data['archivo'] = $archivo;
        }

        return $data;
    }
}
