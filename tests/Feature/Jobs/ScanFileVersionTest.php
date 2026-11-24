<?php

namespace Tests\Feature\Jobs;

use App\Enums\AuditAction;
use App\Enums\ScanStatus;
use App\Jobs\ScanFileVersion;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\Scanning\ClamAvScanner;
use App\Services\Scanning\ScanFailedException;
use App\Services\Scanning\VirusScanner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
}
