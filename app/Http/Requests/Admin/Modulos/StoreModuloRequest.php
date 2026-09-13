<?php

namespace App\Http\Requests\Admin\Modulos;

use App\Models\Modulo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModuloRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Modulo::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'clave' => [
                'required',
                'string',
                'min:2',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Modulo::class, 'clave'),
            ],
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $normalized = [];

        if (array_key_exists('clave', $data)) {
            $normalized['clave'] = str((string) $data['clave'])->trim()->lower()->toString();
        }

        if (array_key_exists('nombre', $data)) {
            $normalized['nombre'] = str((string) $data['nombre'])->squish()->toString();
        }

        if (array_key_exists('descripcion', $data)) {
            $description = str((string) $data['descripcion'])->squish()->toString();
            $normalized['descripcion'] = $description === '' ? null : $description;
        }

        if (array_key_exists('activo', $data)) {
            $normalized['activo'] = filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ?? $data['activo'];
        }

        $this->merge($normalized);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'clave.regex' => 'La clave sólo puede contener minúsculas, números y guiones.',
        ];
    }
}
