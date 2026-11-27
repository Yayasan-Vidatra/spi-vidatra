<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Enums\AuditAction;
use App\Enums\DocumentRequestStatus;
use App\Enums\EscalationStatus;
use App\Enums\EscalationType;
use App\Enums\RequestItemStatus;
use App\Models\DocumentRequest;
use App\Models\Escalation;
use App\Models\RequestItem;
use App\Notifications\RequestItemsEscalated;
use App\Services\AuditLogger;
use App\Services\EscalationRecipients;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Eskalasi tenggat Piagam (F-21, KF-12, UC-17): slot yang belum Diterima setelah hari kerja ke-10 sesudah
 * tenggat berakhir diteruskan ke Dewan Pengawas, dan Kepala SPI diberi tahu. Slot Diunggah ikut, dengan
 * keterangan tertahan di SPI. Slot dari penugasan yang sudah ditutup tidak dieskalasi. Setiap slot dieskalasi
 * sekali saja (dijaga indeks unik), tercatat di log audit, dan satu notifikasi dikirim per permintaan. Hanya
 * berjalan di hari kerja; dijalankan scheduler setiap pagi.
 */
#[Signature('permintaan:eskalasi')]
#[Description('Meneruskan slot permintaan yang melewati tenggat Piagam ke Dewan Pengawas')]
class EscalateOverdueRequestItems extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WorkingCalendar $calendar, EscalationRecipients $recipients, AuditLogger $auditLogger): int
    {
        $today = CarbonImmutable::now(config('app.business_timezone'))->startOfDay();

        if (! $calendar->isWorkingDay($today)) {
            $this->info('Hari ini bukan hari kerja; eskalasi tidak dijalankan.');

            return self::SUCCESS;
        }

        $escalatedCount = 0;

        $this->candidates($today)
            ->groupBy('document_request_id')
            ->each(function (Collection $items) use ($calendar, $recipients, $auditLogger, $today, &$escalatedCount): void {
                /** @var DocumentRequest $documentRequest */
                $documentRequest = $items->first()->documentRequest;
                $charterDeadline = $documentRequest->charterDeadline($calendar);

                if ($today->lessThanOrEqualTo($charterDeadline)) {
                    return;
                }

                $users = $recipients->all();
                $escalated = $items->filter(fn (RequestItem $item): bool => $this->escalate($item, $charterDeadline, $users->modelKeys(), $auditLogger))->values();

                if ($escalated->isEmpty()) {
                    return;
                }

                if ($users->isEmpty()) {
                    Log::warning('Eskalasi tenggat Piagam tercatat tetapi tidak ada Dewan Pengawas aktif yang menerimanya.', [
                        'document_request_id' => $documentRequest->id,
                        'request_item_ids' => $escalated->modelKeys(),
                    ]);
                }

                Notification::send($users, new RequestItemsEscalated($documentRequest, $escalated, $charterDeadline));
                $escalatedCount += $escalated->count();
            });

        $this->info("{$escalatedCount} slot diteruskan ke Dewan Pengawas.");

        return self::SUCCESS;
    }

    /**
     * Slots not yet Diterima and never escalated, of open requests in assignments that are still open. The due
     * date filter is a coarse bound (ten working days span more than ten calendar days); the exact Piagam
     * deadline is checked per request.
     *
     * @return EloquentCollection<int, RequestItem>
     */
    private function candidates(CarbonImmutable $today): EloquentCollection
    {
        return RequestItem::query()
            ->where('status', '!=', RequestItemStatus::Diterima)
            ->whereDoesntHave('escalation')
            ->whereHas('documentRequest', fn (Builder $query) => $query
                ->where('status', DocumentRequestStatus::Terbuka)
                ->whereDate('due_date', '<', $today->subDays(DocumentRequest::CharterEscalationWorkingDays)->toDateString())
                ->whereRelation('assignment', 'status', '!=', AssignmentStatus::Ditutup))
            ->with(['documentRequest.assignment', 'documentRequest.unit'])
            ->orderBy('document_request_id')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Record the escalation and its audit log row; false when another run escalated the slot first.
     *
     * @param  list<int>  $recipientIds
     */
    private function escalate(RequestItem $item, CarbonImmutable $charterDeadline, array $recipientIds, AuditLogger $auditLogger): bool
    {
        try {
            DB::transaction(function () use ($item, $charterDeadline, $recipientIds, $auditLogger): void {
                $escalation = (new Escalation)->forceFill([
                    'type' => EscalationType::PermintaanDokumen,
                    'subject_type' => $item->getMorphClass(),
                    'subject_id' => $item->id,
                    'charter_deadline' => $charterDeadline->toDateString(),
                    'escalated_at' => now(),
                    'status' => EscalationStatus::Diteruskan,
                ]);
                $escalation->save();

                $auditLogger->record(AuditAction::RequestItemEscalated, $item, [
                    'escalation_id' => $escalation->id,
                    'document_request_id' => $item->document_request_id,
                    'assignment_id' => $item->documentRequest->assignment_id,
                    'name' => $item->name,
                    'status' => $item->status->value,
                    'hold' => $item->status->holdLabel(),
                    'due_date' => $item->documentRequest->due_date->toDateString(),
                    'charter_deadline' => $charterDeadline->toDateString(),
                    'forwarded_to' => $recipientIds,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
