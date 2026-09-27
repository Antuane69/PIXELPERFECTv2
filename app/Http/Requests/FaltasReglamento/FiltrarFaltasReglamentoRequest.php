<?php

namespace App\Http\Requests\FaltasReglamento;

use App\Models\Empleado;
use App\Models\FaltasReglamento\FaltaReglamento;
use App\Models\FaltasReglamento\TipoFaltaReglamento;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FiltrarFaltasReglamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FaltaReglamento::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;

        return [
            'search' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(['PENDIENTE', 'AUTORIZADA', 'RECHAZADA'])],
            'empleado_id' => ['nullable', 'integer', Rule::exists(Empleado::class, 'id')->where('empresa_id', $empresaId)],
            'tipo_falta_reglamento_id' => ['nullable', 'integer', Rule::exists(TipoFaltaReglamento::class, 'id')->where('empresa_id', $empresaId)],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => Str::squish($this->input('search'))]);
        }
    }
}
