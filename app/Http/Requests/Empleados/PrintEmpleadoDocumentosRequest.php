<?php

namespace App\Http\Requests\Empleados;

use App\Models\Empleado;
use App\Models\EmpleadoDocumentoCatalogo;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PrintEmpleadoDocumentosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('empleado');
        $user = $this->user();

        return $employee instanceof Empleado
            && $user instanceof User
            && $user->can('view', $employee)
            && $user->can('empleados_documentos_catalogo.view');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;

        return [
            'documento_ids' => ['required', 'array', 'min:1', 'max:25'],
            'documento_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('empleados_documentos_catalogo', 'id')
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $rawIds = $this->input('documento_ids');
            $user = $this->user();

            if ($validator->errors()->has('documento_ids')
                || ! is_array($rawIds)
                || ! $user instanceof User) {
                return;
            }

            foreach (array_keys($rawIds) as $index) {
                if ($validator->errors()->has("documento_ids.{$index}")) {
                    return;
                }
            }

            $ids = array_map(static fn (mixed $id): int => (int) $id, $rawIds);
            $empresa = app(EmpresaContext::class)->empresaRequerida();
            $accessibleCount = EmpleadoDocumentoCatalogo::query()
                ->visiblesPara($empresa, $user)
                ->whereIn('id', $ids)
                ->count();

            if ($accessibleCount !== count($ids)) {
                $validator->errors()->add('documento_ids', 'Uno o más documentos no están disponibles en tus carpetas.');
            }
        }];
    }
}
