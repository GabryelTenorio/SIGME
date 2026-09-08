<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization
            ? $this->user()->can('update', $organization)
            : $this->user()->can('create', Organization::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:120',
                Rule::unique(Organization::class)->ignore($this->route('organization')),
            ],
            'mode' => ['required', Rule::in(['single_school', 'network'])],
            'approval_threshold' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'allows_student_representative' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('slug') ?: $this->input('name')),
            'allows_student_representative' => $this->boolean('allows_student_representative'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
