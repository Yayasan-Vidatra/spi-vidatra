<?php

namespace Tests\Feature\Policies;

use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class FolderPolicyTest extends TestCase
{
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    private function auditor(): User
    {
        return User::factory()->withRole(RoleSlug::AuditorSpi)->create();
    }

    private function dewanPengawas(): User
    {
        return User::factory()->withRole(RoleSlug::DewanPengawas)->create();
    }

    public function test_confidential_folder_and_its_contents_are_hidden_from_auditor_without_confidential_access(): void
    {
        $auditor = $this->auditor();
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();
        $internalChild = Folder::factory()->childOf($confidentialFolder)->create();

        $this->assertFalse($auditor->can('view', $confidentialFolder));
        $this->assertFalse($auditor->can('view', $internalChild));
        $this->assertFalse($auditor->can('rename', $internalChild));
    }

    public function test_confidential_folder_is_visible_to_auditor_given_access_and_to_dewan_pengawas(): void
    {
        $auditor = $this->auditor();
        $assignment = Assignment::factory()->withAuditor($auditor, canAccessConfidential: true)->create();
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();

        $this->assertTrue($auditor->can('view', $confidentialFolder));
        $this->assertTrue($this->dewanPengawas()->can('view', $confidentialFolder));
    }

    public function test_auditor_cannot_view_folders_of_an_assignment_they_are_not_assigned_to(): void
    {
        $folder = Folder::factory()->create();

        $this->assertFalse($this->auditor()->can('view', $folder));
    }

    public function test_standard_folder_cannot_be_renamed_moved_or_archived_but_its_label_can_change(): void
    {
        $assignment = Assignment::factory()->create();
        $standardFolder = Folder::factory()->for($assignment)->standard()->create();
        $dewanPengawas = $this->dewanPengawas();

        $this->assertFalse($dewanPengawas->can('rename', $standardFolder));
        $this->assertFalse($dewanPengawas->can('move', $standardFolder));
        $this->assertFalse($dewanPengawas->can('archive', $standardFolder));
        $this->assertTrue($dewanPengawas->can('changeLabel', [$standardFolder, Classification::Rahasia]));
        $this->assertTrue($dewanPengawas->can('createSubfolder', $standardFolder));
    }

    public function test_assigned_auditor_can_manage_subfolders(): void
    {
        $auditor = $this->auditor();
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $subfolder = Folder::factory()->for($assignment)->create();

        $this->assertTrue($auditor->can('createSubfolder', $subfolder));
        $this->assertTrue($auditor->can('rename', $subfolder));
        $this->assertTrue($auditor->can('move', $subfolder));
        $this->assertTrue($auditor->can('archive', $subfolder));
        $this->assertTrue($auditor->can('changeLabel', [$subfolder, Classification::Umum]));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedFolderStates(): array
    {
        return [
            'locked folder' => ['locked'],
            'archived folder' => ['archived'],
            'closed assignment' => ['closed'],
        ];
    }

    #[DataProvider('blockedFolderStates')]
    public function test_locked_archived_or_closed_folders_reject_every_change(string $state): void
    {
        $dewanPengawas = $this->dewanPengawas();
        $assignment = $state === 'closed' ? Assignment::factory()->closed()->create() : Assignment::factory()->create();
        $factory = Folder::factory()->for($assignment);
        $folder = match ($state) {
            'locked' => $factory->locked()->create(),
            'archived' => $factory->archived()->create(),
            'closed' => $factory->create(),
        };

        $this->assertFalse($dewanPengawas->can('createSubfolder', $folder));
        $this->assertFalse($dewanPengawas->can('rename', $folder));
        $this->assertFalse($dewanPengawas->can('move', $folder));
        $this->assertFalse($dewanPengawas->can('archive', $folder));
        $this->assertFalse($dewanPengawas->can('changeLabel', $folder));
    }

    public function test_auditor_without_confidential_access_cannot_label_a_folder_rahasia(): void
    {
        $auditor = $this->auditor();
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $folder = Folder::factory()->for($assignment)->create();

        $this->assertFalse($auditor->can('changeLabel', [$folder, Classification::Rahasia]));
        $this->assertFalse($auditor->can('applyLabelUnder', [$folder, Classification::Rahasia]));
    }

    public function test_auditor_with_confidential_access_can_label_a_folder_rahasia(): void
    {
        $auditor = $this->auditor();
        $assignment = Assignment::factory()->withAuditor($auditor, canAccessConfidential: true)->create();
        $folder = Folder::factory()->for($assignment)->create();

        $this->assertTrue($auditor->can('changeLabel', [$folder, Classification::Rahasia]));
    }

    public function test_only_dewan_pengawas_can_lower_a_rahasia_label(): void
    {
        $auditor = $this->auditor();
        $assignment = Assignment::factory()->withAuditor($auditor, canAccessConfidential: true)->create();
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();

        $this->assertFalse($auditor->can('changeLabel', [$confidentialFolder, Classification::Internal]));
        $this->assertFalse($auditor->can('applyLabelUnder', [$confidentialFolder, Classification::Internal]));
        $this->assertTrue($this->dewanPengawas()->can('changeLabel', [$confidentialFolder, Classification::Internal]));
    }

    public function test_only_dewan_pengawas_can_move_a_folder_out_of_a_rahasia_parent(): void
    {
        $auditor = $this->auditor();
        $assignment = Assignment::factory()->withAuditor($auditor, canAccessConfidential: true)->create();
        $confidentialParent = Folder::factory()->for($assignment)->confidential()->create();
        $internalChild = Folder::factory()->childOf($confidentialParent)->create();
        $internalTarget = Folder::factory()->for($assignment)->create();

        $this->assertFalse($auditor->can('moveTo', [$internalChild, $internalTarget]));
        $this->assertTrue($this->dewanPengawas()->can('moveTo', [$internalChild, $internalTarget]));
    }

    public function test_general_folders_are_managed_by_dewan_pengawas_and_viewed_by_auditors(): void
    {
        $generalFolder = Folder::query()->whereNull('assignment_id')->where('name', 'Regulasi dan Pedoman')->sole();
        $auditor = $this->auditor();
        $dewanPengawas = $this->dewanPengawas();

        $this->assertTrue($auditor->can('view', $generalFolder));
        $this->assertFalse($auditor->can('createSubfolder', $generalFolder));
        $this->assertTrue($dewanPengawas->can('createSubfolder', $generalFolder));
        $this->assertFalse($dewanPengawas->can('rename', $generalFolder));
        $this->assertFalse($dewanPengawas->can('archive', $generalFolder));
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_other_roles_cannot_view_or_change_any_folder(RoleSlug $roleSlug): void
    {
        $user = User::factory()->withRole($roleSlug)->create();
        $assignmentFolder = Folder::factory()->create();
        $generalFolder = Folder::query()->whereNull('assignment_id')->where('name', 'Templat')->sole();

        $this->assertFalse($user->can('view', $assignmentFolder));
        $this->assertFalse($user->can('createSubfolder', $assignmentFolder));
        $this->assertFalse($user->can('changeLabel', $assignmentFolder));
        $this->assertFalse($user->can('view', $generalFolder));
        $this->assertFalse($user->can('createSubfolder', $generalFolder));
    }
}
