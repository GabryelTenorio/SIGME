<?php

namespace App\Http\Requests;

use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceOrderTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('o');

        return $order instanceof ServiceOrder && $this->user()->can('assign', $order);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $order = $this->route('o');

        return [
            'assigned_user_id' => [
                Rule::requiredIf($order instanceof ServiceOrder && $order->external_service),
                'nullable',
                'integer',
                Rule::exists(User::class, 'id'),
            ],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists(User::class, 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['member_ids' => (array) $this->input('member_ids', [])]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $order = $this->route('o');
            if (! $order instanceof ServiceOrder) {
                return;
            }

            $requestedUserIds = collect([$this->input('assigned_user_id'), ...$this->input('member_ids', [])])
                ->filter(fn (mixed $userId): bool => is_scalar($userId) && ctype_digit((string) $userId))
                ->map(fn (mixed $userId): int => (int) $userId)
                ->unique();
            $users = User::query()->whereKey($requestedUserIds)->get()->keyBy('id');

            $assignedUserId = $this->input('assigned_user_id');
            if (is_scalar($assignedUserId) && ctype_digit((string) $assignedUserId)) {
                $assignedUser = $users->get((int) $assignedUserId);
                if ($assignedUser && ! $assignedUser->isEligibleForServiceOrderAssignment($order->school)) {
                    $validator->errors()->add('assigned_user_id', 'O responsável deve estar ativo, vinculado à escola e possuir acesso técnico à OS.');
                }
            }

            foreach ($this->input('member_ids', []) as $index => $memberId) {
                if (! is_scalar($memberId) || ! ctype_digit((string) $memberId)) {
                    continue;
                }

                $member = $users->get((int) $memberId);
                if ($member && ! $member->isEligibleForServiceOrderAssignment($order->school)) {
                    $validator->errors()->add("member_ids.{$index}", 'O membro deve estar ativo, vinculado à escola e possuir acesso técnico à OS.');
                }
            }
        }];
    }
}
