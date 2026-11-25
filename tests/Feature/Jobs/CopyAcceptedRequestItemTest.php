<?php

namespace Tests\Feature\Jobs;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Enums\FolderPurpose;
use App\Enums\RequestItemStatus;
use App\Enums\RoleSlug;
use App\Enums\ScanStatus;
use App\Jobs\CopyAcceptedRequestItem;
use App\Jobs\ScanFileVersion;
use App\Models\Assignment;
use App\Models\AssignmentMember;
use App\Models\DocumentRequest;
use App\Models\File;
use App\Models\Folder;
use App\Models\RequestItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class CopyAcceptedRequestItemTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    private Assignment $assignment;

    private Folder $evidenceFolder;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeFileDisk();
        Queue::fake([ScanFileVersion::class]);

        $this->assignment = Assignment::factory()->create();
        $this->evidenceFolder = Folder::factory()->for($this->assignment)->forPurpose(FolderPurpose::BuktiUnit)->create();
        $this->reviewer = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
    }

    private function acceptedSlot(string $name = 'Laporan kas Oktober', string $fileName = 'Laporan kas Oktober.pdf', ?Folder $requestFolder = null, Classification $classification = Classification::Internal, bool $encrypted = false): RequestItem
    {
        $documentRequest = DocumentRequest::factory()->for($this->assignment)->create([
            'unit_id' => Unit::query()->where('code', 'SMP')->value('id'),
            ...($requestFolder === null ? [] : ['folder_id' => $requestFolder->id]),
        ]);
        $file = $this->storedFile($documentRequest->folder, User::factory()->picOf($documentRequest->unit)->create(), ScanStatus::Bersih, [
            'original_name' => $fileName,
            'classification' => $classification,
        ], content: 'isi dokumen unit', encrypted: $encrypted);

        return RequestItem::factory()->for($documentRequest)->withStatus(RequestItemStatus::Diterima)->create([
            'name' => $name,
            'file_id' => $file->id,
            'reviewer_note' => 'Lengkap.',
            'reviewed_by' => $this->reviewer->id,
            'reviewed_at' => now(),
            'accepted_version_id' => $file->current_version_id,
            'evidence_copy_pending_since' => now(),
        ]);
    }

    public function test_accepted_version_is_copied_to_bukti_unit_as_a_new_file_named_after_the_unit_and_document(): void
    {
        $item = $this->acceptedSlot();

        CopyAcceptedRequestItem::dispatchSync($item);

        $item->refresh();
        $copy = $item->evidenceFile;
        $version = $copy->currentVersion;
        $source = $item->acceptedVersion;
        $this->assertSame('SMP - Laporan kas Oktober.pdf', $copy->original_name);
        $this->assertSame($this->evidenceFolder->id, $copy->folder_id);
        $this->assertSame(Classification::Internal, $copy->classification);
        $this->assertSame($this->reviewer->id, $copy->created_by);
        $this->assertSame(1, $version->version_number);
        $this->assertSame($this->reviewer->id, $version->uploaded_by);
        $this->assertSame(ScanStatus::Menunggu, $version->scan_status);
        $this->assertNotSame($source->storage_path, $version->storage_path);
        $this->assertSame('isi dokumen unit', $this->storedBytes($version));
        $this->assertSame($source->sha256, $version->sha256);
        $this->assertNull($item->evidence_copy_pending_since);
        $this->assertFalse($item->isEvidenceCopyPending());
        $this->assertSame(RequestItemStatus::Diterima, $item->status);
        Queue::assertPushed(ScanFileVersion::class, fn (ScanFileVersion $job): bool => $job->version->is($version));
        $this->assertSame($source->id, $this->assertAuditLogged(AuditAction::FileUploaded, $copy)->properties['copied_from_version_id']);
        $log = $this->assertAuditLogged(AuditAction::RequestItemCopiedToEvidence, $item, [
            'document_request_id' => $item->document_request_id,
            'name' => 'Laporan kas Oktober',
            'source_file_id' => $item->file_id,
            'source_version_id' => $source->id,
            'evidence_file_id' => $copy->id,
            'evidence_name' => 'SMP - Laporan kas Oktober.pdf',
            'folder_id' => $this->evidenceFolder->id,
        ]);
        $this->assertNull($log->user_id);
    }

    public function test_accepting_through_h07_places_the_copy_in_bukti_unit_and_shows_it_on_the_page(): void
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->withTwoFactor()->acceptedConfidentiality()->create();
        AssignmentMember::factory()->for($this->assignment)->create(['user_id' => $auditor->id]);
        $item = $this->acceptedSlot();
        $item->forceFill(['status' => RequestItemStatus::Diunggah, 'accepted_version_id' => null, 'evidence_copy_pending_since' => null])->save();
        $showUrl = route('penugasan.permintaan.show', [$this->assignment, $item->documentRequest]);

        $this->actingAsVerified($auditor)
            ->post(route('penugasan.permintaan.slot.periksa', [$this->assignment, $item->documentRequest, $item]), [
                'result' => 'diterima', 'note' => 'Lengkap.', 'version_id' => $item->file->current_version_id,
            ])
            ->assertSessionHasNoErrors();

        $copy = $item->fresh()->evidenceFile;
        $this->assertSame('SMP - Laporan kas Oktober.pdf', $copy->original_name);
        $this->actingAsVerified($auditor)->get($showUrl)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.0.evidence_copy_pending', false)
            ->where('items.0.evidence_file', ['id' => $copy->id, 'name' => 'SMP - Laporan kas Oktober.pdf'])
        );
    }

    public function test_copy_keeps_the_extension_of_the_accepted_file(): void
    {
        $item = $this->acceptedSlot('Rekap gaji', 'Rekap gaji.xlsx');

        CopyAcceptedRequestItem::dispatchSync($item);

        $this->assertSame('SMP - Rekap gaji.xlsx', $item->fresh()->evidenceFile->original_name);
    }

    public function test_name_already_used_in_bukti_unit_gets_a_numbered_suffix_while_trashed_files_free_their_name(): void
    {
        File::factory()->for($this->evidenceFolder)->create(['original_name' => 'SMP - Laporan kas Oktober.pdf']);
        File::factory()->for($this->evidenceFolder)->create(['original_name' => 'SMP - Laporan kas Oktober (2).pdf']);
        File::factory()->for($this->evidenceFolder)->create(['original_name' => 'SMP - Laporan kas Oktober (3).pdf'])->delete();
        $item = $this->acceptedSlot();

        CopyAcceptedRequestItem::dispatchSync($item);

        $this->assertSame('SMP - Laporan kas Oktober (3).pdf', $item->fresh()->evidenceFile->original_name);
    }

    public function test_running_the_job_twice_leaves_one_copy(): void
    {
        $item = $this->acceptedSlot();

        CopyAcceptedRequestItem::dispatchSync($item);
        CopyAcceptedRequestItem::dispatchSync($item);

        $this->assertSame(1, File::query()->where('folder_id', $this->evidenceFolder->id)->sole()->versions()->count());
        $this->assertCount(2, Storage::disk(config('berkas.disk'))->allFiles('versi'));
    }

    public function test_slot_that_is_not_accepted_is_not_copied(): void
    {
        $item = $this->acceptedSlot();
        $item->forceFill(['status' => RequestItemStatus::PerluPerbaikan])->save();

        CopyAcceptedRequestItem::dispatchSync($item);

        $this->assertNull($item->fresh()->evidence_file_id);
        $this->assertSame(0, File::query()->where('folder_id', $this->evidenceFolder->id)->count());
    }

    public function test_copy_follows_the_bukti_unit_label_not_the_slot_label(): void
    {
        $item = $this->acceptedSlot(classification: Classification::Rahasia, encrypted: true);

        CopyAcceptedRequestItem::dispatchSync($item);

        $version = $item->fresh()->evidenceFile->currentVersion;
        $this->assertSame(Classification::Internal, $version->file->classification);
        $this->assertFalse($version->is_encrypted);
        $this->assertSame('isi dokumen unit', $this->storedBytes($version));
    }

    public function test_copy_into_a_rahasia_bukti_unit_is_labelled_rahasia_and_stored_encrypted(): void
    {
        $this->evidenceFolder->forceFill(['classification' => Classification::Rahasia])->save();
        $item = $this->acceptedSlot();

        CopyAcceptedRequestItem::dispatchSync($item);

        $copy = $item->fresh()->evidenceFile;
        $this->assertSame(Classification::Rahasia, $copy->classification);
        $this->assertTrue($copy->currentVersion->is_encrypted);
        $this->assertStringNotContainsString('isi dokumen unit', $this->storedBytes($copy->currentVersion));
    }

    public function test_missing_encryption_key_leaves_the_copy_pending_without_stored_content(): void
    {
        $this->evidenceFolder->forceFill(['classification' => Classification::Rahasia])->save();
        $item = $this->acceptedSlot();
        $this->withoutEncryptionKey();

        $this->assertThrows(fn () => CopyAcceptedRequestItem::dispatchSync($item), RuntimeException::class);

        $item->refresh();
        $this->assertSame(RequestItemStatus::Diterima, $item->status);
        $this->assertTrue($item->isEvidenceCopyPending());
        $this->assertSame(0, File::query()->where('folder_id', $this->evidenceFolder->id)->count());
        $this->assertCount(1, Storage::disk(config('berkas.disk'))->allFiles('versi'));
    }

    public function test_assignment_without_bukti_unit_leaves_the_copy_pending(): void
    {
        $item = $this->acceptedSlot();
        $this->evidenceFolder->forceFill(['folder_template_id' => null])->save();

        $this->assertThrows(fn () => CopyAcceptedRequestItem::dispatchSync($item), RuntimeException::class, 'Penugasan ini tidak punya folder 04 Bukti Unit.');

        $this->assertTrue($item->fresh()->isEvidenceCopyPending());
    }

    public function test_final_failure_is_logged(): void
    {
        Log::spy();
        $item = $this->acceptedSlot();
        $exception = new RuntimeException('disk penuh');

        (new CopyAcceptedRequestItem($item))->failed($exception);

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, '04 Bukti Unit')
            && $context['request_item_id'] === $item->id
            && $context['exception'] === $exception);
    }
}
