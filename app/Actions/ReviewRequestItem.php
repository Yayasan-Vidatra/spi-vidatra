<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\DocumentRequestStatus;
use App\Enums\EscalationStatus;
use App\Enums\RequestItemStatus;
use App\Jobs\CopyAcceptedRequestItem;
use App\Models\DocumentRequest;
use App\Models\FileVersion;
use App\Models\RequestItem;
use App\Models\User;
use App\Notifications\RequestItemReviewed;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Auditor menandai slot Diterima atau Perlu Perbaikan dengan catatan (US-17, UC-08). Diterima bersifat final:
 * slot terkunci untuk PIC dan versi yang diterima disalin ke "04 Bukti Unit" oleh job antrean. Permintaan
 * menjadi Selesai saat semua slotnya Diterima, dan eskalasi slot ke Dewan Pengawas, bila ada, selesai. PIC unit
 * diberi tahu hasilnya setelah commit.
 */
class ReviewRequestItem
{
    public const string NotReviewableMessage = 'Slot ini sudah diperiksa, sedang menunggu unggahan PIC, atau penugasannya sudah ditutup. Muat ulang halaman.';

    public const string NewerVersionMessage = 'PIC baru saja mengunggah versi baru. Muat ulang halaman dan periksa versi terbaru.';

    public const string WaitingForScanMessage = 'Berkas belum bisa diperiksa karena belum dinyatakan bersih oleh pemindai antivirus.';

    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(User $reviewer, RequestItem $item, RequestItemStatus $result, string $note, int $versionId): RequestItem
    {
        $item = DB::transaction(fn (): RequestItem => $this->review($reviewer, $item, $result, $note, $versionId));

        Notification::send($item->documentRequest->unit->picUsers, new RequestItemReviewed($item));

        return $item;
    }

    private function review(User $reviewer, RequestItem $item, RequestItemStatus $result, string $note, int $versionId): RequestItem
    {
        // Kunci slot agar unggahan ulang PIC dan pemeriksaan oleh auditor lain tidak saling menimpa.
        $item = RequestItem::query()->with('documentRequest.unit')->lockForUpdate()->findOrFail($item->id);

        if (Gate::forUser($reviewer)->denies('review', $item)) {
            throw ValidationException::withMessages(['result' => self::NotReviewableMessage]);
        }

        $version = FileVersion::query()->findOrFail($item->file->current_version_id);

        if ($version->id !== $versionId) {
            throw ValidationException::withMessages(['result' => self::NewerVersionMessage]);
        }

        if (! $version->hasPassedScan()) {
            throw ValidationException::withMessages(['result' => self::WaitingForScanMessage]);
        }

        $isAccepted = $result === RequestItemStatus::Diterima;

        $item->forceFill([
            'status' => $result,
            'reviewer_note' => $note,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'accepted_version_id' => $isAccepted ? $version->id : null,
            'evidence_copy_pending_since' => $isAccepted ? now() : null,
        ])->save();

        $documentRequest = $item->documentRequest;

        $this->auditLogger->record(AuditAction::RequestItemReviewed, $item, [
            'document_request_id' => $documentRequest->id,
            'unit' => $documentRequest->unit->code,
            'name' => $item->name,
            'result' => $result->value,
            'note' => $note,
            'file_id' => $item->file_id,
            'version' => $version->version_number,
            'version_id' => $version->id,
        ], $reviewer);

        if ($isAccepted) {
            CopyAcceptedRequestItem::dispatch($item)->afterCommit();
            $this->resolveEscalation($item, $reviewer);
            $this->completeWhenAllAccepted($documentRequest, $reviewer);
        }

        return $item;
    }

    /**
     * A slot that was escalated to Dewan Pengawas is no longer a blocker once Diterima (UC-17).
     */
    private function resolveEscalation(RequestItem $item, User $reviewer): void
    {
        $escalation = $item->escalation()->where('status', EscalationStatus::Diteruskan)->lockForUpdate()->first();

        if ($escalation === null) {
            return;
        }

        $escalation->forceFill(['status' => EscalationStatus::Selesai, 'resolved_at' => now()])->save();

        $this->auditLogger->record(AuditAction::RequestItemEscalationResolved, $item, [
            'escalation_id' => $escalation->id,
            'document_request_id' => $item->document_request_id,
            'name' => $item->name,
            'escalated_at' => $escalation->escalated_at->toIso8601String(),
        ], $reviewer);
    }

    private function completeWhenAllAccepted(DocumentRequest $documentRequest, User $reviewer): void
    {
        // Kunci permintaan agar dua slot terakhir yang diterima bersamaan tetap menghasilkan satu "Selesai";
        // slot dibaca dengan locking read supaya status yang baru di-commit transaksi lain ikut terlihat.
        $documentRequest = DocumentRequest::query()->lockForUpdate()->findOrFail($documentRequest->id);
        $isComplete = $documentRequest->status !== DocumentRequestStatus::Selesai
            && ! $documentRequest->items()->where('status', '!=', RequestItemStatus::Diterima)->sharedLock()->exists();

        if (! $isComplete) {
            return;
        }

        $documentRequest->forceFill(['status' => DocumentRequestStatus::Selesai])->save();

        $this->auditLogger->record(AuditAction::DocumentRequestCompleted, $documentRequest, [
            'assignment_id' => $documentRequest->assignment_id,
            'unit_id' => $documentRequest->unit_id,
            'item_count' => $documentRequest->items()->count(),
        ], $reviewer);
    }
}
