<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\RoleSlug;
use App\Jobs\SyncFileEncryption;
use App\Models\Assignment;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class FolderControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    /**
     * @return array{User, Assignment, Folder}
     */
    private function auditorWithStandardFolder(bool $canAccessConfidential = false): array
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor, $canAccessConfidential)->create();
        $standardFolder = Folder::factory()->for($assignment)->standard()->create(['name' => '03 Kertas Kerja']);

        return [$auditor, $assignment, $standardFolder];
    }

    public function test_assigned_auditor_creates_a_subfolder_that_inherits_the_parent_label(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.folder.store', $assignment), [
            'parent_id' => $standardFolder->id,
            'name' => 'KKA Kas',
        ]);

        $response->assertRedirect(route('penugasan.show', $assignment));
        $folder = Folder::query()->where('name', 'KKA Kas')->sole();
        $this->assertSame($standardFolder->id, $folder->parent_id);
        $this->assertSame($assignment->id, $folder->assignment_id);
        $this->assertSame('internal', $folder->classification->value);
        $this->assertSame($auditor->id, $folder->created_by);
        $this->assertAuditLogged(AuditAction::FolderCreated, $folder, [
            'name' => 'KKA Kas',
            'parent_id' => $standardFolder->id,
            'classification' => 'internal',
        ], $auditor);
    }

    public function test_subfolder_label_can_be_set_when_created(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();

        $this->actingAsVerified($auditor)->post(route('penugasan.folder.store', $assignment), [
            'parent_id' => $standardFolder->id,
            'name' => 'Dokumen Publik',
            'classification' => 'umum',
        ]);

        $this->assertSame('umum', Folder::query()->where('name', 'Dokumen Publik')->sole()->classification->value);
    }

    public function test_auditor_without_confidential_access_cannot_create_a_rahasia_subfolder(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.folder.store', $assignment), [
            'parent_id' => $standardFolder->id,
            'name' => 'Indikasi Fraud',
            'classification' => 'rahasia',
        ]);

        $response->assertForbidden();
        $this->assertFalse(Folder::query()->where('name', 'Indikasi Fraud')->exists());
    }

    public function test_auditor_cannot_create_a_lower_labelled_subfolder_under_a_rahasia_folder(): void
    {
        [$auditor, $assignment] = $this->auditorWithStandardFolder(canAccessConfidential: true);
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.folder.store', $assignment), [
            'parent_id' => $confidentialFolder->id,
            'name' => 'Turunan',
            'classification' => 'internal',
        ]);

        $response->assertForbidden();
    }

    public function test_rejects_a_duplicate_name_in_the_same_parent(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();
        Folder::factory()->childOf($standardFolder)->create(['name' => 'KKA Kas']);

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.folder.store', $assignment), [
            'parent_id' => $standardFolder->id,
            'name' => 'KKA Kas',
        ]);

        $response->assertSessionHasErrors(['name' => 'Sudah ada folder dengan nama ini di folder induk yang sama.']);
    }

    public function test_rejects_a_parent_folder_from_another_assignment(): void
    {
        [$auditor, $assignment] = $this->auditorWithStandardFolder();
        $otherFolder = Folder::factory()->create();

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.folder.store', $assignment), [
            'parent_id' => $otherFolder->id,
            'name' => 'Nyasar',
        ]);

        $response->assertSessionHasErrors(['parent_id' => 'Folder induk tidak ditemukan di penugasan ini.']);
        $this->assertFalse(Folder::query()->where('name', 'Nyasar')->exists());
    }

    public function test_auditor_not_assigned_to_the_assignment_cannot_create_folders(): void
    {
        $outsider = $this->userWithRole(RoleSlug::AuditorSpi);
        [, $assignment, $standardFolder] = $this->auditorWithStandardFolder();

        $response = $this->actingAsVerified($outsider)->post(route('penugasan.folder.store', $assignment), [
            'parent_id' => $standardFolder->id,
            'name' => 'Penyusup',
        ]);

        $response->assertForbidden();
    }

    public function test_assigned_auditor_renames_a_subfolder(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();
        $folder = Folder::factory()->childOf($standardFolder)->create(['name' => 'KKA Lama']);

        $response = $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $folder]), ['name' => 'KKA Baru']);

        $response->assertRedirect(route('penugasan.show', $assignment));
        $this->assertSame('KKA Baru', $folder->fresh()->name);
        $this->assertAuditLogged(AuditAction::FolderRenamed, $folder, ['from' => 'KKA Lama', 'to' => 'KKA Baru']);
    }

    public function test_standard_folder_cannot_be_renamed(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();

        $response = $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $standardFolder]), ['name' => 'Ganti']);

        $response->assertForbidden();
        $this->assertSame('03 Kertas Kerja', $standardFolder->fresh()->name);
    }

    public function test_assigned_auditor_moves_a_subfolder(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();
        $target = Folder::factory()->for($assignment)->standard()->create(['name' => '04 Bukti Unit']);
        $folder = Folder::factory()->childOf($standardFolder)->create();

        $response = $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $folder]), ['parent_id' => $target->id]);

        $response->assertRedirect(route('penugasan.show', $assignment));
        $this->assertSame($target->id, $folder->fresh()->parent_id);
        $this->assertAuditLogged(AuditAction::FolderMoved, $folder, ['from_parent_id' => $standardFolder->id, 'to_parent_id' => $target->id]);
    }

    public function test_folder_cannot_be_moved_into_its_own_subfolder(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();
        $folder = Folder::factory()->childOf($standardFolder)->create();
        $child = Folder::factory()->childOf($folder)->create();

        $response = $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $folder]), ['parent_id' => $child->id]);

        $response->assertSessionHasErrors(['parent_id' => 'Folder tidak bisa dipindah ke dalam dirinya sendiri atau subfoldernya.']);
        $this->assertSame($standardFolder->id, $folder->fresh()->parent_id);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_auditor_cannot_move_a_folder_out_of_a_rahasia_parent(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder(canAccessConfidential: true);
        $confidentialParent = Folder::factory()->childOf($standardFolder)->confidential()->create();
        $folder = Folder::factory()->childOf($confidentialParent)->create();

        $response = $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $folder]), ['parent_id' => $standardFolder->id]);

        $response->assertForbidden();
        $this->assertSame($confidentialParent->id, $folder->fresh()->parent_id);
    }

    public function test_label_of_a_standard_folder_can_be_changed_and_is_logged(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();

        $response = $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $standardFolder]), ['classification' => 'umum']);

        $response->assertRedirect(route('penugasan.show', $assignment));
        $this->assertSame('umum', $standardFolder->fresh()->classification->value);
        $this->assertAuditLogged(AuditAction::FolderLabelChanged, $standardFolder, ['from' => 'internal', 'to' => 'umum'], $auditor);
    }

    public function test_auditor_without_confidential_access_cannot_label_a_folder_rahasia(): void
    {
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();

        $response = $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $standardFolder]), ['classification' => 'rahasia']);

        $response->assertForbidden();
        $this->assertSame('internal', $standardFolder->fresh()->classification->value);
    }

    public function test_only_dewan_pengawas_can_lower_a_rahasia_label(): void
    {
        [$auditor, $assignment] = $this->auditorWithStandardFolder(canAccessConfidential: true);
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();

        $this->actingAsVerified($auditor)
            ->patch(route('penugasan.folder.update', [$assignment, $confidentialFolder]), ['classification' => 'internal'])
            ->assertForbidden();
        $this->actingAsVerified($dewanPengawas)
            ->patch(route('penugasan.folder.update', [$assignment, $confidentialFolder]), ['classification' => 'internal'])
            ->assertRedirect();

        $this->assertSame('internal', $confidentialFolder->fresh()->classification->value);
        $this->assertAuditLogged(AuditAction::FolderLabelChanged, $confidentialFolder, ['from' => 'rahasia', 'to' => 'internal'], $dewanPengawas);
    }

    public function test_hidden_rahasia_folder_cannot_be_changed_by_auditor_without_access(): void
    {
        [$auditor, $assignment] = $this->auditorWithStandardFolder();
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create(['name' => 'Indikasi Fraud']);

        $response = $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $confidentialFolder]), ['name' => 'Ketahuan']);

        $response->assertForbidden();
        $this->assertSame('Indikasi Fraud', $confidentialFolder->fresh()->name);
    }

    public function test_locked_folder_rejects_changes(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $folder = Folder::factory()->for($assignment)->locked()->create(['name' => 'Terkunci']);

        $response = $this->actingAsVerified($dewanPengawas)->patch(route('penugasan.folder.update', [$assignment, $folder]), ['name' => 'Dibuka']);

        $response->assertForbidden();
        $this->assertSame('Terkunci', $folder->fresh()->name);
    }

    public function test_folder_of_another_assignment_returns_404(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $otherFolder = Folder::factory()->create(['name' => 'Milik Lain']);

        $response = $this->actingAsVerified($dewanPengawas)->patch(route('penugasan.folder.update', [$assignment, $otherFolder]), ['name' => 'Dibajak']);

        $response->assertNotFound();
        $this->assertSame('Milik Lain', $otherFolder->fresh()->name);
    }

    public function test_labelling_a_folder_rahasia_encrypts_the_files_beneath_it(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder(canAccessConfidential: true);
        $child = Folder::factory()->childOf($standardFolder)->create();
        $file = File::factory()->for($child)->withVersion()->create();
        $this->putVersionContent($file->currentVersion, 'isi berkas');

        $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $standardFolder]), ['classification' => 'rahasia']);

        $this->assertTrue($file->currentVersion->fresh()->is_encrypted);
    }

    public function test_moving_a_folder_into_a_rahasia_parent_schedules_encryption_of_its_files(): void
    {
        Queue::fake([SyncFileEncryption::class]);
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder(canAccessConfidential: true);
        $folder = Folder::factory()->childOf($standardFolder)->create();
        $file = File::factory()->for(Folder::factory()->childOf($folder)->create())->withVersion()->create();
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();

        $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $folder]), ['parent_id' => $confidentialFolder->id]);

        Queue::assertPushed(SyncFileEncryption::class, fn (SyncFileEncryption $job): bool => $job->file->is($file));
    }

    public function test_moving_a_folder_between_non_rahasia_parents_does_not_re_encrypt(): void
    {
        Queue::fake([SyncFileEncryption::class]);
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder();
        $folder = Folder::factory()->childOf($standardFolder)->create();
        File::factory()->for($folder)->withVersion()->create();
        $target = Folder::factory()->for($assignment)->create();

        $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $folder]), ['parent_id' => $target->id]);

        $this->assertSame($target->id, $folder->fresh()->parent_id);
        Queue::assertNotPushed(SyncFileEncryption::class);
    }

    public function test_labelling_a_folder_with_files_rahasia_is_refused_without_the_encryption_key(): void
    {
        $this->withoutEncryptionKey();
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder(canAccessConfidential: true);
        File::factory()->for($standardFolder)->withVersion()->create();

        $response = $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $standardFolder]), ['classification' => 'rahasia']);

        $response->assertSessionHasErrors(['classification' => 'Kunci enkripsi berkas Rahasia belum terpasang di server. Hubungi Admin IT.']);
        $this->assertSame('internal', $standardFolder->fresh()->classification->value);
    }

    public function test_labelling_an_empty_folder_rahasia_works_without_the_encryption_key(): void
    {
        $this->withoutEncryptionKey();
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder(canAccessConfidential: true);

        $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $standardFolder]), ['classification' => 'rahasia']);

        $this->assertSame('rahasia', $standardFolder->fresh()->classification->value);
    }

    public function test_moving_a_folder_with_files_into_a_rahasia_parent_is_refused_without_the_encryption_key(): void
    {
        $this->withoutEncryptionKey();
        [$auditor, $assignment, $standardFolder] = $this->auditorWithStandardFolder(canAccessConfidential: true);
        $folder = Folder::factory()->childOf($standardFolder)->create();
        File::factory()->for(Folder::factory()->childOf($folder)->create())->withVersion()->create();
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();

        $response = $this->actingAsVerified($auditor)->patch(route('penugasan.folder.update', [$assignment, $folder]), ['parent_id' => $confidentialFolder->id]);

        $response->assertSessionHasErrors(['parent_id' => 'Kunci enkripsi berkas Rahasia belum terpasang di server. Hubungi Admin IT.']);
        $this->assertSame($standardFolder->id, $folder->fresh()->parent_id);
    }

    public function test_moving_a_folder_with_files_out_of_a_rahasia_parent_is_refused_without_the_encryption_key(): void
    {
        $this->withoutEncryptionKey();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();
        $folder = Folder::factory()->childOf($confidentialFolder)->create();
        File::factory()->for($folder)->withVersion(versionAttributes: ['is_encrypted' => true])->create();
        $target = Folder::factory()->for($assignment)->create();

        $response = $this->actingAsVerified($dewanPengawas)->patch(route('penugasan.folder.update', [$assignment, $folder]), ['parent_id' => $target->id]);

        $response->assertSessionHasErrors(['parent_id' => 'Kunci enkripsi berkas Rahasia belum terpasang di server. Hubungi Admin IT.']);
        $this->assertSame($confidentialFolder->id, $folder->fresh()->parent_id);
    }
}
