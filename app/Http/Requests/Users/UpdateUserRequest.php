<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

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

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user),
            ],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Role::class, 'id')->where('guard_name', 'web'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => str((string) $this->input('name'))->squish()->toString(),
            'email' => str((string) $this->input('email'))->trim()->lower()->toString(),
        ]);
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
