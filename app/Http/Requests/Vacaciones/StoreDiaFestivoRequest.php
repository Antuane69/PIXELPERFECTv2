<?php

namespace App\Http\Requests\Vacaciones;

use App\Models\DiaFestivo;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiaFestivoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', DiaFestivo::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;

        return [
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'fecha' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique(DiaFestivo::class, 'fecha')->where('empresa_id', $empresaId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => str($this->input('nombre'))->squish()->toString()]);
        }
    }
}
