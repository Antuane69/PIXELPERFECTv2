<?php

namespace App\Http\Requests\Admin;

use App\Models\Empresa;
use App\Models\Modulo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpresaModulosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $empresa = $this->route('empresa');

        return $empresa instanceof Empresa
            && ($this->user()?->can('update', $empresa) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'modulos' => ['present', 'array'],
            'modulos.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Modulo::class, 'id')->where('activo', true),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('modulos')) {
            $this->merge(['modulos' => []]);
        }
    }
}
