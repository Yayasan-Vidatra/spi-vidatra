<?php

namespace Tests\Feature\Policies;

use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Enums\ScanStatus;
use App\Models\Assignment;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class FilePolicyTest extends TestCase
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

    private function fileIn(Folder $folder, ScanStatus $scanStatus = ScanStatus::Bersih, Classification $classification = Classification::Internal): File
    {
        return File::factory()->for($folder)->withVersion($scanStatus)->create(['classification' => $classification])->fresh();
    }

    public function test_assigned_auditor_views_downloads_and_manages_an_internal_file(): void
    {
        $auditor = $this->auditor();
        $file = $this->fileIn(Folder::factory()->for(Assignment::factory()->withAuditor($auditor))->create());

        $this->assertTrue($auditor->can('view', $file));
        $this->assertTrue($auditor->can('download', [$file, $file->currentVersion]));
        $this->assertTrue($auditor->can('preview', [$file, $file->currentVersion]));
        $this->assertTrue($auditor->can('uploadVersion', $file));
        $this->assertTrue($auditor->can('changeLabel', [$file, Classification::Umum]));
        $this->assertFalse($auditor->can('viewAccessHistory', $file));
    }

    public function test_rahasia_file_in_an_internal_folder_needs_confidential_access(): void
    {
        $auditor = $this->auditor();
        $confidentialAuditor = $this->auditor();
        $assignment = Assignment::factory()->withAuditor($auditor)->withAuditor($confidentialAuditor, canAccessConfidential: true)->create();
        $file = $this->fileIn(Folder::factory()->for($assignment)->create(), classification: Classification::Rahasia);

        $this->assertFalse($auditor->can('view', $file));
        $this->assertFalse($auditor->can('download', [$file, $file->currentVersion]));
        $this->assertTrue($confidentialAuditor->can('view', $file));
        $this->assertTrue($this->dewanPengawas()->can('view', $file));
    }

    public function test_file_in_a_rahasia_folder_is_hidden_whatever_its_own_label(): void
    {
        $auditor = $this->auditor();
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();
        $file = $this->fileIn(Folder::factory()->childOf($confidentialFolder)->create(), classification: Classification::Umum);

        $this->assertFalse($auditor->can('view', $file));
    }

    public function test_pending_and_quarantined_versions_cannot_be_downloaded_or_restored(): void
    {
        $dewanPengawas = $this->dewanPengawas();
        $file = $this->fileIn(Folder::factory()->create());
        $pending = FileVersion::factory()->for($file)->pending()->create();
        $infected = FileVersion::factory()->for($file)->create(['scan_status' => ScanStatus::Terinfeksi]);

        foreach ([$pending, $infected] as $version) {
            $this->assertFalse($dewanPengawas->can('download', [$file, $version]));
            $this->assertFalse($dewanPengawas->can('preview', [$file, $version]));
            $this->assertFalse($dewanPengawas->can('restoreVersion', [$file, $version]));
        }
    }

    public function test_version_of_another_file_is_refused(): void
    {
        $dewanPengawas = $this->dewanPengawas();
        $file = $this->fileIn(Folder::factory()->create());
        $otherFile = $this->fileIn(Folder::factory()->create());

        $this->assertFalse($dewanPengawas->can('download', [$file, $otherFile->currentVersion]));
        $this->assertFalse($dewanPengawas->can('restoreVersion', [$file, $otherFile->currentVersion]));
    }

    public function test_closed_assignment_keeps_view_and_download_but_refuses_changes(): void
    {
        $dewanPengawas = $this->dewanPengawas();
        $file = $this->fileIn(Folder::factory()->for(Assignment::factory()->closed())->locked()->create());
        $oldVersion = FileVersion::factory()->for($file)->create();

        $this->assertTrue($dewanPengawas->can('view', $file));
        $this->assertTrue($dewanPengawas->can('download', [$file, $file->currentVersion]));
        $this->assertFalse($dewanPengawas->can('uploadVersion', $file));
        $this->assertFalse($dewanPengawas->can('changeLabel', $file));
        $this->assertFalse($dewanPengawas->can('restoreVersion', [$file, $oldVersion]));
    }

    public function test_label_rules_follow_the_folder_label_rules(): void
    {
        $auditor = $this->auditor();
        $confidentialAuditor = $this->auditor();
        $assignment = Assignment::factory()->withAuditor($auditor)->withAuditor($confidentialAuditor, canAccessConfidential: true)->create();
        $folder = Folder::factory()->for($assignment)->create();
        $internalFile = $this->fileIn($folder);
        $confidentialFile = $this->fileIn($folder, classification: Classification::Rahasia);

        $this->assertFalse($auditor->can('changeLabel', [$internalFile, Classification::Rahasia]));
        $this->assertTrue($confidentialAuditor->can('changeLabel', [$internalFile, Classification::Rahasia]));
        $this->assertFalse($confidentialAuditor->can('changeLabel', [$confidentialFile, Classification::Internal]));
        $this->assertTrue($this->dewanPengawas()->can('changeLabel', [$confidentialFile, Classification::Internal]));
    }

    public function test_general_folder_files_are_managed_by_dewan_pengawas_and_viewed_by_auditors(): void
    {
        $auditor = $this->auditor();
        $dewanPengawas = $this->dewanPengawas();
        $folder = Folder::query()->whereNull('assignment_id')->where('name', 'Templat')->sole();
        $file = $this->fileIn($folder);
        $confidentialFile = $this->fileIn($folder, classification: Classification::Rahasia);

        $this->assertTrue($auditor->can('view', $file));
        $this->assertTrue($auditor->can('download', [$file, $file->currentVersion]));
        $this->assertFalse($auditor->can('uploadVersion', $file));
        $this->assertFalse($auditor->can('view', $confidentialFile));
        $this->assertTrue($dewanPengawas->can('uploadVersion', $file));
        $this->assertTrue($dewanPengawas->can('view', $confidentialFile));
    }

    public function test_only_dewan_pengawas_views_the_access_history(): void
    {
        $auditor = $this->auditor();
        $file = $this->fileIn(Folder::factory()->for(Assignment::factory()->withAuditor($auditor))->create());

        $this->assertFalse($auditor->can('viewAccessHistory', $file));
        $this->assertTrue($this->dewanPengawas()->can('viewAccessHistory', $file));
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_roles_without_assignment_access_have_no_file_access(RoleSlug $roleSlug): void
    {
        $user = User::factory()->withRole($roleSlug)->create();
        $assignmentFile = $this->fileIn(Folder::factory()->create());
        $generalFile = $this->fileIn(Folder::query()->whereNull('assignment_id')->where('name', 'Regulasi dan Pedoman')->sole());

        foreach ([$assignmentFile, $generalFile] as $file) {
            $this->assertFalse($user->can('view', $file));
            $this->assertFalse($user->can('download', [$file, $file->currentVersion]));
            $this->assertFalse($user->can('uploadVersion', $file));
        }
    }
}
