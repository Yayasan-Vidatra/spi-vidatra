<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Peran nyata diisi RoleSeeder; factory ini hanya untuk peran uji di luar daftar baku.
 *
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->jobTitle(),
            'requires_two_factor' => false,
        ];
    }
}
