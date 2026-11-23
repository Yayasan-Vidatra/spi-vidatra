<?php

namespace Database\Factories;

use App\Enums\Classification;
use App\Models\Assignment;
use App\Models\Folder;
use App\Models\FolderTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    /**
     * Define the model's default state: a user-created subfolder inside an assignment.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'parent_id' => null,
            'folder_template_id' => null,
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'classification' => Classification::Internal,
            'is_locked' => false,
            'archived_at' => null,
            'created_by' => null,
        ];
    }

    /**
     * Place the folder inside the given parent, in the parent's assignment.
     */
    public function childOf(Folder $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_id' => $parent->assignment_id,
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * One of the seven standard folders created from a template.
     */
    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'folder_template_id' => FolderTemplate::query()->orderBy('sort_order')->value('id'),
        ]);
    }

    public function confidential(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification' => Classification::Rahasia,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
