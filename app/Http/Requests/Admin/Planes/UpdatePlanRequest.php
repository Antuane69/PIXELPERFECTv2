<?php

namespace App\Http\Requests\Admin\Planes;

use App\IconoPlan;
use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan && ($this->user()?->can('update', $plan) ?? false);
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
                'max:120',
                Rule::unique(Plan::class, 'nombre')->ignore($this->route('plan')),
            ],
            'precio_mensual' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'color' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'icono' => ['required', Rule::enum(IconoPlan::class)],
            'modulos_incluidos' => ['nullable', 'string', 'max:4000'],
            'limite_usuarios' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'periodo_gracia_dias' => ['required', 'integer', 'min:0', 'max:90'],
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

        if (array_key_exists('color', $data) && is_string($data['color'])) {
            $normalized['color'] = mb_strtoupper(trim($data['color']));
        }

        foreach (['modulos_incluidos', 'limite_usuarios'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                $normalized[$field] = $value === '' ? null : $value;
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
