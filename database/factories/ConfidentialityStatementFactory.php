<?php

namespace Database\Factories;

use App\Models\ConfidentialityStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfidentialityStatement>
 */
class ConfidentialityStatementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->paragraphs(3, true),
        ];
    }
}
