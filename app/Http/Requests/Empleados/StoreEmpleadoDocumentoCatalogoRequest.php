<?php

namespace App\Http\Requests\Empleados;

use App\Models\EmpleadoCarpeta;
use App\Models\User;
use App\Services\Empleados\EmpleadoDocumentoVariables;
use App\Services\Empleados\ModulosRelacionablesDocumentoCatalogo;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmpleadoDocumentoCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('empleados_documentos_catalogo.create') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $empresa = app(EmpresaContext::class)->empresaRequerida();
        $empresaId = $empresa->id;
        $user = $this->user();
        $availableModuleIds = $user instanceof User
            ? app(ModulosRelacionablesDocumentoCatalogo::class)->query($user, $empresa)->pluck('modulos.id')->all()
            : [];

        return [
            'nombre' => ['required', 'string', 'max:180'],
            'empleado_carpeta_id' => [
                'required',
                'integer',
                Rule::exists('empleados_carpetas', 'id')
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at'),
            ],
            'modulo_ids' => ['present', 'array'],
            'modulo_ids.*' => ['required', 'integer', 'distinct', Rule::in($availableModuleIds)],
            'contenido_html' => ['required', 'string', 'max:200000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'modulo_ids.*.in' => 'Selecciona solo módulos habilitados a los que tengas acceso.',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateFolderAccess($validator);
            $this->validateVariables($validator);
        }];
    }

    /** @return array{nombre: string, empleado_carpeta_id: int, contenido_html: string, modulo_ids: list<int>} */
    public function documentoData(): array
    {
        $data = $this->validated();

        return [
            'nombre' => (string) $data['nombre'],
            'empleado_carpeta_id' => (int) $data['empleado_carpeta_id'],
            'contenido_html' => (string) $data['contenido_html'],
            'modulo_ids' => array_values(array_map('intval', $data['modulo_ids'])),
        ];
    }

    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');

        $normalized = [];

        if (is_string($nombre)) {
            $normalized['nombre'] = Str::squish($nombre);
        }

        if (! $this->has('modulo_ids')) {
            $normalized['modulo_ids'] = [];
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    private function validateFolderAccess(Validator $validator): void
    {
        if ($validator->errors()->has('empleado_carpeta_id')) {
            return;
        }

        $user = $this->user();
        $empresa = app(EmpresaContext::class)->empresaRequerida();

        if (! $user instanceof User || ! is_numeric($this->input('empleado_carpeta_id'))) {
            return;
        }

        $canAccess = EmpleadoCarpeta::query()
            ->whereBelongsTo($empresa)
            ->visiblesPara($user)
            ->whereKey((int) $this->input('empleado_carpeta_id'))
            ->exists();

        if (! $canAccess) {
            $validator->errors()->add('empleado_carpeta_id', 'Selecciona una carpeta vigente a la que tengas acceso.');
        }
    }

    private function validateVariables(Validator $validator): void
    {
        if ($validator->errors()->has('contenido_html')) {
            return;
        }

        $contenido = $this->input('contenido_html');

        if (! is_string($contenido)) {
            return;
        }

        $unknown = app(EmpleadoDocumentoVariables::class)->unknownVariables($contenido);

        if ($unknown !== []) {
            $validator->errors()->add(
                'contenido_html',
                'Hay variables no disponibles: '.implode(', ', array_map(static fn (string $name): string => '{{'.$name.'}}', $unknown)).'.',
            );
        }
    }
}
