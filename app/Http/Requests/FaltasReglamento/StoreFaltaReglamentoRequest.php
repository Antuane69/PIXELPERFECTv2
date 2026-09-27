<?php

namespace App\Http\Requests\FaltasReglamento;

use App\Models\Empleado;
use App\Models\FaltasReglamento\FaltaReglamento;
use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\FaltasReglamento\TipoFaltaReglamento;
use App\Rules\FaltasReglamento\EvidenciaFaltaReglamentoValida;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class StoreFaltaReglamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FaltaReglamento::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $empresa = app(EmpresaContext::class)->empresaRequerida();
        $canCreateForOthers = $this->user()?->can('faltas_reglamento.create_for_others') ?? false;

        return [
            'empleado_id' => $canCreateForOthers
                ? [
                    'required',
                    'integer',
                    Rule::exists(Empleado::class, 'id')->where('empresa_id', $empresa->id)->whereNull('deleted_at'),
                ]
                : ['prohibited'],
            'tipo_falta_reglamento_id' => [
                'required',
                'integer',
                Rule::exists(TipoFaltaReglamento::class, 'id')
                    ->where('empresa_id', $empresa->id)
                    ->where('activo', true)
                    ->whereNull('deleted_at'),
            ],
            'falta_reglamento_catalogo_id' => [
                'required',
                'integer',
                Rule::exists(FaltaReglamentoCatalogo::class, 'id')
                    ->where('empresa_id', $empresa->id)
                    ->where('activo', true)
                    ->whereNull('deleted_at'),
            ],
            'fecha_ocurrencia' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.now($empresa->zona_horaria)->toDateString(),
            ],
            'comentarios' => ['nullable', 'string', 'max:2000'],
            'archivos' => ['sometimes', 'array'],
            'archivos.*' => ['required', 'file', 'max:10240', new EvidenciaFaltaReglamentoValida],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('tipo_falta_reglamento_id')
                || $validator->errors()->has('falta_reglamento_catalogo_id')) {
                return;
            }

            $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;
            $catalogo = FaltaReglamentoCatalogo::query()
                ->where('empresa_id', $empresaId)
                ->where('tipo_falta_reglamento_id', (int) $this->input('tipo_falta_reglamento_id'))
                ->where('activo', true)
                ->whereNull('deleted_at')
                ->whereKey((int) $this->input('falta_reglamento_catalogo_id'))
                ->whereHas('tipoFalta', static fn ($query) => $query
                    ->where('activo', true)
                    ->whereNull('deleted_at'))
                ->exists();

            if (! $catalogo) {
                $validator->errors()->add('falta_reglamento_catalogo_id', 'Selecciona una falta disponible para el tipo elegido.');
            }
        }];
    }

    /** @return array{empleado_id?: int, falta_reglamento_catalogo_id: int, fecha_ocurrencia: string, comentarios?: string|null, archivos?: list<UploadedFile>} */
    public function faltaData(): array
    {
        $validated = $this->validated();
        $catalogoId = $validated['falta_reglamento_catalogo_id'] ?? null;
        $fecha = $validated['fecha_ocurrencia'] ?? null;

        if ((! is_int($catalogoId) && ! is_string($catalogoId)) || ! is_string($fecha)) {
            throw new LogicException('El reporte validado de falta al reglamento está incompleto.');
        }

        $data = [
            'falta_reglamento_catalogo_id' => (int) $catalogoId,
            'fecha_ocurrencia' => $fecha,
        ];

        if (array_key_exists('empleado_id', $validated)) {
            $empleadoId = $validated['empleado_id'];

            if (! is_int($empleadoId) && ! is_string($empleadoId)) {
                throw new LogicException('El empleado validado del reporte no es válido.');
            }

            $data['empleado_id'] = (int) $empleadoId;
        }

        $comentarios = $validated['comentarios'] ?? null;

        if ($comentarios !== null && ! is_string($comentarios)) {
            throw new LogicException('Los comentarios validados del reporte no son válidos.');
        }

        $data['comentarios'] = $comentarios;
        $files = $validated['archivos'] ?? [];
        $data['archivos'] = array_values(array_filter(
            is_array($files) ? $files : [],
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        return $data;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comentarios'))) {
            $this->merge(['comentarios' => trim($this->input('comentarios')) ?: null]);
        }
    }
}
