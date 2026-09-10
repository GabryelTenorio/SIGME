<?php

namespace App\Http\Requests;

use App\Models\Occurrence;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ServiceOrder::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'occurrence_id' => ['required', 'integer', Rule::exists(Occurrence::class, 'id')],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'member_ids' => ['array'], 'member_ids.*' => ['integer', 'distinct', Rule::exists(User::class, 'id')],
            'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'], 'planned_at' => ['nullable', 'date'], 'estimated_cost' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'requires_purchase' => ['boolean'], 'external_service' => ['boolean'], 'asset_replacement' => ['boolean'], 'asset_disposal' => ['boolean'], 'extraordinary_purchase' => ['boolean'],
            'external_provider_name' => [Rule::excludeIf(fn (): bool => ! $this->boolean('external_service')), Rule::requiredIf(fn (): bool => $this->boolean('external_service')), 'nullable', 'string', 'max:255'],
            'external_service_description' => [Rule::excludeIf(fn (): bool => ! $this->boolean('external_service')), Rule::requiredIf(fn (): bool => $this->boolean('external_service')), 'nullable', 'string', 'max:5000'],
            'external_provider_contact' => [Rule::excludeIf(fn (): bool => ! $this->boolean('external_service')), 'nullable', 'string', 'max:255'],
            'external_provider_tax_id' => [Rule::excludeIf(fn (): bool => ! $this->boolean('external_service')), 'nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['requires_purchase', 'external_service', 'asset_replacement', 'asset_disposal', 'extraordinary_purchase'] as $field) {
            $this->merge([$field => $this->boolean($field)]);
        }
        $this->merge(['estimated_cost' => $this->input('estimated_cost') ?? '0.00', 'member_ids' => (array) $this->input('member_ids', [])]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $occurrence = Occurrence::query()->find($this->integer('occurrence_id'));
            if (! $occurrence || ! $occurrence->school->is_active || $occurrence->status !== 'ENCAMINHADA' || ! $this->user()->canAccessSchool($occurrence->school) || ! $this->user()->hasPermission('ordens_servico.criar', $occurrence->school)) {
                $validator->errors()->add('occurrence_id', 'A OS deve nascer de uma ocorrência encaminhada e autorizada.');
            }

            if (! $occurrence) {
                return;
            }

            if ($this->boolean('external_service') && ! $this->filled('assigned_user_id')) {
                $validator->errors()->add('assigned_user_id', 'O serviço externo exige um responsável interno elegível.');
            }

            $requestedUserIds = collect([$this->input('assigned_user_id'), ...$this->input('member_ids', [])])
                ->filter(fn (mixed $userId): bool => is_scalar($userId) && ctype_digit((string) $userId))
                ->map(fn (mixed $userId): int => (int) $userId)
                ->unique();
            $users = User::query()->whereKey($requestedUserIds)->get()->keyBy('id');

            $assignedUserId = $this->input('assigned_user_id');
            if (is_scalar($assignedUserId) && ctype_digit((string) $assignedUserId)) {
                $assignedUser = $users->get((int) $assignedUserId);
                if ($assignedUser && ! $assignedUser->isEligibleForServiceOrderAssignment($occurrence->school)) {
                    $validator->errors()->add('assigned_user_id', 'O responsável deve estar ativo, vinculado à escola e possuir acesso técnico à OS.');
                }
            }

            foreach ($this->input('member_ids', []) as $index => $memberId) {
                if (! is_scalar($memberId) || ! ctype_digit((string) $memberId)) {
                    continue;
                }

                $member = $users->get((int) $memberId);
                if ($member && ! $member->isEligibleForServiceOrderAssignment($occurrence->school)) {
                    $validator->errors()->add("member_ids.{$index}", 'O membro deve estar ativo, vinculado à escola e possuir acesso técnico à OS.');
                }
            }
        }];
    }
}
