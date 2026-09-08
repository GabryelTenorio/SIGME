<?php

namespace Database\Factories;

use App\Models\Occurrence;
use App\Models\Organization;
use App\Models\School;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'school_id' => School::factory(),
            'occurrence_id' => Occurrence::factory(),
            'created_by' => User::factory(),
            'assigned_user_id' => null,
            'code' => 'OS-TST-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'code_year' => now()->year,
            'code_sequence' => fake()->unique()->numberBetween(1, 999999),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority_snapshot' => 'MEDIUM',
            'status' => 'APROVADA',
            'due_date' => now()->addWeek(),
            'estimated_cost' => '0.00',
            'external_service' => false,
            'external_provider_name' => null,
            'external_service_description' => null,
            'external_provider_contact' => null,
            'external_provider_tax_id' => null,
            'approval_required' => false,
        ];
    }
}
