<?php

namespace Database\Factories;

use App\Enums\DocumentRequestStatus;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequest>
 */
class DocumentRequestFactory extends Factory
{
    /**
     * Define the model's default state: an open request to the assignment's unit, with its own subfolder.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'sequence_number' => fake()->unique()->numberBetween(1, 60000),
            'unit_id' => fn (array $attributes): int => Assignment::query()->whereKey($attributes['assignment_id'])->value('unit_id'),
            'folder_id' => fn (array $attributes): Factory => Folder::factory()->state(['assignment_id' => $attributes['assignment_id']]),
            'status' => DocumentRequestStatus::Terbuka,
            'due_date' => now()->addWeek()->toDateString(),
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentRequestStatus::Selesai,
        ]);
    }
}
