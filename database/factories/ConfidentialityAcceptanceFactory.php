<?php

namespace Database\Factories;

use App\Models\ConfidentialityAcceptance;
use App\Models\ConfidentialityStatement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfidentialityAcceptance>
 */
class ConfidentialityAcceptanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'confidentiality_statement_id' => ConfidentialityStatement::factory(),
            'accepted_at' => now(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
