<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageAvailability', $this->route('category'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'school_ids' => ['present', 'array'],
            'school_ids.*' => ['integer', 'distinct', Rule::exists(School::class, 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['school_ids' => array_values(array_filter((array) $this->input('school_ids', [])))]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = $this->route('category');
            $schools = School::query()->whereIn('id', $this->input('school_ids', []))->get();
            foreach ($schools as $school) {
                if ($school->organization_id !== $category->organization_id
                    || ! $this->user()->canAccessSchool($school)
                    || ! $this->user()->hasPermission('categorias.gerenciar_disponibilidade', $school)) {
                    $validator->errors()->add('school_ids', 'Uma ou mais escolas não pertencem ao seu escopo autorizado.');
                    break;
                }
            }
        }];
    }
}
