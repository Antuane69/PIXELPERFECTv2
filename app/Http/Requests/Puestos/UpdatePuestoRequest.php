<?php

namespace App\Http\Requests\Puestos;

use App\Models\Puesto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePuestoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $puesto = $this->route('puesto');

        return $puesto instanceof Puesto
            && ($this->user()?->can('update', $puesto) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $puesto = $this->route('puesto');
        $uniqueName = Rule::unique(Puesto::class, 'nombre');

        if ($puesto instanceof Puesto) {
            $uniqueName->ignore($puesto);
        }

        return [
            'nombre' => ['sometimes', 'required', 'string', 'min:2', 'max:255', $uniqueName],
            'salario_dia' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'salario_quincena' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
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
