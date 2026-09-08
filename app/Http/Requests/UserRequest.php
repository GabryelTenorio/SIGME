<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $managedUser = $this->route('user');

        return $managedUser
            ? $this->user()->can('update', $managedUser)
            : $this->user()->can('create', User::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $managedUser = $this->route('user');

        return [
            'organization_id' => ['required', 'integer', Rule::exists(Organization::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($managedUser)],
            'password' => [$managedUser ? 'nullable' : 'required', 'string', 'min:12'],
            'is_active' => ['required', 'boolean'],
            'school_ids' => ['array'],
            'school_ids.*' => ['integer', 'distinct', Rule::exists(School::class, 'id')],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists(Role::class, 'id')],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $organizationId = (int) $this->input('organization_id');
            $schoolIds = collect($this->input('school_ids', []))->map(fn ($id) => (int) $id);
            $roles = Role::query()->whereIn('id', $this->input('role_ids', []))->get();

            if (! $this->user()->is_platform_admin && $organizationId !== $this->user()->organization_id) {
                $validator->errors()->add('organization_id', 'A organização selecionada está fora do seu acesso.');
            }

            if (School::query()->whereIn('id', $schoolIds)->where('organization_id', '!=', $organizationId)->exists()) {
                $validator->errors()->add('school_ids', 'Todas as escolas devem pertencer à organização selecionada.');
            }

            if ($roles->contains(fn (Role $role) => $role->organization_id !== $organizationId)) {
                $validator->errors()->add('role_ids', 'Todos os perfis devem pertencer à organização selecionada.');
            }

            if ($roles->contains(fn (Role $role) => ! $role->is_system || ! array_key_exists($role->slug, AccessCatalog::roles()))) {
                $validator->errors()->add('role_ids', 'Somente perfis padrão do SIGME podem ser atribuídos na primeira versão.');
            }

            if ($roles->contains('scope', 'school') && $schoolIds->isEmpty()) {
                $validator->errors()->add('school_ids', 'Selecione ao menos uma escola para os perfis escolares.');
            }

            if (! $this->user()->is_platform_admin) {
                foreach ($schoolIds as $schoolId) {
                    $school = School::query()->find($schoolId);
                    if ($school && ! $this->user()->canAccessSchool($school)) {
                        $validator->errors()->add('school_ids', 'Uma das escolas selecionadas está fora do seu acesso.');
                        break;
                    }
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'organization_id' => $this->user()->is_platform_admin
                ? $this->input('organization_id')
                : $this->user()->organization_id,
            'email' => Str::lower($this->input('email')),
            'is_active' => $this->boolean('is_active'),
            'school_ids' => array_values(array_filter((array) $this->input('school_ids', []))),
            'role_ids' => array_values(array_filter((array) $this->input('role_ids', []))),
        ]);
    }
}
