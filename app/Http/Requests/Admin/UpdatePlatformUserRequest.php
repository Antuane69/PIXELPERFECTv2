<?php

namespace App\Http\Requests\Admin;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdatePlatformUserRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
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
            'empresa_ids' => $user->es_superadministrador_plataforma
                ? ['sometimes', 'array']
                : ['required', 'array', 'min:1'],
            'empresa_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Empresa::class, 'id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user),
            ],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'name' => str((string) $this->input('name'))->squish()->toString(),
            'email' => str((string) $this->input('email'))->trim()->lower()->toString(),
        ];

        if ($this->has('empresa_ids')) {
            $data['empresa_ids'] = is_array($this->input('empresa_ids'))
                ? array_values(array_map(
                    static fn (mixed $id): mixed => is_string($id) && ctype_digit($id) ? (int) $id : $id,
                    $this->input('empresa_ids'),
                ))
                : $this->input('empresa_ids');
        }

        $this->merge($data);
    }
}
