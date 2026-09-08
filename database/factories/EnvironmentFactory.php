<?php

namespace Database\Factories;

use App\Models\Environment;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Environment>
 */
class EnvironmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'parent_id' => null,
            'code' => strtoupper(fake()->unique()->bothify('AMB-###')),
            'name' => fake()->randomElement(['Sala de aula', 'Laboratório', 'Biblioteca', 'Secretaria', 'Quadra']),
            'type' => fake()->randomElement(array_keys(Environment::TYPES)),
            'building' => null,
            'floor' => null,
            'capacity' => fake()->optional()->numberBetween(1, 80),
            'description' => null,
            'is_active' => true,
        ];
    }
}
