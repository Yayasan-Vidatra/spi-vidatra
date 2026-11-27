<?php

namespace Database\Factories;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Libur nyata diisi HolidaySeeder; factory memakai tahun 2040 ke atas agar tidak bentrok dengan data baku.
 *
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('2040-01-01', '2049-12-31')->format('Y-m-d'),
            'description' => 'Libur '.fake()->word(),
            'type' => HolidayType::LiburNasional,
        ];
    }

    public function cutiBersama(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => HolidayType::CutiBersama]);
    }
}
