<?php

namespace App\Http\Requests\FaltasReglamento;

use App\Models\FaltasReglamento\FaltaReglamentoCatalogo;
use App\Models\FaltasReglamento\TipoFaltaReglamento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CambiarPaginaCatalogoFaltaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('viewAny', TipoFaltaReglamento::class) ?? false)
            || ($this->user()?->can('viewAny', FaltaReglamentoCatalogo::class) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['page' => ['required', 'integer', 'min:1', 'max:10000']];
    }
}
