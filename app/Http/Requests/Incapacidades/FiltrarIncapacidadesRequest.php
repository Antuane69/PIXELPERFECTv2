<?php

namespace App\Http\Requests\Incapacidades;

use App\Enums\Incapacidades\EstadoIncapacidad;
use App\Models\Incapacidad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarIncapacidadesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Incapacidad::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::enum(EstadoIncapacidad::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }

        if (is_string($this->input('estado'))) {
            $this->merge(['estado' => strtoupper(trim($this->input('estado')))]);
        }
    }
}
