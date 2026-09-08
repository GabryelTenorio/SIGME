<?php

namespace App\Http\Requests;

use App\Models\Environment;
use App\Models\Occurrence;
use App\Models\OccurrenceCategory;
use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OccurrenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Occurrence::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', Rule::exists(School::class, 'id')],
            'environment_id' => ['required', 'integer', Rule::exists(Environment::class, 'id')],
            'occurrence_category_id' => ['required', 'integer', Rule::exists(OccurrenceCategory::class, 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'impact' => ['required', Rule::in(array_keys(Occurrence::IMPACTS))],
            'perceived_urgency' => ['required', Rule::in(array_keys(Occurrence::URGENCIES))],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $school = School::query()->find($this->integer('school_id'));
            if (! $school || ! $this->user()->canAccessSchool($school) || ! $this->user()->hasPermission('ocorrencias.criar', $school)) {
                $validator->errors()->add('school_id', 'Você não pode registrar ocorrências nesta escola.');

                return;
            }
            $environment = Environment::query()->find($this->integer('environment_id'));
            if (! $environment || $environment->school_id !== $school->id || ! $environment->is_active) {
                $validator->errors()->add('environment_id', 'Selecione um ambiente ativo desta escola.');
            }
            $category = OccurrenceCategory::query()->find($this->integer('occurrence_category_id'));
            if (! $category || ! $category->is_active || $category->organization_id !== $school->organization_id || ! $category->schools()->whereKey($school->id)->exists()) {
                $validator->errors()->add('occurrence_category_id', 'Selecione uma categoria ativa e disponível nesta escola.');
            }
        }];
    }
}
