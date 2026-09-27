<?php

namespace App\Http\Requests\Vacaciones;

use App\EstadoVacacion;
use App\Models\Vacacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarVacacionesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Vacacion::class) ?? false;
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
            'estado' => ['nullable', Rule::in(array_column(EstadoVacacion::cases(), 'value'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');
        $estado = $this->input('estado');

        $this->merge([
            'search' => is_string($search) ? trim($search) : $search,
            'estado' => $estado === '' ? null : $estado,
        ]);
    }
}
