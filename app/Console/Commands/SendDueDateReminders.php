<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Enums\DocumentRequestStatus;
use App\Enums\RequestItemStatus;
use App\Models\DocumentRequest;
use App\Notifications\DocumentRequestDueSoon;
use App\Notifications\DocumentRequestDueToday;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Pengingat tenggat ke PIC unit (US-18): H-3, yaitu 3 hari kerja sebelum tenggat, dan hari-H. Hanya untuk
 * permintaan Terbuka di penugasan yang belum ditutup dan masih punya slot Menunggu atau Perlu Perbaikan.
 * Masing-masing terkirim sekali, dijaga kolom *_reminded_at yang dikosongkan lagi saat tenggat diubah. Bila
 * jadwal sempat terlewat, H-3 dikirim menyusul selama tenggat belum tiba. Dijalankan scheduler setiap pagi.
 */
#[Signature('permintaan:kirim-pengingat')]
#[Description('Mengirim pengingat tenggat H-3 dan hari-H ke PIC unit')]
class SendDueDateReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WorkingCalendar $calendar): int
    {
        $today = CarbonImmutable::now(config('app.business_timezone'))->startOfDay();
        $dueSoonCount = 0;
        $dueTodayCount = 0;

        DocumentRequest::query()
            ->where('status', DocumentRequestStatus::Terbuka)
            ->whereRelation('assignment', 'status', '!=', AssignmentStatus::Ditutup)
            ->whereDate('due_date', '>=', $today->toDateString())
            ->whereDate('due_date', '<=', $calendar->addWorkingDays($today, 3)->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('due_soon_reminded_at')->orWhereNull('due_today_reminded_at'))
            ->withCount(['items as pending_item_count' => fn (Builder $query) => $query
                ->whereIn('status', [RequestItemStatus::Menunggu, RequestItemStatus::PerluPerbaikan])])
            ->with('unit')
            ->lazyById()
            ->each(function (DocumentRequest $documentRequest) use ($calendar, $today, &$dueSoonCount, &$dueTodayCount): void {
                if ($documentRequest->pending_item_count === 0) {
                    return;
                }

                $isDueToday = $documentRequest->due_date->toDateString() === $today->toDateString();

                if (! $isDueToday && $calendar->subWorkingDays($documentRequest->due_date, 3)->greaterThan($today)) {
                    return;
                }

                $column = $isDueToday ? 'due_today_reminded_at' : 'due_soon_reminded_at';

                // Tandai lebih dulu secara atomik agar dua proses bersamaan tidak mengirim pengingat yang sama.
                if (DocumentRequest::query()->whereKey($documentRequest->id)->whereNull($column)->update([$column => now()]) === 0) {
                    return;
                }

                if ($isDueToday) {
                    Notification::send($documentRequest->unit->picUsers, new DocumentRequestDueToday($documentRequest, $documentRequest->pending_item_count));
                    $dueTodayCount++;
                } else {
                    Notification::send($documentRequest->unit->picUsers, new DocumentRequestDueSoon($documentRequest, $documentRequest->pending_item_count));
                    $dueSoonCount++;
                }
            });

        $this->info("{$dueSoonCount} pengingat H-3 dan {$dueTodayCount} pengingat hari-H dikirim.");

        return self::SUCCESS;
    }
}
