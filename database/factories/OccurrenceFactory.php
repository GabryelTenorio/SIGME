<?php

namespace Database\Factories;

use App\Models\Environment;
use App\Models\Occurrence;
use App\Models\OccurrenceCategory;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Occurrence>
 */
class OccurrenceFactory extends Factory
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
            'environment_id' => Environment::factory(),
            'occurrence_category_id' => OccurrenceCategory::factory(),
            'reporter_id' => User::factory(),
            'triaged_by' => null,
            'duplicate_of_id' => null,
            'protocol' => 'SIG-TST-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'protocol_year' => now()->year,
            'protocol_sequence' => fake()->unique()->numberBetween(1, 999999),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'impact' => 'MEDIUM',
            'perceived_urgency' => 'NORMAL',
            'suggested_priority' => 'MEDIUM',
            'confirmed_priority' => null,
            'status' => 'ABERTA',
            'triage_note' => null,
            'triaged_at' => null,
            'forwarded_destination' => null,
        ];
    }
}
