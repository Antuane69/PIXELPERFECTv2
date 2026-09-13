<?php

namespace App\Http\Requests\Roles;

use App\AlcancePermiso;
use App\Models\Modulo;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && ($this->user()?->can('update', $role) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');
        $empresaId = app(EmpresaContext::class)->empresaRequerida()->id;
        $enabledModuleIds = $this->user()?->es_superadministrador_plataforma
            ? Modulo::query()->where('activo', true)->pluck('id')
            : Modulo::query()
                ->where('activo', true)
                ->whereHas('empresas', fn ($query) => $query
                    ->where('empresas.id', $empresaId)
                    ->where('empresa_modulo.habilitado', true))
                ->pluck('id');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Role::class, 'name')
                    ->where('guard_name', 'web')
                    ->where('empresa_id', $empresaId)
                    ->ignore($role),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Permission::class, 'id')->where(
                    fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('alcance', AlcancePermiso::Empresa)
                        ->whereIn('modulo_id', $enabledModuleIds),
                ),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $role = $this->route('role');

                if ($role instanceof Role && $role->name === 'Administrador') {
                    $validator->errors()->add(
                        'role',
                        'El rol Administrador no se puede modificar.',
                    );
                }

                $user = $this->user();

                if ($user === null || $user->es_superadministrador_plataforma || $user->hasRole('Administrador', 'web')) {
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => str((string) $this->input('name'))->squish()->toString(),
        ]);
    }
}
