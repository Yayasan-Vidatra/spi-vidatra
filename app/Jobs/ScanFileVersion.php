<?php

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Enums\ScanStatus;
use App\Models\FileVersion;
use App\Services\AuditLogger;
use App\Services\Scanning\VirusScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Memindai satu versi berkas dengan antivirus (US-09). Bersih membuka akses unduh; terinfeksi memindahkan
 * isinya ke karantina. Jika pemindai gagal memberi putusan, job diulang dan versi tetap menunggu.
 */
class ScanFileVersion implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600, 1800];

    /**
     * Di bawah retry_after antrean (90 detik), dengan sisa waktu di atas batas waktu clamd.
     */
    public int $timeout = 60;

    public function __construct(public FileVersion $version) {}

    public function handle(VirusScanner $scanner, AuditLogger $auditLogger): void
    {
        $version = $this->version->fresh(['file']);

        if ($version === null || $version->scan_status !== ScanStatus::Menunggu) {
            return;
        }

        $disk = Storage::disk(config('berkas.disk'));
        $stream = $disk->readStream($version->storage_path);

        try {
            $result = $scanner->scan($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($result->isInfected) {
            $this->quarantine($version, $result->signature, $auditLogger);

            return;
        }

        DB::transaction(function () use ($version, $auditLogger): void {
            $version->forceFill(['scan_status' => ScanStatus::Bersih, 'scanned_at' => now()])->save();

            $auditLogger->record(AuditAction::FileScanned, $version->file, [
                'version' => $version->version_number,
                'version_id' => $version->id,
                'result' => ScanStatus::Bersih->value,
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Pemindaian antivirus gagal; versi berkas tetap menunggu pindai.', [
            'file_version_id' => $this->version->id,
            'exception' => $exception,
        ]);
    }

    private function quarantine(FileVersion $version, string $signature, AuditLogger $auditLogger): void
    {
        $disk = Storage::disk(config('berkas.disk'));
        $originalPath = $version->storage_path;
        $quarantinePath = 'karantina/'.$version->stored_name;

        $disk->move($originalPath, $quarantinePath);

        try {
            DB::transaction(function () use ($version, $signature, $quarantinePath, $auditLogger): void {
                $version->forceFill([
                    'scan_status' => ScanStatus::Terinfeksi,
                    'scanned_at' => now(),
                    'scan_signature' => $signature,
                    'storage_path' => $quarantinePath,
                ])->save();

                $auditLogger->record(AuditAction::FileQuarantined, $version->file, [
                    'version' => $version->version_number,
                    'version_id' => $version->id,
                    'signature' => $signature,
                ]);
            });
        } catch (Throwable $exception) {
            $disk->move($quarantinePath, $originalPath);

            throw $exception;
        }
    }
}
