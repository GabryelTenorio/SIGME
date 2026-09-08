<?php

namespace App\Http\Requests;

use App\Models\Environment;
use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EnvironmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $environment = $this->route('environment');

        return $environment
            ? $this->user()->can('update', $environment)
            : $this->user()->can('create', Environment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $environment = $this->route('environment');
        $schoolId = $environment?->school_id ?: $this->input('school_id');

        return [
            'school_id' => ['required', 'integer', Rule::exists(School::class, 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists(Environment::class, 'id')],
            'code' => ['required', 'string', 'max:40', Rule::unique(Environment::class)->where('school_id', $schoolId)->ignore($environment)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Environment::TYPES))],
            'building' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:80'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $environment = $this->route('environment');
        $code = Str::of((string) $this->input('code'))->ascii()->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->toString();

        $this->merge([
            'school_id' => $environment?->school_id ?: $this->input('school_id'),
            'parent_id' => $this->filled('parent_id') ? $this->input('parent_id') : null,
            'code' => $code,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $environment = $this->route('environment');
            $school = School::query()->find($this->integer('school_id'));
            if (! $school) {
                return;
            }

            $permission = $environment ? 'ambientes.editar' : 'ambientes.criar';
            if (! $this->user()->canAccessSchool($school)
                || (! $this->user()->hasPermission($permission, $school)
                    && ! $this->user()->hasPermission('ambientes.gerenciar', $school))) {
                $validator->errors()->add('school_id', 'Você não pode gerenciar ambientes desta escola.');
            }

            $parent = $this->filled('parent_id') ? Environment::query()->find($this->integer('parent_id')) : null;
            if ($parent && $parent->school_id !== $school->id) {
                $validator->errors()->add('parent_id', 'O ambiente superior deve pertencer à mesma escola.');
            }

            if ($environment && $parent) {
                $cursor = $parent;
                while ($cursor) {
                    if ($cursor->is($environment)) {
                        $validator->errors()->add('parent_id', 'A hierarquia não pode formar um ciclo.');
                        break;
                    }
                    $cursor = $cursor->parent;
                }
            }

            if ($environment && $environment->is_active && ! $this->boolean('is_active')
                && ! $this->user()->can('deactivate', $environment)) {
                $validator->errors()->add('is_active', 'Você não possui permissão para desativar este ambiente.');
            }
        }];
    }
}
