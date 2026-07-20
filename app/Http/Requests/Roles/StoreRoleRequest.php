<?php

namespace App\Http\Requests\Roles;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Role::class, 'name')->where('guard_name', 'web'),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Permission::class, 'id')->where('guard_name', 'web'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => str((string) $this->input('name'))->squish()->toString(),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($user === null || $user->hasRole('Administrador', 'web')) {
                    return;
                }

                $permissions = $this->input('permissions');

                if (! is_array($permissions)) {
                    return;
                }

                $requestedPermissionIds = collect($permissions)
                    ->filter(static fn (mixed $permissionId): bool => is_int($permissionId) || is_string($permissionId))
                    ->map(static fn (int|string $permissionId): int => (int) $permissionId);
                $allowedPermissionIds = $user->getAllPermissions()->pluck('id');

                if ($requestedPermissionIds->diff($allowedPermissionIds)->isNotEmpty()) {
                    $validator->errors()->add(
                        'permissions',
                        'No puedes conceder permisos que no tienes asignados.',
                    );
                }
            },
        ];
    }
}
