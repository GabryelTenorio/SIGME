<?php

namespace App\Http\Requests;

use App\Models\OccurrenceCategory;
use App\Models\Organization;
use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OccurrenceCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', OccurrenceCategory::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $organizationId = $category?->organization_id ?: $this->input('organization_id');

        return [
            'organization_id' => ['required', 'integer', Rule::exists(Organization::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['required', 'string', 'max:255', Rule::unique(OccurrenceCategory::class)->where('organization_id', $organizationId)->ignore($category)],
            'description' => ['nullable', 'string', 'max:3000'],
            'display_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['required', 'boolean'],
            'school_ids' => [$category ? 'sometimes' : 'present', 'array'],
            'school_ids.*' => ['integer', 'distinct', Rule::exists(School::class, 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $category = $this->route('category');
        $name = Str::squish((string) $this->input('name'));

        $this->merge([
            'organization_id' => $category?->organization_id
                ?: ($this->user()->is_platform_admin ? $this->input('organization_id') : $this->user()->organization_id),
            'name' => $name,
            'identifier' => Str::slug($name),
            'display_order' => $this->input('display_order', 0),
            'is_active' => $this->boolean('is_active'),
            'school_ids' => array_values(array_filter((array) $this->input('school_ids', []))),
        ]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = $this->route('category');
            $organization = Organization::query()->with('schools')->find($this->integer('organization_id'));
            if (! $organization) {
                return;
            }

            if (! $this->user()->is_platform_admin) {
                $allowed = $this->user()->organization_id === $organization->id
                    && ($organization->mode === 'network'
                        ? $this->user()->hasPermission($category ? 'categorias.editar' : 'categorias.criar')
                        : $organization->schools->contains(fn ($school) => $this->user()->canAccessSchool($school)
                            && $this->user()->hasPermission($category ? 'categorias.editar' : 'categorias.criar', $school)));
                if (! $allowed) {
                    $validator->errors()->add('organization_id', 'Você não pode gerenciar categorias desta organização.');
                }
            }

            if ($category?->is_fallback && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'A categoria de fallback da organização deve permanecer ativa.');
            }

            $invalidSchool = School::query()->whereIn('id', $this->input('school_ids', []))
                ->where('organization_id', '!=', $organization->id)->exists();
            if ($invalidSchool) {
                $validator->errors()->add('school_ids', 'Todas as escolas devem pertencer à organização da categoria.');
            }
        }];
    }
}
