<?php

namespace App\Http\Requests\FaltasReglamento;

use App\Models\FaltasReglamento\FaltaReglamento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CambiarPaginaFaltasReglamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FaltaReglamento::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['page' => ['required', 'integer', 'min:1', 'max:10000']];
    }
}
