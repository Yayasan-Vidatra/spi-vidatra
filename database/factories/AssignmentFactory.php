<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\AssignmentMember;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Audit '.fake()->words(2, true),
            'year' => 2026,
            'sequence_number' => fake()->unique()->numberBetween(1, 60000),
            'unit_id' => Unit::factory(),
            'code' => fn (array $attributes): string => sprintf(
                '%d-%02d-%s-%s',
                $attributes['year'],
                $attributes['sequence_number'],
                Unit::query()->whereKey($attributes['unit_id'])->value('code'),
                $attributes['title'],
            ),
            'team_lead_id' => null,
            'status' => AssignmentStatus::Aktif,
            'start_date' => '2026-01-05',
            'end_date' => null,
            'created_by' => User::factory(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::Ditutup,
            'closed_at' => now(),
        ]);
    }

    /**
     * Assign the given auditor to the assignment.
     */
    public function withAuditor(User $auditor, bool $canAccessConfidential = false): static
    {
        return $this->has(
            AssignmentMember::factory()->state([
                'user_id' => $auditor->id,
                'can_access_confidential' => $canAccessConfidential,
            ]),
            'members',
        );
    }
}
