<?php

namespace App\Http\Requests\Empleados;

use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListEmpleadoDocumentoCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('empleados_documentos_catalogo.view') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;

        return [
            'carpeta_id' => [
                'required',
                'integer',
                Rule::exists('empleados_carpetas', 'id')
                    ->where('empresa_id', $empresaId)
                    ->where('activo', true)
                    ->whereNull('deleted_at'),
            ],
            'search' => ['nullable', 'string', 'max:180'],
            'archivados' => ['sometimes', 'boolean'],
            'per_page' => ['required', 'integer', 'between:1,100'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');

        if (is_string($search)) {
            $this->merge(['search' => Str::squish($search)]);
        }
    }

    /** @return array{carpeta_id: int, search: string, archivados: bool, per_page: int, page: int} */
    public function listingData(): array
    {
        $data = $this->validated();

        return [
            'carpeta_id' => (int) $data['carpeta_id'],
            'search' => (string) ($data['search'] ?? ''),
            'archivados' => (bool) ($data['archivados'] ?? false),
            'per_page' => (int) $data['per_page'],
            'page' => (int) $data['page'],
        ];
    }
}
