<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Enums\ScanStatus;
use App\Jobs\ScanFileVersion;
use App\Models\Assignment;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\User;
use App\Services\Scanning\VirusScanner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\Concerns\InteractsWithRoles;
use Tests\Fakes\FakeVirusScanner;
use Tests\TestCase;

class FileControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    /**
     * @return array{User, Assignment, Folder}
     */
    private function auditorWithFolder(bool $canAccessConfidential = false): array
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor, $canAccessConfidential)->create();
        $folder = Folder::factory()->for($assignment)->standard()->create(['name' => '03 Kertas Kerja']);

        return [$auditor, $assignment, $folder];
    }

    private function generalFolder(string $name = 'Regulasi dan Pedoman'): Folder
    {
        return Folder::query()->whereNull('assignment_id')->where('name', $name)->sole();
    }

    public function test_assigned_auditor_uploads_several_files_at_once(): void
    {
        $this->fakeFileDisk();
        Queue::fake([ScanFileVersion::class]);
        [$auditor, $assignment, $folder] = $this->auditorWithFolder();

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [
                $this->pdfUpload('Program Kerja.pdf'),
                UploadedFile::fake()->createWithContent('KKA Kas.xlsx', $this->officeContent('xlsx')),
                UploadedFile::fake()->image('Foto Kas.png'),
            ],
        ]);

        $response->assertRedirect(route('penugasan.show', ['assignment' => $assignment, 'folder' => $folder->id]));
        $this->assertSame(
            ['Foto Kas.png', 'KKA Kas.xlsx', 'Program Kerja.pdf'],
            File::query()->where('folder_id', $folder->id)->orderBy('original_name')->pluck('original_name')->all(),
        );
        Queue::assertPushed(ScanFileVersion::class, 3);
    }

    public function test_upload_stores_content_under_a_uuid_with_hash_uploader_and_pending_scan(): void
    {
        $this->fakeFileDisk();
        Queue::fake([ScanFileVersion::class]);
        [$auditor, , $folder] = $this->auditorWithFolder();

        $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload('Surat Tugas.pdf')],
        ]);

        $file = File::query()->where('original_name', 'Surat Tugas.pdf')->sole();
        $version = $file->currentVersion;
        $this->assertSame(1, $version->version_number);
        $this->assertSame(hash('sha256', self::PdfContent), $version->sha256);
        $this->assertSame(strlen(self::PdfContent), $version->size);
        $this->assertSame('application/pdf', $version->mime_type);
        $this->assertSame($auditor->id, $version->uploaded_by);
        $this->assertSame(ScanStatus::Menunggu, $version->scan_status);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $version->stored_name);
        $this->assertSame('versi/'.$version->stored_name, $version->storage_path);
        $this->assertSame(self::PdfContent, Storage::disk(config('berkas.disk'))->get($version->storage_path));
        Queue::assertPushed(ScanFileVersion::class, fn (ScanFileVersion $job): bool => $job->version->is($version));
        $this->assertAuditLogged(AuditAction::FileUploaded, $file, [
            'name' => 'Surat Tugas.pdf',
            'folder_id' => $folder->id,
            'version' => 1,
            'version_id' => $version->id,
            'size' => strlen(self::PdfContent),
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', self::PdfContent),
        ], $auditor);
    }

    public function test_uploaded_file_inherits_the_folder_label(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment] = $this->auditorWithFolder();
        $publicFolder = Folder::factory()->for($assignment)->create(['classification' => Classification::Umum]);

        $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $publicFolder), [
            'files' => [$this->pdfUpload('Pengumuman.pdf')],
        ]);

        $this->assertSame(Classification::Umum, File::query()->where('original_name', 'Pengumuman.pdf')->sole()->classification);
    }

    public function test_uploading_the_same_name_in_the_same_folder_adds_the_next_version(): void
    {
        $this->fakeFileDisk();
        Queue::fake([ScanFileVersion::class]);
        [$auditor, , $folder] = $this->auditorWithFolder();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $file = $this->storedFile($folder, $auditor, fileAttributes: ['original_name' => 'KKA Kas.pdf']);
        $firstVersion = $file->currentVersion;
        $newContent = self::PdfContent.'% revisi';

        $this->actingAsVerified($dewanPengawas)->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload('KKA Kas.pdf', $newContent)],
        ]);

        $file->refresh();
        $this->assertSame(1, File::query()->where('folder_id', $folder->id)->count());
        $this->assertSame(2, $file->currentVersion->version_number);
        $this->assertSame(hash('sha256', $newContent), $file->currentVersion->sha256);
        $this->assertSame($dewanPengawas->id, $file->currentVersion->uploaded_by);
        $this->assertTrue($firstVersion->fresh()->exists);
        Storage::disk(config('berkas.disk'))->assertExists($firstVersion->storage_path);
        $this->assertAuditLogged(AuditAction::FileVersionAdded, $file, user: $dewanPengawas);
    }

    public function test_same_name_in_another_folder_is_a_separate_file(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment, $folder] = $this->auditorWithFolder();
        $otherFolder = Folder::factory()->for($assignment)->create();
        $this->storedFile($folder, $auditor, fileAttributes: ['original_name' => 'Laporan.pdf']);

        $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $otherFolder), [
            'files' => [$this->pdfUpload('Laporan.pdf')],
        ]);

        $this->assertSame(1, File::query()->where('folder_id', $otherFolder->id)->sole()->currentVersion->version_number);
    }

    public function test_upload_is_scanned_and_becomes_clean(): void
    {
        $this->fakeFileDisk();
        $scanner = FakeVirusScanner::clean();
        $this->app->instance(VirusScanner::class, $scanner);
        [$auditor, , $folder] = $this->auditorWithFolder();

        $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload('Bersih.pdf')],
        ]);

        $file = File::query()->where('original_name', 'Bersih.pdf')->sole();
        $this->assertSame(ScanStatus::Bersih, $file->currentVersion->scan_status);
        $this->assertSame([self::PdfContent], $scanner->scannedContents);
        $this->assertAuditLogged(AuditAction::FileScanned, $file, [
            'version' => 1,
            'version_id' => $file->current_version_id,
            'result' => 'bersih',
        ]);
    }

    public function test_infected_upload_is_quarantined(): void
    {
        $this->fakeFileDisk();
        $this->app->instance(VirusScanner::class, FakeVirusScanner::infected('Eicar-Signature'));
        [$auditor, , $folder] = $this->auditorWithFolder();

        $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload('Virus.pdf')],
        ]);

        $version = File::query()->where('original_name', 'Virus.pdf')->sole()->currentVersion;
        $this->assertSame(ScanStatus::Terinfeksi, $version->scan_status);
        $this->assertSame('Eicar-Signature', $version->scan_signature);
        $this->assertSame('karantina/'.$version->stored_name, $version->storage_path);
        Storage::disk(config('berkas.disk'))->assertMissing('versi/'.$version->stored_name);
        Storage::disk(config('berkas.disk'))->assertExists('karantina/'.$version->stored_name);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function disguisedFiles(): array
    {
        return [
            'exe renamed to pdf' => ['Laporan.pdf', 'executable'],
            'png renamed to pdf' => ['Laporan.pdf', 'png'],
            'zip renamed to docx' => ['Laporan.docx', 'zip'],
            'macro document renamed to docx' => ['Laporan.docx', 'docm'],
        ];
    }

    #[DataProvider('disguisedFiles')]
    public function test_rejects_a_file_whose_content_does_not_match_its_extension(string $name, string $content): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $bytes = match ($content) {
            'executable' => $this->executableContent(),
            'png' => UploadedFile::fake()->image('foto.png')->getContent(),
            'zip' => $this->zipContent(),
            'docm' => $this->officeContent('docm'),
        };
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [UploadedFile::fake()->createWithContent($name, $bytes)],
        ]);

        $response->assertSessionHasErrors(['files.0' => "Isi berkas {$name} tidak sesuai dengan jenis .{$extension}."]);
        $this->assertSame(0, File::query()->count());
        Storage::disk(config('berkas.disk'))->assertDirectoryEmpty('/');
    }

    public function test_rejects_a_file_type_that_is_not_allowed(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [UploadedFile::fake()->createWithContent('pemasang.exe', $this->executableContent())],
        ]);

        $response->assertSessionHasErrors([
            'files.0' => 'Jenis berkas pemasang.exe tidak diizinkan. Jenis yang diizinkan: PDF, DOCX, XLSX, PPTX, JPG, JPEG, PNG, ZIP.',
        ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function genuineOfficeFiles(): array
    {
        return [
            'docx' => ['Program Kerja.docx', 'docx'],
            'xlsx' => ['KKA.xlsx', 'xlsx'],
            'pptx' => ['Paparan.pptx', 'pptx'],
        ];
    }

    #[DataProvider('genuineOfficeFiles')]
    public function test_accepts_office_documents_that_libmagic_reads_as_zip(string $name, string $kind): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [UploadedFile::fake()->createWithContent($name, $this->officeContent($kind))],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertStringContainsString('openxmlformats', File::query()->where('original_name', $name)->sole()->currentVersion->mime_type);
    }

    public function test_rejects_a_file_larger_than_the_configured_limit(): void
    {
        $this->fakeFileDisk();
        config(['berkas.max_size_mb' => 2]);
        [$auditor, , $folder] = $this->auditorWithFolder();

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload('Besar.pdf')->size(2 * 1024 + 1)],
        ]);

        $response->assertSessionHasErrors(['files.0' => 'Ukuran berkas Besar.pdf melebihi batas 2 MB.']);
        $this->assertSame(0, File::query()->count());
    }

    public function test_accepts_a_file_exactly_at_the_configured_limit(): void
    {
        $this->fakeFileDisk();
        config(['berkas.max_size_mb' => 2]);
        [$auditor, , $folder] = $this->auditorWithFolder();

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload('Pas.pdf')->size(2 * 1024)],
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_rejects_more_files_than_allowed_per_upload(): void
    {
        $this->fakeFileDisk();
        config(['berkas.max_files_per_upload' => 2]);
        [$auditor, , $folder] = $this->auditorWithFolder();

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload('a.pdf'), $this->pdfUpload('b.pdf'), $this->pdfUpload('c.pdf')],
        ]);

        $response->assertSessionHasErrors(['files' => 'Maksimal 2 berkas per unggahan.']);
    }

    public function test_requires_at_least_one_file(): void
    {
        [$auditor, , $folder] = $this->auditorWithFolder();

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), []);

        $response->assertSessionHasErrors(['files' => 'Pilih minimal satu berkas.']);
    }

    public function test_strips_path_and_control_characters_from_the_original_name(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();

        $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload("..\\..\\Laporan\r\nSet-Cookie: x.pdf")],
        ]);

        $this->assertSame('LaporanSet-Cookie: x.pdf', File::query()->sole()->original_name);
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_roles_without_assignment_access_cannot_upload(RoleSlug $roleSlug): void
    {
        $this->fakeFileDisk();
        $folder = Folder::factory()->create();

        $response = $this->actingAsVerified($this->userWithRole($roleSlug))->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload()],
        ]);

        $response->assertForbidden();
        $this->assertSame(0, File::query()->count());
    }

    public function test_auditor_not_assigned_to_the_assignment_cannot_upload(): void
    {
        $this->fakeFileDisk();
        $folder = Folder::factory()->create();

        $response = $this->actingAsVerified($this->userWithRole(RoleSlug::AuditorSpi))->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload()],
        ]);

        $response->assertForbidden();
    }

    public function test_auditor_without_confidential_access_cannot_upload_to_a_rahasia_folder(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment] = $this->auditorWithFolder();
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $confidentialFolder), [
            'files' => [$this->pdfUpload()],
        ]);

        $response->assertForbidden();
    }

    public function test_auditor_with_confidential_access_uploads_to_a_rahasia_folder(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment] = $this->auditorWithFolder(canAccessConfidential: true);
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();

        $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $confidentialFolder), [
            'files' => [$this->pdfUpload('Indikasi.pdf')],
        ]);

        $this->assertSame(Classification::Rahasia, File::query()->where('original_name', 'Indikasi.pdf')->sole()->classification);
    }

    public function test_name_taken_by_a_hidden_rahasia_file_is_rejected_without_revealing_the_file(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $this->userWithRole(RoleSlug::DewanPengawas), fileAttributes: [
            'original_name' => 'Indikasi Fraud.pdf',
            'classification' => Classification::Rahasia,
        ]);

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload('Indikasi Fraud.pdf', self::PdfContent.'% timpa')],
        ]);

        $response->assertSessionHasErrors(['files.0' => 'Berkas ini tidak dapat diunggah ke folder ini. Ganti nama berkas lalu coba lagi.']);
        $this->assertStringNotContainsString('Indikasi Fraud', implode(' ', session('errors')->all()));
        $this->assertSame(1, $file->versions()->count());
        $this->assertCount(1, Storage::disk(config('berkas.disk'))->allFiles());
    }

    public function test_cannot_upload_to_a_locked_folder(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment] = $this->auditorWithFolder();
        $lockedFolder = Folder::factory()->for($assignment)->locked()->create();

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $lockedFolder), [
            'files' => [$this->pdfUpload()],
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_upload_to_an_archived_folder(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment] = $this->auditorWithFolder();
        $archivedFolder = Folder::factory()->for($assignment)->archived()->create();

        $response = $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $archivedFolder), [
            'files' => [$this->pdfUpload()],
        ]);

        $response->assertForbidden();
    }

    public function test_dewan_pengawas_cannot_upload_to_a_closed_assignment(): void
    {
        $this->fakeFileDisk();
        $assignment = Assignment::factory()->closed()->create();
        $folder = Folder::factory()->for($assignment)->create();

        $response = $this->actingAsVerified($this->userWithRole(RoleSlug::DewanPengawas))->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload()],
        ]);

        $response->assertForbidden();
    }

    public function test_dewan_pengawas_uploads_to_a_general_folder(): void
    {
        $this->fakeFileDisk();
        $folder = $this->generalFolder();

        $response = $this->actingAsVerified($this->userWithRole(RoleSlug::DewanPengawas))->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload('Piagam Audit.pdf')],
        ]);

        $response->assertRedirect(route('folder-umum.index', ['folder' => $folder->id]));
        $this->assertSame(Classification::Internal, File::query()->where('original_name', 'Piagam Audit.pdf')->sole()->classification);
    }

    public function test_auditor_cannot_upload_to_a_general_folder(): void
    {
        $this->fakeFileDisk();

        $response = $this->actingAsVerified($this->userWithRole(RoleSlug::AuditorSpi))->post(route('folder.berkas.store', $this->generalFolder('Templat')), [
            'files' => [$this->pdfUpload()],
        ]);

        $response->assertForbidden();
    }

    public function test_detail_page_shows_versions_and_preview_of_a_clean_pdf(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment, $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor, fileAttributes: ['original_name' => 'Surat Tugas.pdf']);
        $oldVersion = $file->currentVersion;
        $newVersion = FileVersion::factory()->for($file)->pending()->create(['uploaded_by' => $auditor->id]);
        $file->forceFill(['current_version_id' => $newVersion->id])->save();

        $response = $this->actingAsVerified($auditor)->get(route('berkas.show', $file));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Berkas/Show')
            ->where('file.name', 'Surat Tugas.pdf')
            ->where('file.folder_path', '03 Kertas Kerja')
            ->where('file.assignment_code', $assignment->code)
            ->where('preview', null)
            ->has('versions', 2)
            ->where('versions.0.number', 2)
            ->where('versions.0.scan_status', 'menunggu')
            ->where('versions.0.download_url', null)
            ->where('versions.1.number', 1)
            ->where('versions.1.download_url', route('berkas.versi.unduh', [$file, $oldVersion]))
            ->where('versions.1.can.restore', true)
            ->where('accessHistory', null),
        );
    }

    public function test_detail_page_offers_a_preview_for_a_clean_image(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor, fileAttributes: ['original_name' => 'Foto.png']);
        $file->currentVersion->forceFill(['mime_type' => 'image/png'])->save();

        $response = $this->actingAsVerified($auditor)->get(route('berkas.show', $file));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('preview.url', route('berkas.versi.pratinjau', [$file, $file->current_version_id]))
            ->where('preview.type', 'image'),
        );
    }

    public function test_dewan_pengawas_sees_the_access_history_from_audit_logs(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);

        $this->actingAsVerified($auditor)->post(route('folder.berkas.store', $folder), [
            'files' => [$this->pdfUpload('Laporan.pdf')],
        ]);
        $file = File::query()->sole();
        $contentUrl = $this->actingAsVerified($auditor)->get(route('berkas.versi.unduh', [$file, $file->current_version_id]))->headers->get('Location');
        $this->get($contentUrl)->assertOk();

        $response = $this->actingAsVerified($dewanPengawas)->get(route('berkas.show', $file));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('accessHistory', 3)
            ->where('accessHistory.0.action', AuditAction::FileDownloaded->value)
            ->where('accessHistory.0.user', $auditor->name)
            ->where('accessHistory.0.version', 1)
            ->where('accessHistory.1.action', AuditAction::FileScanned->value)
            ->where('accessHistory.2.action', AuditAction::FileUploaded->value),
        );
    }

    public function test_auditor_without_confidential_access_cannot_open_a_rahasia_file_by_direct_url(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $this->userWithRole(RoleSlug::DewanPengawas), fileAttributes: ['classification' => Classification::Rahasia]);

        $this->actingAsVerified($auditor)->get(route('berkas.show', $file))->assertForbidden();
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_roles_without_assignment_access_cannot_open_the_detail_page(RoleSlug $roleSlug): void
    {
        $this->fakeFileDisk();
        $folder = Folder::factory()->create();
        $file = $this->storedFile($folder, User::factory()->create());

        $this->actingAsVerified($this->userWithRole($roleSlug))->get(route('berkas.show', $file))->assertForbidden();
    }

    public function test_detail_page_stays_readable_after_the_assignment_is_closed(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment, $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor);
        $assignment->forceFill(['status' => 'ditutup', 'closed_at' => now()])->save();
        $folder->forceFill(['is_locked' => true])->save();

        $response = $this->actingAsVerified($auditor)->get(route('berkas.show', $file));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('file.is_locked', true)
            ->where('can.changeLabel', false)
            ->where('versions.0.download_url', route('berkas.versi.unduh', [$file, $file->current_version_id])),
        );
    }

    public function test_auditor_changes_the_file_label(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor);

        $response = $this->actingAsVerified($auditor)->patch(route('berkas.update', $file), ['classification' => 'umum']);

        $response->assertRedirect(route('berkas.show', $file));
        $this->assertSame(Classification::Umum, $file->fresh()->classification);
        $this->assertAuditLogged(AuditAction::FileLabelChanged, $file, ['from' => 'internal', 'to' => 'umum'], $auditor);
    }

    public function test_auditor_without_confidential_access_cannot_label_a_file_rahasia(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor);

        $this->actingAsVerified($auditor)->patch(route('berkas.update', $file), ['classification' => 'rahasia'])->assertForbidden();

        $this->assertSame(Classification::Internal, $file->fresh()->classification);
    }

    public function test_only_dewan_pengawas_lowers_a_rahasia_file_label(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder(canAccessConfidential: true);
        $file = $this->storedFile($folder, $auditor, fileAttributes: ['classification' => Classification::Rahasia]);

        $this->actingAsVerified($auditor)->patch(route('berkas.update', $file), ['classification' => 'internal'])->assertForbidden();
        $this->actingAsVerified($this->userWithRole(RoleSlug::DewanPengawas))->patch(route('berkas.update', $file), ['classification' => 'internal']);

        $this->assertSame(Classification::Internal, $file->fresh()->classification);
    }

    public function test_cannot_change_the_label_of_a_file_in_a_locked_folder(): void
    {
        $this->fakeFileDisk();
        [$auditor, $assignment] = $this->auditorWithFolder();
        $file = $this->storedFile(Folder::factory()->for($assignment)->locked()->create(), $auditor);

        $this->actingAsVerified($auditor)->patch(route('berkas.update', $file), ['classification' => 'umum'])->assertForbidden();
    }

    public function test_rejects_an_unknown_label(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $folder] = $this->auditorWithFolder();
        $file = $this->storedFile($folder, $auditor);

        $response = $this->actingAsVerified($auditor)->patch(route('berkas.update', $file), ['classification' => 'publik']);

        $response->assertSessionHasErrors(['classification' => 'Label tidak valid.']);
    }
}
