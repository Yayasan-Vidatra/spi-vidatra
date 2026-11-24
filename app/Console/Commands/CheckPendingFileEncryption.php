<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Jobs\SyncFileEncryption;
use App\Models\File;
use App\Services\AuditLogger;
use App\Services\Encryption\FileCipher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mendeteksi berkas yang isinya belum mengikuti label lebih lama dari batas tunggu, misalnya karena
 * queue worker masih menjalankan kode lama atau mati, lalu menjadwalkan ulang enkripsi ulangnya (US-12).
 * Berkas yang tidak pernah dijadwalkan sama sekali (tanpa penanda) juga ikut dijadwalkan. Dijalankan scheduler.
 */
#[Signature('berkas:periksa-enkripsi {--menit=10 : Batas tunggu sebelum dijadwalkan ulang}')]
#[Description('Menjadwalkan ulang enkripsi ulang berkas yang tertahan')]
class CheckPendingFileEncryption extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FileCipher $cipher, AuditLogger $auditLogger): int
    {
        $minutes = (int) $this->option('menit');
        $overdue = File::withTrashed()
            ->withEncryptionMismatch()
            ->where(fn (Builder $query) => $query
                ->whereNull('encryption_pending_since')
                ->orWhere('encryption_pending_since', '<=', now()->subMinutes($minutes)));

        if (! $cipher->isAvailable()) {
            $count = (clone $overdue)->count();

            if ($count > 0) {
                Log::error('Kunci enkripsi berkas Rahasia tidak ada; enkripsi ulang tidak bisa dijadwalkan ulang.', ['file_count' => $count]);
            }

            $this->error("Kunci enkripsi belum terpasang; {$count} berkas menunggu enkripsi ulang.");

            return $count > 0 ? self::FAILURE : self::SUCCESS;
        }

        $fileIds = [];

        $overdue->lazyById()->each(function (File $file) use ($auditLogger, &$fileIds): void {
            DB::transaction(function () use ($file, $auditLogger): void {
                $pendingSince = $file->encryption_pending_since;

                $file->forceFill(['encryption_pending_since' => now()])->saveQuietly();

                $auditLogger->record(AuditAction::FileEncryptionRescheduled, $file, [
                    'pending_since' => $pendingSince?->toIso8601String(),
                ]);

                SyncFileEncryption::dispatch($file)->afterCommit();
            });

            $fileIds[] = $file->id;
        });

        if ($fileIds !== []) {
            Log::warning('Enkripsi ulang berkas tertahan dan dijadwalkan ulang. Pastikan queue worker berjalan dengan kode terbaru.', [
                'file_ids' => $fileIds,
                'threshold_minutes' => $minutes,
            ]);
        }

        $this->info(count($fileIds).' berkas dijadwalkan ulang untuk enkripsi ulang.');

        return self::SUCCESS;
    }
}
