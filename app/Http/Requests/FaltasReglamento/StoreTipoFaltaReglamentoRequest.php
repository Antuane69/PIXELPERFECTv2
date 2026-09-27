<?php

namespace App\Http\Requests\FaltasReglamento;

use App\Models\FaltasReglamento\TipoFaltaReglamento;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTipoFaltaReglamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TipoFaltaReglamento::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;

        return [
            'nombre' => ['required', 'string', 'min:2', 'max:120', Rule::unique(TipoFaltaReglamento::class, 'nombre')->where('empresa_id', $empresaId)],
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
