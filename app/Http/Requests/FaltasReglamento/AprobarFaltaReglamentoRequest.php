<?php

namespace App\Http\Requests\FaltasReglamento;

use App\Models\FaltasReglamento\FaltaReglamento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AprobarFaltaReglamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $falta = $this->route('faltaReglamento');

        return $falta instanceof FaltaReglamento && ($this->user()?->can('review', $falta) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [];
    }
}
