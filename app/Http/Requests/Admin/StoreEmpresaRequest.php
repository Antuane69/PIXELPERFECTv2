<?php

namespace App\Http\Requests\Admin;

use App\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreEmpresaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Empresa::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre_legal' => ['required', 'string', 'max:180'],
            'nombre_comercial' => ['nullable', 'string', 'max:180'],
            'grupo_empresarial_id' => [
                'nullable',
                'integer',
                Rule::exists((new GrupoEmpresarial)->getTable(), 'id')->where('activo', true),
            ],
            'rfc' => ['nullable', 'string', 'between:12,13', Rule::unique((new Empresa)->getTable(), 'rfc')],
            'correo_contacto' => ['nullable', 'email:rfc', 'max:180'],
            'telefono_contacto' => ['nullable', 'string', 'max:30'],
            'zona_horaria' => ['required', 'timezone'],
            'moneda' => ['required', 'string', 'size:3'],
            'estado' => ['required', Rule::enum(EstadoEmpresa::class)],
            'demo_ends_at' => [
                'nullable',
                Rule::requiredIf($this->string('estado')->toString() === EstadoEmpresa::Demo->value),
                'date',
                'after:now',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre_legal' => Str::squish($this->string('nombre_legal')->toString()),
            'nombre_comercial' => $this->filled('nombre_comercial')
                ? Str::squish($this->string('nombre_comercial')->toString())
                : null,
            'rfc' => $this->filled('rfc') ? Str::upper(trim($this->string('rfc')->toString())) : null,
            'correo_contacto' => $this->filled('correo_contacto')
                ? Str::lower(trim($this->string('correo_contacto')->toString()))
                : null,
            'telefono_contacto' => $this->filled('telefono_contacto')
                ? trim($this->string('telefono_contacto')->toString())
                : null,
            'zona_horaria' => $this->filled('zona_horaria')
                ? trim($this->string('zona_horaria')->toString())
                : 'America/Mexico_City',
            'moneda' => $this->filled('moneda')
                ? Str::upper(trim($this->string('moneda')->toString()))
                : 'MXN',
            'estado' => $this->filled('estado')
                ? Str::upper(trim($this->string('estado')->toString()))
                : EstadoEmpresa::Prospecto->value,
        ]);
    }

    public function nombreLegal(): string
    {
        return $this->string('nombre_legal')->toString();
    }

    public function nombreComercial(): ?string
    {
        return $this->nullableString('nombre_comercial');
    }

    public function grupoEmpresarialId(): ?int
    {
        $value = $this->validated('grupo_empresarial_id');

        return $value !== null ? (int) $value : null;
    }

    public function rfc(): ?string
    {
        return $this->nullableString('rfc');
    }

    public function correoContacto(): ?string
    {
        return $this->nullableString('correo_contacto');
    }

    public function telefonoContacto(): ?string
    {
        return $this->nullableString('telefono_contacto');
    }

    public function zonaHoraria(): string
    {
        return $this->string('zona_horaria')->toString();
    }

    public function moneda(): string
    {
        return $this->string('moneda')->toString();
    }

    public function estado(): EstadoEmpresa
    {
        return EstadoEmpresa::from($this->string('estado')->toString());
    }

    public function demoEndsAt(): ?CarbonInterface
    {
        return $this->date('demo_ends_at');
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }
}
