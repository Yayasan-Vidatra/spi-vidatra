<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\AssignmentMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentMember>
 */
class AssignmentMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'user_id' => User::factory()->withRole(RoleSlug::AuditorSpi),
            'can_access_confidential' => false,
            'assigned_by' => User::factory(),
        ];
    }
}
