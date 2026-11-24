<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\RoleSlug;
use App\Enums\ScanStatus;
use App\Jobs\ScanFileVersion;
use App\Models\Assignment;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\User;
use App\Services\FileVersionContent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class FileVersionControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    /**
     * @return array{User, Assignment, Folder}
     */
    private function auditorWithFolder(): array
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();

        return [$auditor, $assignment, Folder::factory()->for($assignment)->create()];
    }

    /**
     * A file with a clean first version and a second, current version.
     *
     * @return array{File, FileVersion}
     */
    private function fileWithTwoVersions(Folder $folder, User $uploader): array
    {
        $file = $this->storedFile($folder, $uploader);
        $firstVersion = $file->currentVersion;
        $secondVersion = FileVersion::factory()->for($file)->create(['uploaded_by' => $uploader->id]);
        Storage::disk(config('berkas.disk'))->put($secondVersion->storage_path, self::PdfContent.'% v2');
        $file->forceFill(['current_version_id' => $secondVersion->id])->save();

        return [$file->fresh(), $firstVersion];
    }

    public function test_restoring_an_old_version_creates_a_new_version_from_its_content(): void
    {
        $this->fakeFileDisk();
        Queue::fake([ScanFileVersion::class]);
        [$auditor, , $folder] = $this->auditorWithFolder();
        [$file, $firstVersion] = $this->fileWithTwoVersions($folder, $auditor);
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);

        $response = $this->actingAsVerified($dewanPengawas)->post(route('berkas.versi.pulihkan', [$file, $firstVersion]));

        $response->assertRedirect(route('berkas.show', $file));
        $file->refresh();
        $restored = $file->currentVersion;
        $this->assertSame(3, $restored->version_number);
        $this->assertSame($firstVersion->id, $restored->restored_from_version_id);
        $this->assertSame($firstVersion->sha256, $restored->sha256);
        $this->assertSame($dewanPengawas->id, $restored->uploaded_by);
        $this->assertSame(ScanStatus::Menunggu, $restored->scan_status);
        $this->assertNotSame($firstVersion->stored_name, $restored->stored_name);
        $this->assertSame(self::PdfContent, Storage::disk(config('berkas.disk'))->get($restored->storage_path));
        $this->assertSame([1, 2, 3], $file->versions()->orderBy('version_number')->pluck('version_number')->all());
        Queue::assertPushed(ScanFileVersion::class, fn (ScanFileVersion $job): bool => $job->version->is($restored));
        $this->assertAuditLogged(AuditAction::FileVersionRestored, $file, [
            'version' => 3,
            'version_id' => $restored->id,
            'restored_from_version' => 1,
            'restored_from_version_id' => $firstVersion->id,
            'sha256' => $firstVersion->sha256,
        ], $dewanPengawas);
    }

    public function test_cannot_restore_the_current_version(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor);

        $this->actingAsVerified($auditor)->post(route('berkas.versi.pulihkan', [$file, $file->currentVersion]))->assertForbidden();
    }

    public function test_cannot_restore_a_quarantined_version(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        [$file, $firstVersion] = $this->fileWithTwoVersions($folder, $auditor);
        $firstVersion->forceFill(['scan_status' => ScanStatus::Terinfeksi])->save();

        $this->actingAsVerified($auditor)->post(route('berkas.versi.pulihkan', [$file, $firstVersion]))->assertForbidden();

        $this->assertSame(2, $file->versions()->count());
    }

    public function test_cannot_restore_a_version_in_a_closed_assignment(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment, $folder] = $this->auditorWithFolder();
        [$file, $firstVersion] = $this->fileWithTwoVersions($folder, $auditor);
        $assignment->forceFill(['status' => 'ditutup', 'closed_at' => now()])->save();
        $folder->forceFill(['is_locked' => true])->save();

        $this->actingAsVerified($this->userWithRole(RoleSlug::DewanPengawas))
            ->post(route('berkas.versi.pulihkan', [$file, $firstVersion]))
            ->assertForbidden();
    }

    public function test_version_of_another_file_returns_404(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor);
        $otherFile = $this->storedFile($folder, $auditor);

        $this->actingAsVerified($auditor)->post(route('berkas.versi.pulihkan', [$file, $otherFile->current_version_id]))->assertNotFound();
        $this->actingAsVerified($auditor)->get(route('berkas.versi.unduh', [$file, $otherFile->current_version_id]))->assertNotFound();
    }

    public function test_download_redirects_to_a_signed_content_url_valid_for_five_minutes(): void
    {
        $this->freezeTime();
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor);

        $response = $this->actingAsVerified($auditor)->get(route('berkas.versi.unduh', [$file, $file->currentVersion]));

        $location = $response->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith(route('berkas.konten', [$file, $file->current_version_id, 'unduh']).'?', $location);
        $this->assertTrue(URL::hasValidSignature(Request::create($location)));
        $this->assertSame((string) $auditor->id, $query['u']);
        $this->assertSame(now()->addMinutes(5)->getTimestamp(), (int) $query['expires']);
    }

    public function test_download_is_refused_while_the_scan_is_pending(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor, ScanStatus::Menunggu);

        $this->actingAsVerified($auditor)->get(route('berkas.versi.unduh', [$file, $file->currentVersion]))->assertForbidden();
    }

    public function test_download_is_refused_for_a_quarantined_version(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor, ScanStatus::Terinfeksi);

        $this->actingAsVerified($auditor)->get(route('berkas.versi.unduh', [$file, $file->currentVersion]))->assertForbidden();
    }

    public function test_preview_is_refused_for_a_type_that_cannot_be_previewed(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor, fileAttributes: ['original_name' => 'Arsip.zip']);
        $file->currentVersion->forceFill(['mime_type' => 'application/zip'])->save();

        $this->actingAsVerified($auditor)->get(route('berkas.versi.pratinjau', [$file, $file->currentVersion]))->assertForbidden();
    }

    public function test_download_stays_allowed_after_the_assignment_is_closed(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment, $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor);
        $assignment->forceFill(['status' => 'ditutup', 'closed_at' => now()])->save();
        $folder->forceFill(['is_locked' => true])->save();

        $this->actingAsVerified($auditor)->get(route('berkas.versi.unduh', [$file, $file->currentVersion]))->assertRedirect();
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_roles_without_assignment_access_cannot_download(RoleSlug $roleSlug): void
    {
        $this->fakeFileDisk();
        $file = $this->storedFile(Folder::factory()->create(), User::factory()->create());

        $this->actingAsVerified($this->userWithRole($roleSlug))
            ->get(route('berkas.versi.unduh', [$file, $file->currentVersion]))
            ->assertForbidden();
    }

    public function test_auditor_of_another_assignment_cannot_download(): void
    {
        $this->fakeFileDisk();
        $file = $this->storedFile(Folder::factory()->create(), User::factory()->create());
        [$otherAuditor] = $this->auditorWithFolder();

        $this->actingAsVerified($otherAuditor)->get(route('berkas.versi.unduh', [$file, $file->currentVersion]))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->fakeFileDisk();
        $file = $this->storedFile(Folder::factory()->create(), User::factory()->create());

        $this->get(route('berkas.versi.unduh', [$file, $file->currentVersion]))->assertRedirect(route('login'));
    }

    public function test_restoring_a_version_of_a_rahasia_file_stores_the_copy_encrypted(): void
    {
        $this->fakeFileDisk();
        Queue::fake([ScanFileVersion::class]);
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $folder = Folder::factory()->confidential()->create();
        $file = $this->storedFile($folder, $dewanPengawas, content: 'versi pertama', encrypted: true);
        $firstVersion = $file->currentVersion;
        $secondVersion = FileVersion::factory()->for($file)->create(['is_encrypted' => true]);
        $this->putVersionContent($secondVersion, 'versi kedua');
        $file->forceFill(['current_version_id' => $secondVersion->id])->save();

        $this->actingAsVerified($dewanPengawas)->post(route('berkas.versi.pulihkan', [$file, $firstVersion]));

        $restored = $file->fresh()->currentVersion;
        $this->assertSame(3, $restored->version_number);
        $this->assertTrue($restored->is_encrypted);
        $this->assertStringNotContainsString('versi pertama', $this->storedBytes($restored));
        $this->assertSame('versi pertama', implode('', iterator_to_array(app(FileVersionContent::class)->read($restored), false)));
    }

    public function test_restoring_an_encrypted_version_is_refused_without_the_encryption_key(): void
    {
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        [$file, $firstVersion] = $this->fileWithTwoVersions(Folder::factory()->confidential()->create(), $dewanPengawas);
        $this->withoutEncryptionKey();

        $response = $this->actingAsVerified($dewanPengawas)->post(route('berkas.versi.pulihkan', [$file, $firstVersion]));

        $response->assertSessionHasErrors(['version' => 'Kunci enkripsi berkas Rahasia belum terpasang di server. Hubungi Admin IT.']);
        $this->assertSame(2, $file->versions()->count());
    }
}
