<?php

namespace App\Http\Requests\FaltasReglamento;

use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\FaltasReglamento\TipoFaltaReglamento;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreFaltaReglamentoCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FaltaReglamentoCatalogo::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;
        $tipoId = $this->input('tipo_falta_reglamento_id');

        return [
            'tipo_falta_reglamento_id' => [
                'required',
                'integer',
                Rule::exists(TipoFaltaReglamento::class, 'id')->where('empresa_id', $empresaId)->where('activo', true)->whereNull('deleted_at'),
            ],
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:180',
                Rule::unique(FaltaReglamentoCatalogo::class, 'nombre')
                    ->where('empresa_id', $empresaId)
                    ->where('tipo_falta_reglamento_id', $tipoId),
            ],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('nombre'))) {
            $normalized['nombre'] = Str::squish($this->input('nombre'));
        }

        if (is_string($this->input('descripcion'))) {
            $normalized['descripcion'] = trim($this->input('descripcion')) ?: null;
        }

        $this->merge($normalized);
    }
}
