<?php

namespace App\Http\Requests\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && ($this->user()?->can('update', $user) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;
        $canEditIdentity = $this->user()->es_superadministrador_plataforma;

        return [
            'name' => $canEditIdentity
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'string', Rule::in([$user->name])],
            'email' => $canEditIdentity ? [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user),
            ] : ['sometimes', 'string', Rule::in([$user->email])],
            'password' => $canEditIdentity
                ? ['nullable', 'string', Password::defaults(), 'confirmed']
                : ['prohibited'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Role::class, 'id')
                    ->where('guard_name', 'web')
                    ->where('empresa_id', $empresaId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('name')) {
            $this->merge(['name' => $this->string('name')->squish()->toString()]);
        }

        if ($this->exists('email')) {
            $this->merge(['email' => $this->string('email')->trim()->lower()->toString()]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.in' => 'El nombre debe actualizarlo el titular desde su perfil o un superadministrador de plataforma.',
            'email.in' => 'El correo debe actualizarlo el titular desde su perfil o un superadministrador de plataforma.',
            'password.prohibited' => 'La contraseña debe actualizarla el titular desde su perfil o un superadministrador de plataforma.',
        ];
    }

    /**
     * @return array<int, int|string>|null
     */
    public function validatedRoleIds(): ?array
    {
        if (! $this->exists('roles')) {
            return null;
        }

        $roles = $this->validated('roles', []);

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter(
            $roles,
            static fn (mixed $roleId): bool => is_int($roleId) || is_string($roleId),
        ));
    }
}
