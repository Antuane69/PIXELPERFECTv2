<?php

namespace App\Http\Requests\Incapacidades;

use App\Models\Incapacidad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RechazarIncapacidadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $incapacidad = $this->route('incapacidad');

        return $incapacidad instanceof Incapacidad
            && ($this->user()?->can('review', $incapacidad) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comentarios_rechazo' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comentarios_rechazo'))) {
            $this->merge(['comentarios_rechazo' => trim($this->input('comentarios_rechazo'))]);
        }
    }
}
