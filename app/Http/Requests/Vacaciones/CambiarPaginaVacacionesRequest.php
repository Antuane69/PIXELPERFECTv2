<?php

namespace App\Http\Requests\Vacaciones;

use App\Models\DiaFestivo;
use App\Models\Vacacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CambiarPaginaVacacionesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->input('listado') === 'festivos') {
            return $this->user()?->can('viewAny', DiaFestivo::class) ?? false;
        }

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
            'page' => ['required', 'integer', 'min:1', 'max:100000'],
            'listado' => ['sometimes', 'string', 'in:solicitudes,proximas,festivos'],
        ];
    }
}
