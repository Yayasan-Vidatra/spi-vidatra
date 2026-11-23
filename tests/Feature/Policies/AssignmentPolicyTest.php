<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AssignmentPolicyTest extends TestCase
{
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    public function test_dewan_pengawas_can_view_create_and_manage_every_active_assignment(): void
    {
        $dewanPengawas = User::factory()->withRole(RoleSlug::DewanPengawas)->create();
        $assignment = Assignment::factory()->create();

        $this->assertTrue($dewanPengawas->can('viewAny', Assignment::class));
        $this->assertTrue($dewanPengawas->can('create', Assignment::class));
        $this->assertTrue($dewanPengawas->can('view', $assignment));
        $this->assertTrue($dewanPengawas->can('close', $assignment));
        $this->assertFalse($dewanPengawas->can('reopen', $assignment));
        $this->assertTrue($dewanPengawas->can('manageMembers', $assignment));
        $this->assertTrue($dewanPengawas->can('manageFolders', $assignment));
        $this->assertTrue($dewanPengawas->can('accessConfidential', $assignment));
    }

    public function test_dewan_pengawas_can_only_reopen_a_closed_assignment(): void
    {
        $dewanPengawas = User::factory()->withRole(RoleSlug::DewanPengawas)->create();
        $assignment = Assignment::factory()->closed()->create();

        $this->assertTrue($dewanPengawas->can('view', $assignment));
        $this->assertTrue($dewanPengawas->can('reopen', $assignment));
        $this->assertFalse($dewanPengawas->can('close', $assignment));
        $this->assertFalse($dewanPengawas->can('manageMembers', $assignment));
        $this->assertFalse($dewanPengawas->can('manageFolders', $assignment));
    }

    public function test_assigned_auditor_can_view_and_manage_folders_but_not_create_close_or_manage_members(): void
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $assignment = Assignment::factory()->withAuditor($auditor)->create();

        $this->assertTrue($auditor->can('viewAny', Assignment::class));
        $this->assertTrue($auditor->can('view', $assignment));
        $this->assertTrue($auditor->can('manageFolders', $assignment));
        $this->assertFalse($auditor->can('create', Assignment::class));
        $this->assertFalse($auditor->can('close', $assignment));
        $this->assertFalse($auditor->can('manageMembers', $assignment));
        $this->assertFalse($auditor->can('accessConfidential', $assignment));
    }

    public function test_auditor_given_confidential_access_can_access_confidential_folders(): void
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $assignment = Assignment::factory()->withAuditor($auditor, canAccessConfidential: true)->create();

        $this->assertTrue($auditor->can('accessConfidential', $assignment));
    }

    public function test_auditor_cannot_view_an_assignment_they_are_not_assigned_to(): void
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $assignment = Assignment::factory()->withAuditor(User::factory()->withRole(RoleSlug::AuditorSpi)->create())->create();

        $this->assertFalse($auditor->can('view', $assignment));
        $this->assertFalse($auditor->can('manageFolders', $assignment));
    }

    public function test_assigned_auditor_cannot_manage_folders_of_a_closed_assignment(): void
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $assignment = Assignment::factory()->closed()->withAuditor($auditor)->create();

        $this->assertTrue($auditor->can('view', $assignment));
        $this->assertFalse($auditor->can('manageFolders', $assignment));
        $this->assertFalse($auditor->can('reopen', $assignment));
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_other_roles_have_no_access_to_assignments(RoleSlug $roleSlug): void
    {
        $user = User::factory()->withRole($roleSlug)->create();
        $assignment = Assignment::factory()->create();

        $this->assertFalse($user->can('viewAny', Assignment::class));
        $this->assertFalse($user->can('create', Assignment::class));
        $this->assertFalse($user->can('view', $assignment));
        $this->assertFalse($user->can('close', $assignment));
        $this->assertFalse($user->can('manageMembers', $assignment));
        $this->assertFalse($user->can('manageFolders', $assignment));
        $this->assertFalse($user->can('accessConfidential', $assignment));
    }
}
