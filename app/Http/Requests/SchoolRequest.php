<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        $school = $this->route('school');

        return $school
            ? $this->user()->can('update', $school)
            : $this->user()->can('create', School::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = $this->route('school')?->organization_id ?: $this->input('organization_id');

        return [
            'organization_id' => ['required', 'integer', Rule::exists(Organization::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique(School::class)->where('organization_id', $organizationId)->ignore($this->route('school')),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'organization_id' => $this->user()->is_platform_admin
                ? $this->input('organization_id')
                : $this->user()->organization_id,
            'code' => Str::upper(Str::slug($this->input('code'), '-')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
