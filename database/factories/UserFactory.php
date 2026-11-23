<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\ConfidentialityAcceptance;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Kode cadangan yang dipakai state withTwoFactor().
     */
    public const string RecoveryCode = 'ABCDE-12345';

    /**
     * Define the model's default state.
     *
     * Tanpa state, pengguna belum punya peran, sama seperti akun yang baru pertama kali masuk.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->userName().'@vidatra.sch.id',
            'google_id' => (string) fake()->unique()->numberBetween(100000000000, 999999999999),
            'email_verified_at' => now(),
            'role_id' => null,
            'is_active' => true,
        ];
    }

    /**
     * Assign one of the seeded roles. Requires RoleSeeder to have run.
     */
    public function withRole(RoleSlug $roleSlug): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::query()->where('slug', $roleSlug)->value('id'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user has activated app-based two-factor authentication.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_recovery_codes' => [Hash::make(self::RecoveryCode)],
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user has accepted the confidentiality statement.
     */
    public function acceptedConfidentiality(): static
    {
        return $this->has(ConfidentialityAcceptance::factory(), 'confidentialityAcceptances');
    }
}
