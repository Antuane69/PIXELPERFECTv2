<?php

namespace App\Http\Requests\FaltasReglamento;

use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateFaltaReglamentoCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $catalogo = $this->route('faltaReglamentoCatalogo');

        return $catalogo instanceof FaltaReglamentoCatalogo
            && ($this->user()?->can('update', $catalogo) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;
        $catalogo = $this->route('faltaReglamentoCatalogo');
        $tipoFaltaReglamentoId = $catalogo instanceof FaltaReglamentoCatalogo
            ? $catalogo->tipo_falta_reglamento_id
            : null;

        return [
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:180',
                Rule::unique(FaltaReglamentoCatalogo::class, 'nombre')
                    ->where('empresa_id', $empresaId)
                    ->where('tipo_falta_reglamento_id', $tipoFaltaReglamentoId)
                    ->ignore($catalogo),
            ],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['required', 'boolean'],
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
