<?php

namespace App\Http\Requests\Admin;

use App\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\GrupoEmpresarial;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

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
            'rfc' => [
                'required',
                'string',
                'size:12',
                'regex:/\A[A-ZÑ&]{3}[0-9]{6}[A-Z0-9]{3}\z/u',
                Rule::unique((new Empresa)->getTable(), 'rfc'),
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! preg_match('/\A[A-ZÑ&]{3}[0-9]{6}[A-Z0-9]{3}\z/u', $value)) {
                        return;
                    }

                    $date = DateTimeImmutable::createFromFormat('!ymd', substr($value, 3, 6));
                    $dateErrors = DateTimeImmutable::getLastErrors();

                    if ($date === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                        $fail('El RFC debe incluir una fecha válida en formato mexicano.');
                    }
                },
            ],
            'correo_contacto' => ['nullable', 'email:rfc', 'max:180'],
            'codigo_pais_contacto' => [
                'nullable',
                'string',
                'regex:/\A[0-9]{1,3}\z/',
                Rule::requiredIf(fn (): bool => $this->filled('telefono_contacto')),
            ],
            'telefono_contacto' => [
                'nullable',
                'string',
                'regex:/\A[0-9]{7,15}\z/',
                Rule::requiredIf(fn (): bool => $this->filled('codigo_pais_contacto')),
            ],
            'logo' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb'),
            ],
            'zona_horaria' => ['required', 'timezone'],
            'moneda' => ['required', 'string', 'size:3'],
            'estado' => [
                'required',
                Rule::in([
                    EstadoEmpresa::Prospecto->value,
                    EstadoEmpresa::Demo->value,
                    EstadoEmpresa::Activa->value,
                ]),
            ],
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
            'codigo_pais_contacto' => $this->filled('codigo_pais_contacto')
                ? trim($this->string('codigo_pais_contacto')->toString())
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

    public function codigoPaisContacto(): ?string
    {
        return $this->nullableString('codigo_pais_contacto');
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
