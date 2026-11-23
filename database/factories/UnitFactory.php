<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Unit nyata diisi UnitSeeder; kode factory empat huruf sehingga tidak bentrok dengan kode baku.
 *
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'X'.strtoupper(fake()->unique()->lexify('???')),
            'name' => 'Unit '.fake()->city(),
        ];
    }
}
