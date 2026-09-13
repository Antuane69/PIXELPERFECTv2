<?php

namespace App\Http\Requests\Admin\Permissions;

use App\AlcancePermiso;
use App\Models\Modulo;
use App\Models\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Permission::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:125',
                'regex:/^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/',
                Rule::unique(Permission::class, 'name')->where('guard_name', 'web'),
            ],
            'alcance' => ['required', Rule::enum(AlcancePermiso::class)],
            'modulo_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => $this->input('alcance') === AlcancePermiso::Empresa->value),
                Rule::prohibitedIf(fn (): bool => $this->input('alcance') === AlcancePermiso::Plataforma->value),
                Rule::exists(Modulo::class, 'id'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $normalized = [];

        if (array_key_exists('name', $data)) {
            $normalized['name'] = str((string) $data['name'])->trim()->lower()->toString();
        }

        if (array_key_exists('alcance', $data)) {
            $normalized['alcance'] = str((string) $data['alcance'])->trim()->upper()->toString();
        }

        if (array_key_exists('modulo_id', $data) && $data['modulo_id'] === '') {
            $normalized['modulo_id'] = null;
        }

        $this->merge($normalized);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.regex' => 'Usa formato recurso.accion con minúsculas, números o guion bajo.',
            'modulo_id.required' => 'Selecciona módulo para permiso empresarial.',
            'modulo_id.prohibited' => 'Permiso de plataforma no puede pertenecer a módulo empresarial.',
        ];
    }
}
