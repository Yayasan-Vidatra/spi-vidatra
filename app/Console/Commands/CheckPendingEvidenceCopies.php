<?php

namespace App\Console\Commands;

use App\Enums\RequestItemStatus;
use App\Jobs\CopyAcceptedRequestItem;
use App\Models\RequestItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Menjadwalkan ulang penyalinan slot Diterima ke "04 Bukti Unit" yang tertahan lebih lama dari batas tunggu,
 * misalnya karena job sudah habis percobaannya atau queue worker mati (US-17). Batas tunggu default di atas
 * total jeda percobaan ulang job, agar job yang masih dicoba ulang tidak ikut dijadwalkan. Dijalankan scheduler.
 */
#[Signature('permintaan:periksa-salinan {--menit=60 : Batas tunggu sebelum dijadwalkan ulang}')]
#[Description('Menjadwalkan ulang salinan slot Diterima ke 04 Bukti Unit yang tertahan')]
class CheckPendingEvidenceCopies extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) $this->option('menit');
        $itemIds = [];

        RequestItem::query()
            ->where('status', RequestItemStatus::Diterima)
            ->whereNull('evidence_file_id')
            ->where(fn (Builder $query) => $query
                ->whereNull('evidence_copy_pending_since')
                ->orWhere('evidence_copy_pending_since', '<=', now()->subMinutes($minutes)))
            ->lazyById()
            ->each(function (RequestItem $item) use (&$itemIds): void {
                $item->forceFill(['evidence_copy_pending_since' => now()])->saveQuietly();
                CopyAcceptedRequestItem::dispatch($item);
                $itemIds[] = $item->id;
            });

        if ($itemIds !== []) {
            Log::warning('Salinan slot Diterima ke 04 Bukti Unit tertahan dan dijadwalkan ulang. Periksa log kegagalan job dan queue worker.', [
                'request_item_ids' => $itemIds,
                'threshold_minutes' => $minutes,
            ]);
        }

        $this->info(count($itemIds).' salinan dijadwalkan ulang ke 04 Bukti Unit.');

        return self::SUCCESS;
    }
}
