<?php

namespace Database\Factories;

use App\Enums\Classification;
use App\Models\FolderTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Templat nyata diisi FolderTemplateSeeder.
 *
 * @extends Factory<FolderTemplate>
 */
class FolderTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'default_classification' => Classification::Internal,
            'sort_order' => fake()->unique()->numberBetween(100, 999),
        ];
    }
}
