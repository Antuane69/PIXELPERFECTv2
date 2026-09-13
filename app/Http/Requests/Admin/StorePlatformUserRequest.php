<?php

namespace App\Http\Requests\Admin;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StorePlatformUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->es_superadministrador_plataforma;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'empresa_ids' => ['required', 'array', 'min:1'],
            'empresa_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Empresa::class, 'id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $empresaIds = $this->input('empresa_ids');

        if (! is_array($empresaIds) && $this->filled('empresa_id')) {
            $empresaIds = [$this->input('empresa_id')];
        }

        $this->merge([
            'empresa_ids' => is_array($empresaIds)
                ? array_values(array_map(
                    static fn (mixed $id): mixed => is_string($id) && ctype_digit($id) ? (int) $id : $id,
                    $empresaIds,
                ))
                : $empresaIds,
            'name' => str((string) $this->input('name'))->squish()->toString(),
            'email' => str((string) $this->input('email'))->trim()->lower()->toString(),
        ]);
    }
}
