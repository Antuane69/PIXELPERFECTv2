<?php

namespace App\Http\Requests\FaltasReglamento;

use App\Models\FaltasReglamento\FaltaReglamento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RechazarFaltaReglamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $falta = $this->route('faltaReglamento');

        return $falta instanceof FaltaReglamento && ($this->user()?->can('review', $falta) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['comentarios_rechazo' => ['required', 'string', 'min:3', 'max:2000']];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comentarios_rechazo'))) {
            $this->merge(['comentarios_rechazo' => trim($this->input('comentarios_rechazo'))]);
        }
    }
}
