<?php

namespace Database\Factories;

use App\Models\OccurrenceCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OccurrenceCategory>
 */
class OccurrenceCategoryFactory extends Factory
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
            'name' => 'Categoria '.fake()->unique()->word(),
            'identifier' => fake()->unique()->slug(2),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 100),
            'is_fallback' => false,
        ];
    }
}
