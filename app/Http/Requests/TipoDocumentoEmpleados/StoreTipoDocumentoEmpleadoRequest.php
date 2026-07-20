<?php

namespace App\Http\Requests\TipoDocumentoEmpleados;

use App\Models\TipoDocumentoEmpleado;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTipoDocumentoEmpleadoRequest extends FormRequest
{
    private const DOCUMENT_EXTENSIONS = [
        'PDF',
        'JPG',
        'JPEG',
        'PNG',
        'WEBP',
        'DOC',
        'DOCX',
        'XLS',
        'XLSX',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', TipoDocumentoEmpleado::class) ?? false;
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
                Rule::unique(TipoDocumentoEmpleado::class, 'nombre'),
            ],
            'es_renovable' => ['sometimes', 'boolean'],
            'frecuencia_cantidad' => [
                Rule::requiredIf(fn (): bool => $this->boolean('es_renovable')),
                'nullable',
                'integer',
                'min:1',
                'max:65535',
            ],
            'frecuencia_tipo' => [
                Rule::requiredIf(fn (): bool => $this->boolean('es_renovable')),
                'nullable',
                'string',
                Rule::in(['dias', 'semanas', 'meses', 'anios']),
            ],
            'documentos_aceptados' => ['required', 'array', 'min:1', 'max:9'],
            'documentos_aceptados.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(self::DOCUMENT_EXTENSIONS),
            ],
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

        foreach (['es_renovable', 'activo'] as $field) {
            if (array_key_exists($field, $data)) {
                $normalized[$field] = $this->normalizedBoolean($data[$field]);
            }
        }

        if (isset($data['documentos_aceptados']) && is_array($data['documentos_aceptados'])) {
            $normalized['documentos_aceptados'] = array_map(
                static fn (mixed $extension): string => str((string) $extension)->trim()->upper()->toString(),
                $data['documentos_aceptados'],
            );
        }

        if (array_key_exists('frecuencia_tipo', $data)) {
            $normalized['frecuencia_tipo'] = $this->nullableLowercaseString($data['frecuencia_tipo']);
        }

        if (array_key_exists('frecuencia_cantidad', $data) && $data['frecuencia_cantidad'] === '') {
            $normalized['frecuencia_cantidad'] = null;
        }

        if (($normalized['es_renovable'] ?? null) === false) {
            $normalized['frecuencia_cantidad'] = null;
            $normalized['frecuencia_tipo'] = null;
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

    private function nullableLowercaseString(mixed $value): ?string
    {
        $normalized = str((string) $value)->trim()->lower()->toString();

        return $normalized === '' ? null : $normalized;
    }
}
