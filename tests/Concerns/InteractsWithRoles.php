<?php

namespace Tests\Concerns;

use App\Enums\RoleSlug;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Models\User;

trait InteractsWithRoles
{
    /**
     * Create a user who has passed every M1 gate (2FA and confidentiality statement).
     */
    protected function userWithRole(RoleSlug $roleSlug): User
    {
        return User::factory()->withRole($roleSlug)->withTwoFactor()->acceptedConfidentiality()->create();
    }

    /**
     * Act as the user in a session where 2FA has already been verified.
     */
    protected function actingAsVerified(User $user): static
    {
        return $this->actingAs($user)->withSession([EnsureTwoFactorIsVerified::SessionKey => true]);
    }

    /**
     * @return array<string, array{RoleSlug}>
     */
    public static function rolesWithoutAssignmentAccess(): array
    {
        return [
            'super admin' => [RoleSlug::SuperAdmin],
            'pengurus yayasan' => [RoleSlug::PengurusYayasan],
            'pic unit' => [RoleSlug::PicUnit],
            'peninjau tamu' => [RoleSlug::PeninjauTamu],
        ];
    }
}
