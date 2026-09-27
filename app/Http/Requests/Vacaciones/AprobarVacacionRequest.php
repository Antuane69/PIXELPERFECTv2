<?php

namespace App\Http\Requests\Vacaciones;

use App\Models\Vacacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AprobarVacacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $vacacion = $this->route('vacacion');

        return $vacacion instanceof Vacacion
            && ($this->user()?->can('review', $vacacion) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
