<?php

namespace App\Http\Requests\Puestos;

use App\Models\Puesto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePuestoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Puesto::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:255',
                Rule::unique(Puesto::class, 'nombre'),
            ],
            'salario_dia' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'salario_quincena' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $normalized = [];

        if (array_key_exists('nombre', $data)) {
            $normalized['nombre'] = str($data['nombre'])->squish()->toString();
        }

        foreach (['salario_dia', 'salario_quincena'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $normalized[$field] = null;
            }
        }

        if (array_key_exists('activo', $data)) {
            $normalized['activo'] = $this->normalizedBoolean($data['activo']);
        }

        $this->merge($normalized);
    }

    private function normalizedBoolean(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }
}
