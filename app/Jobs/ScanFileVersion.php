<?php

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Enums\ScanStatus;
use App\Models\FileVersion;
use App\Services\AuditLogger;
use App\Services\FileVersionContent;
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

    public function handle(VirusScanner $scanner, FileVersionContent $content, AuditLogger $auditLogger): void
    {
        $version = $this->version->fresh(['file']);

        if ($version === null || $version->scan_status !== ScanStatus::Menunggu) {
            return;
        }

        // Isi Rahasia didekripsi sambil dialirkan, sehingga clamd memindai isi asli tanpa salinan polos di disk.
        $result = $scanner->scan($content->read($version));

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

        DB::transaction(function () use ($version, $signature, $auditLogger, $disk): void {
            // Kunci baris agar lokasi simpan tidak sedang diganti job enkripsi ulang.
            $locked = FileVersion::query()->lockForUpdate()->findOrFail($version->id);
            $originalPath = $locked->storage_path;
            $quarantinePath = 'karantina/'.$locked->stored_name;

            $disk->move($originalPath, $quarantinePath);

            try {
                $locked->forceFill([
                    'scan_status' => ScanStatus::Terinfeksi,
                    'scanned_at' => now(),
                    'scan_signature' => $signature,
                    'storage_path' => $quarantinePath,
                ])->save();

                $auditLogger->record(AuditAction::FileQuarantined, $version->file, [
                    'version' => $locked->version_number,
                    'version_id' => $locked->id,
                    'signature' => $signature,
                ]);
            } catch (Throwable $exception) {
                $disk->move($quarantinePath, $originalPath);

                throw $exception;
            }
        });
    }
}
