<?php

namespace Tests\Feature\Policies;

use App\Enums\FolderPurpose;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class DocumentRequestPolicyTest extends TestCase
{
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    /**
     * @return array{Assignment, Folder}
     */
    private function assignmentWithRequestsFolder(?User $auditor = null, bool $canAccessConfidential = false): array
    {
        $factory = Assignment::factory();

        if ($auditor !== null) {
            $factory = $factory->withAuditor($auditor, $canAccessConfidential);
        }

        $assignment = $factory->create();
        $requestsFolder = Folder::factory()->for($assignment)->forPurpose(FolderPurpose::PermintaanDokumen)->create();

        return [$assignment, $requestsFolder];
    }

    public function test_dewan_pengawas_and_assigned_auditor_can_create_and_view_requests(): void
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $dewanPengawas = User::factory()->withRole(RoleSlug::DewanPengawas)->create();
        [$assignment] = $this->assignmentWithRequestsFolder($auditor);
        $documentRequest = DocumentRequest::factory()->for($assignment)->create();

        foreach ([$auditor, $dewanPengawas] as $user) {
            $this->assertTrue($user->can('create', [DocumentRequest::class, $assignment]));
            $this->assertTrue($user->can('viewAny', [DocumentRequest::class, $assignment]));
            $this->assertTrue($user->can('view', $documentRequest));
        }
    }

    public function test_auditor_not_assigned_to_the_assignment_cannot_create_or_view_requests(): void
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        [$assignment] = $this->assignmentWithRequestsFolder();
        $documentRequest = DocumentRequest::factory()->for($assignment)->create();

        $this->assertFalse($auditor->can('create', [DocumentRequest::class, $assignment]));
        $this->assertFalse($auditor->can('viewAny', [DocumentRequest::class, $assignment]));
        $this->assertFalse($auditor->can('view', $documentRequest));
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_other_roles_cannot_create_or_view_requests_from_the_spi_side(RoleSlug $roleSlug): void
    {
        $user = User::factory()->withRole($roleSlug)->create();
        [$assignment] = $this->assignmentWithRequestsFolder();
        $documentRequest = DocumentRequest::factory()->for($assignment)->create();

        $this->assertFalse($user->can('create', [DocumentRequest::class, $assignment]));
        $this->assertFalse($user->can('viewAny', [DocumentRequest::class, $assignment]));
        $this->assertFalse($user->can('view', $documentRequest));
    }

    public function test_requests_cannot_be_created_in_a_closed_assignment_but_stay_visible(): void
    {
        $dewanPengawas = User::factory()->withRole(RoleSlug::DewanPengawas)->create();
        [$assignment] = $this->assignmentWithRequestsFolder();
        $documentRequest = DocumentRequest::factory()->for($assignment)->create();
        $assignment->forceFill(['status' => 'ditutup', 'closed_at' => now()])->save();

        $this->assertFalse($dewanPengawas->can('create', [DocumentRequest::class, $assignment->fresh()]));
        $this->assertTrue($dewanPengawas->can('view', $documentRequest->fresh()));
    }

    public function test_requests_cannot_be_created_when_the_requests_folder_is_locked_or_missing(): void
    {
        $dewanPengawas = User::factory()->withRole(RoleSlug::DewanPengawas)->create();
        [$assignment, $requestsFolder] = $this->assignmentWithRequestsFolder();
        $assignmentWithoutFolder = Assignment::factory()->create();

        $requestsFolder->forceFill(['is_locked' => true])->save();

        $this->assertFalse($dewanPengawas->can('create', [DocumentRequest::class, $assignment]));
        $this->assertFalse($dewanPengawas->can('create', [DocumentRequest::class, $assignmentWithoutFolder]));
    }

    public function test_auditor_needs_confidential_access_when_the_requests_folder_is_rahasia(): void
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $confidentialAuditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $assignment = Assignment::factory()->withAuditor($auditor)->withAuditor($confidentialAuditor, canAccessConfidential: true)->create();
        Folder::factory()->for($assignment)->forPurpose(FolderPurpose::PermintaanDokumen)->confidential()->create();

        $this->assertFalse($auditor->can('create', [DocumentRequest::class, $assignment]));
        $this->assertTrue($confidentialAuditor->can('create', [DocumentRequest::class, $assignment]));
    }
}
