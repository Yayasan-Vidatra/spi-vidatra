<?php

namespace Tests\Feature\Jobs;

use App\Enums\AuditAction;
use App\Enums\RequestItemStatus;
use App\Enums\ScanStatus;
use App\Jobs\ScanFileVersion;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\RequestItem;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\RequestItemInfected;
use App\Services\Scanning\ClamAvScanner;
use App\Services\Scanning\ScanFailedException;
use App\Services\Scanning\VirusScanner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\Fakes\FakeVirusScanner;
use Tests\TestCase;

class ScanFileVersionTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use LazilyRefreshDatabase;

    private function pendingFile(): File
    {
        return $this->storedFile(Folder::factory()->create(), User::factory()->create(), ScanStatus::Menunggu);
    }

    public function test_clean_result_marks_the_version_clean(): void
    {
        $this->freezeSecond();
        $this->fakeFileDisk();
        $this->app->instance(VirusScanner::class, FakeVirusScanner::clean());
        $file = $this->pendingFile();

        ScanFileVersion::dispatchSync($file->currentVersion);

        $version = $file->currentVersion->fresh();
        $this->assertSame(ScanStatus::Bersih, $version->scan_status);
        $this->assertEquals(now(), $version->scanned_at);
        $this->assertAuditLogged(AuditAction::FileScanned, $file, ['version' => 1, 'version_id' => $version->id, 'result' => 'bersih']);
    }

    public function test_infected_result_moves_the_content_to_quarantine(): void
    {
        $this->fakeFileDisk();
        $this->app->instance(VirusScanner::class, FakeVirusScanner::infected('Win.Test.EICAR_HDB-1'));
        $file = $this->pendingFile();
        $originalPath = $file->currentVersion->storage_path;

        ScanFileVersion::dispatchSync($file->currentVersion);

        $version = $file->currentVersion->fresh();
        $this->assertSame(ScanStatus::Terinfeksi, $version->scan_status);
        $this->assertSame('karantina/'.$version->stored_name, $version->storage_path);
        Storage::disk(config('berkas.disk'))->assertMissing($originalPath);
        $this->assertSame(self::PdfContent, Storage::disk(config('berkas.disk'))->get($version->storage_path));
        $this->assertAuditLogged(AuditAction::FileQuarantined, $file, [
            'version' => 1,
            'version_id' => $version->id,
            'signature' => 'Win.Test.EICAR_HDB-1',
        ]);
    }

    public function test_scanner_failure_keeps_the_version_pending(): void
    {
        $this->fakeFileDisk();
        $this->app->instance(VirusScanner::class, FakeVirusScanner::failing());
        $file = $this->pendingFile();

        $this->assertThrows(fn () => ScanFileVersion::dispatchSync($file->currentVersion), ScanFailedException::class);

        $this->assertSame(ScanStatus::Menunggu, $file->currentVersion->fresh()->scan_status);
        $this->assertSame(0, AuditLog::query()->whereIn('action', [AuditAction::FileScanned, AuditAction::FileQuarantined])->count());
    }

    public function test_clamd_size_limit_reply_keeps_the_version_pending(): void
    {
        $this->fakeFileDisk();
        $fakeClamd = new Process([PHP_BINARY, base_path('tests/Fixtures/fake-clamd.php'), 'INSTREAM size limit exceeded. ERROR']);
        $fakeClamd->start();
        $fakeClamd->waitUntil(fn (string $type, string $output): bool => str_contains($output, "\n"));
        $this->app->instance(VirusScanner::class, new ClamAvScanner('tcp://'.trim($fakeClamd->getOutput()), 5));
        $file = $this->pendingFile();

        try {
            $this->assertThrows(fn () => ScanFileVersion::dispatchSync($file->currentVersion), ScanFailedException::class);
        } finally {
            $fakeClamd->stop(0);
        }

        $version = $file->currentVersion->fresh();
        $this->assertSame(ScanStatus::Menunggu, $version->scan_status);
        $this->assertSame('versi/'.$version->stored_name, $version->storage_path);
    }

    public function test_version_already_scanned_is_not_scanned_again(): void
    {
        $this->fakeFileDisk();
        $scanner = FakeVirusScanner::infected();
        $this->app->instance(VirusScanner::class, $scanner);
        $file = $this->storedFile(Folder::factory()->create(), User::factory()->create(), ScanStatus::Bersih);

        ScanFileVersion::dispatchSync($file->currentVersion);

        $this->assertSame([], $scanner->scannedContents);
        $this->assertSame(ScanStatus::Bersih, $file->currentVersion->fresh()->scan_status);
    }

    public function test_encrypted_version_is_scanned_as_its_original_content(): void
    {
        $this->fakeFileDisk();
        $scanner = FakeVirusScanner::clean();
        $this->app->instance(VirusScanner::class, $scanner);
        $file = $this->storedFile(Folder::factory()->confidential()->create(), User::factory()->create(), ScanStatus::Menunggu, content: 'isi asli rahasia', encrypted: true);

        ScanFileVersion::dispatchSync($file->currentVersion);

        $this->assertSame(['isi asli rahasia'], $scanner->scannedContents);
        $this->assertSame(ScanStatus::Bersih, $file->currentVersion->fresh()->scan_status);
    }

    public function test_infected_encrypted_version_stays_encrypted_in_quarantine(): void
    {
        $this->fakeFileDisk();
        $this->app->instance(VirusScanner::class, FakeVirusScanner::infected());
        $file = $this->storedFile(Folder::factory()->confidential()->create(), User::factory()->create(), ScanStatus::Menunggu, content: 'isi asli rahasia', encrypted: true);

        ScanFileVersion::dispatchSync($file->currentVersion);

        $version = $file->currentVersion->fresh();
        $this->assertStringStartsWith('karantina/', $version->storage_path);
        $this->assertStringNotContainsString('isi asli rahasia', $this->storedBytes($version));
    }

    /**
     * @return array{RequestItem, File}
     */
    private function uploadedSlot(): array
    {
        $unit = Unit::query()->where('code', 'SMP')->sole();
        $documentRequest = DocumentRequest::factory()->create(['unit_id' => $unit->id]);
        $file = $this->storedFile($documentRequest->folder, User::factory()->picOf($unit)->create(), ScanStatus::Menunggu);
        $item = RequestItem::factory()->for($documentRequest)->withStatus(RequestItemStatus::Diunggah)->create([
            'file_id' => $file->id,
            'reviewer_note' => 'Catatan auditor sebelumnya',
        ]);

        return [$item, $file];
    }

    public function test_infected_slot_upload_returns_the_slot_to_perlu_perbaikan_with_a_system_note(): void
    {
        Notification::fake();
        $this->fakeFileDisk();
        $this->app->instance(VirusScanner::class, FakeVirusScanner::infected());
        [$item, $file] = $this->uploadedSlot();
        $pics = User::query()->where('unit_id', $item->documentRequest->unit_id)->get();
        $otherUnitPic = User::factory()->picOf(Unit::query()->where('code', 'SD')->sole())->create();

        ScanFileVersion::dispatchSync($file->currentVersion);

        $item->refresh();
        $this->assertSame(RequestItemStatus::PerluPerbaikan, $item->status);
        $this->assertSame(RequestItem::InfectedSystemNote, $item->system_note);
        $this->assertSame('Catatan auditor sebelumnya', $item->reviewer_note);
        $log = $this->assertAuditLogged(AuditAction::RequestItemInfected, $item, [
            'document_request_id' => $item->document_request_id,
            'name' => $item->name,
            'version' => 1,
            'version_id' => $file->current_version_id,
            'note_source' => 'sistem',
        ]);
        $this->assertNull($log->user_id);
        Notification::assertSentTo($pics, RequestItemInfected::class);
        Notification::assertNotSentTo($otherUnitPic, RequestItemInfected::class);
    }

    public function test_scanner_failure_keeps_the_slot_waiting_for_the_scan(): void
    {
        $this->fakeFileDisk();
        $this->app->instance(VirusScanner::class, FakeVirusScanner::failing());
        [$item, $file] = $this->uploadedSlot();

        $this->assertThrows(fn () => ScanFileVersion::dispatchSync($file->currentVersion), ScanFailedException::class);

        $this->assertSame(RequestItemStatus::Diunggah, $item->fresh()->status);
        $this->assertNull($item->fresh()->system_note);
    }

    public function test_infected_older_version_does_not_change_a_slot_that_already_has_a_newer_upload(): void
    {
        $this->fakeFileDisk();
        $this->app->instance(VirusScanner::class, FakeVirusScanner::infected());
        [$item, $file] = $this->uploadedSlot();
        $olderVersion = $file->currentVersion;
        $newerVersion = FileVersion::factory()->for($file)->create(['version_number' => 2, 'scan_status' => ScanStatus::Menunggu]);
        $file->forceFill(['current_version_id' => $newerVersion->id])->save();

        ScanFileVersion::dispatchSync($olderVersion);

        $this->assertSame(RequestItemStatus::Diunggah, $item->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => AuditAction::RequestItemInfected->value]);
    }
}
