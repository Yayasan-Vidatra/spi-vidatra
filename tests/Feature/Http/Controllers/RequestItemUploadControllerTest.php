<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Enums\RequestItemStatus;
use App\Enums\RoleSlug;
use App\Enums\ScanStatus;
use App\Jobs\ScanFileVersion;
use App\Models\AssignmentMember;
use App\Models\DocumentRequest;
use App\Models\File;
use App\Models\RequestItem;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\RequestItemUploaded;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class RequestItemUploadControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeFileDisk();
        Queue::fake([ScanFileVersion::class]);
    }

    private function unit(string $code = 'SMP'): Unit
    {
        return Unit::query()->where('code', $code)->sole();
    }

    private function pic(string $unitCode = 'SMP'): User
    {
        return User::factory()->picOf($this->unit($unitCode))->withTwoFactor()->acceptedConfidentiality()->create();
    }

    /**
     * @param  array<string, mixed>  $itemAttributes
     */
    private function slot(array $itemAttributes = [], string $dueDate = '2026-12-10'): RequestItem
    {
        $documentRequest = DocumentRequest::factory()->create([
            'unit_id' => $this->unit()->id,
            'due_date' => $dueDate,
            'created_by' => $this->userWithRole(RoleSlug::AuditorSpi)->id,
        ]);

        return RequestItem::factory()->for($documentRequest)->create([
            'name' => 'Laporan kas Oktober',
            'accepted_extensions' => ['pdf', 'xlsx'],
            ...$itemAttributes,
        ]);
    }

    private function uploadUrl(RequestItem $item): string
    {
        return route('permintaan-unit.slot.unggah', [$item->documentRequest, $item]);
    }

    public function test_pic_uploads_to_a_slot_named_after_the_document_and_the_slot_becomes_diunggah(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Makassar')->setDateTime(2026, 12, 9, 10, 0));
        $pic = $this->pic();
        $item = $this->slot();

        $response = $this->actingAsVerified($pic)->post($this->uploadUrl($item), ['file' => $this->pdfUpload('scan_kas_okt.pdf')]);

        $response->assertRedirect(route('permintaan-unit.show', $item->documentRequest));
        $response->assertInertiaFlash('success', '"Laporan kas Oktober" diunggah. Berkas sedang dipindai antivirus.');
        $item->refresh();
        $file = $item->file;
        $version = $file->currentVersion;
        $this->assertSame(RequestItemStatus::Diunggah, $item->status);
        $this->assertEquals(now(), $item->submitted_at);
        $this->assertSame('Laporan kas Oktober.pdf', $file->original_name);
        $this->assertSame($item->documentRequest->folder_id, $file->folder_id);
        $this->assertSame(1, $version->version_number);
        $this->assertSame($pic->id, $version->uploaded_by);
        $this->assertSame(ScanStatus::Menunggu, $version->scan_status);
        $this->assertSame(self::PdfContent, Storage::disk(config('berkas.disk'))->get($version->storage_path));
        Queue::assertPushed(ScanFileVersion::class, fn (ScanFileVersion $job): bool => $job->version->is($version));
        $this->assertSame($item->id, $this->assertAuditLogged(AuditAction::FileUploaded, $file, user: $pic)->properties['request_item_id']);
        $this->assertAuditLogged(AuditAction::RequestItemUploaded, $item, [
            'document_request_id' => $item->document_request_id,
            'unit' => 'SMP',
            'name' => 'Laporan kas Oktober',
            'file_id' => $file->id,
            'version' => 1,
            'version_id' => $version->id,
            'late' => false,
        ], $pic);
    }

    public function test_only_the_request_creator_is_notified_of_the_upload(): void
    {
        Notification::fake();
        $pic = $this->pic();
        $item = $this->slot();
        $otherMember = $this->userWithRole(RoleSlug::AuditorSpi);
        AssignmentMember::factory()->for($item->documentRequest->assignment)->create(['user_id' => $otherMember->id]);

        $this->actingAsVerified($pic)->post($this->uploadUrl($item), ['file' => $this->pdfUpload()]);

        $creator = $item->documentRequest->creator;
        Notification::assertSentTo($creator, RequestItemUploaded::class, fn (RequestItemUploaded $notification): bool => $notification->item->is($item));
        Notification::assertCount(1);
        $this->assertStringContainsString('mengunggah "Laporan kas Oktober"', (new RequestItemUploaded($item->fresh(), $pic))->toArray($creator)['message']);
    }

    public function test_reupload_with_another_accepted_extension_adds_a_version_and_renames_the_slot_file(): void
    {
        $pic = $this->pic();
        $otherPic = $this->pic();
        $item = $this->slot();
        $this->actingAsVerified($pic)->post($this->uploadUrl($item), ['file' => $this->pdfUpload()]);
        $item->refresh()->forceFill([
            'status' => RequestItemStatus::PerluPerbaikan,
            'reviewer_note' => 'Kurang tanda tangan',
            'system_note' => 'catatan lama',
        ])->save();

        $this->actingAsVerified($otherPic)->post($this->uploadUrl($item), [
            'file' => UploadedFile::fake()->createWithContent('kas.xlsx', $this->officeContent('xlsx')),
        ]);

        $item->refresh();
        $this->assertSame(1, File::query()->count());
        $this->assertSame('Laporan kas Oktober.xlsx', $item->file->original_name);
        $this->assertSame(2, $item->file->currentVersion->version_number);
        $this->assertSame($otherPic->id, $item->file->currentVersion->uploaded_by);
        $this->assertSame(RequestItemStatus::Diunggah, $item->status);
        $this->assertNull($item->system_note);
        $this->assertSame('Kurang tanda tangan', $item->reviewer_note);
        $this->assertAuditLogged(AuditAction::FileVersionAdded, $item->file, user: $otherPic);
    }

    public function test_upload_after_the_deadline_is_accepted_and_marked_late(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Makassar')->setDateTime(2026, 12, 11, 8, 0));
        $pic = $this->pic();
        $item = $this->slot(dueDate: '2026-12-10');

        $this->actingAsVerified($pic)->post($this->uploadUrl($item), ['file' => $this->pdfUpload()])->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertTrue($item->isSubmittedLate());
        $this->assertTrue($this->assertAuditLogged(AuditAction::RequestItemUploaded, $item)->properties['late']);
    }

    public function test_extension_outside_the_slot_formats_is_rejected(): void
    {
        $pic = $this->pic();
        $item = $this->slot();

        $response = $this->actingAsVerified($pic)->post($this->uploadUrl($item), ['file' => UploadedFile::fake()->image('foto.png')]);

        $response->assertSessionHasErrors(['file' => 'Format berkas untuk dokumen ini harus PDF atau Excel (.pdf, .xlsx).']);
        $this->assertNull($item->fresh()->file_id);
        Storage::disk(config('berkas.disk'))->assertDirectoryEmpty('/');
    }

    public function test_content_that_does_not_match_its_extension_is_rejected_by_the_general_upload_rules(): void
    {
        $pic = $this->pic();
        $item = $this->slot();

        $response = $this->actingAsVerified($pic)->post($this->uploadUrl($item), [
            'file' => UploadedFile::fake()->createWithContent('kas.pdf', $this->executableContent()),
        ]);

        $response->assertSessionHasErrors(['file' => 'Isi berkas kas.pdf tidak sesuai dengan jenis .pdf.']);
        $this->assertSame(RequestItemStatus::Menunggu, $item->fresh()->status);
    }

    public function test_upload_into_a_rahasia_request_folder_is_stored_encrypted(): void
    {
        $pic = $this->pic();
        $item = $this->slot();
        $item->documentRequest->folder->forceFill(['classification' => Classification::Rahasia])->save();

        $this->actingAsVerified($pic)->post($this->uploadUrl($item), ['file' => $this->pdfUpload()])->assertSessionHasNoErrors();

        $version = $item->fresh()->file->currentVersion;
        $this->assertTrue($version->is_encrypted);
        $this->assertNotSame(self::PdfContent, $this->storedBytes($version));
    }

    public function test_upload_into_a_rahasia_request_folder_is_refused_without_the_encryption_key(): void
    {
        $this->withoutEncryptionKey();
        $pic = $this->pic();
        $item = $this->slot();
        $item->documentRequest->folder->forceFill(['classification' => Classification::Rahasia])->save();

        $response = $this->actingAsVerified($pic)->post($this->uploadUrl($item), ['file' => $this->pdfUpload()]);

        $response->assertSessionHasErrors(['file' => 'Kunci enkripsi berkas Rahasia belum terpasang di server. Hubungi Admin IT.']);
        Storage::disk(config('berkas.disk'))->assertDirectoryEmpty('/');
    }

    public function test_accepted_slot_is_locked_for_the_pic(): void
    {
        $pic = $this->pic();
        $item = $this->slot(['status' => RequestItemStatus::Diterima]);

        $this->actingAsVerified($pic)->post($this->uploadUrl($item), ['file' => $this->pdfUpload()])->assertForbidden();
    }

    public function test_slot_of_a_closed_assignment_is_locked_for_the_pic(): void
    {
        $pic = $this->pic();
        $item = $this->slot();
        $item->documentRequest->assignment->forceFill(['status' => 'ditutup', 'closed_at' => now()])->save();

        $this->actingAsVerified($pic)->post($this->uploadUrl($item), ['file' => $this->pdfUpload()])->assertForbidden();
    }

    public function test_pic_of_another_unit_cannot_upload_through_a_direct_url(): void
    {
        $item = $this->slot();

        $this->actingAsVerified($this->pic('SD'))->post($this->uploadUrl($item), ['file' => $this->pdfUpload()])->assertForbidden();
        $this->assertNull($item->fresh()->file_id);
    }

    public function test_auditor_cannot_upload_to_a_slot(): void
    {
        $item = $this->slot();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);

        $this->actingAsVerified($dewanPengawas)->post($this->uploadUrl($item), ['file' => $this->pdfUpload()])->assertForbidden();
    }

    public function test_slot_of_another_request_is_not_found(): void
    {
        $pic = $this->pic();
        $item = $this->slot();
        $otherRequest = DocumentRequest::factory()->create(['unit_id' => $this->unit()->id]);

        $this->actingAsVerified($pic)
            ->post(route('permintaan-unit.slot.unggah', [$otherRequest, $item]), ['file' => $this->pdfUpload()])
            ->assertNotFound();
    }

    public function test_pic_downloads_a_clean_slot_version_of_the_unit_but_cannot_open_the_file_detail_page(): void
    {
        $pic = $this->pic();
        $item = $this->slot();
        $this->actingAsVerified($this->pic())->post($this->uploadUrl($item), ['file' => $this->pdfUpload()]);
        $file = $item->fresh()->file;
        $file->currentVersion->forceFill(['scan_status' => ScanStatus::Bersih])->save();

        $this->actingAsVerified($pic)->get(route('berkas.versi.unduh', [$file, $file->currentVersion]))->assertRedirectContains('/konten/unduh');
        $this->actingAsVerified($pic)->get(route('berkas.show', $file))->assertForbidden();
        $this->actingAsVerified($this->pic('SD'))->get(route('berkas.versi.unduh', [$file, $file->currentVersion]))->assertForbidden();
    }
}
