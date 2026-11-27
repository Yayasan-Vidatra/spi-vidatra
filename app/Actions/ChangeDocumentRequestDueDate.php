<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Notifications\DocumentRequestDueDateChanged;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Mengubah tenggat permintaan (US-18). Penanda pengingat H-3 dan hari-H dikosongkan agar pengingat untuk tenggat
 * baru bisa terkirim, dan waktu perubahan dicatat untuk aturan H-3. Eskalasi yang sudah terkirim tidak disentuh
 * (sekali per slot). Perubahan dan alasannya dicatat di log; PIC unit diberi tahu setelah commit.
 */
class ChangeDocumentRequestDueDate
{
    public const string NotChangeableMessage = 'Tenggat permintaan ini tidak bisa diubah lagi. Muat ulang halaman.';

    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(User $user, DocumentRequest $documentRequest, string $dueDate, string $reason): DocumentRequest
    {
        [$documentRequest, $previousDueDate] = DB::transaction(function () use ($user, $documentRequest, $dueDate, $reason): array {
            // Kunci permintaan agar perubahan tenggat tidak bertabrakan dengan slot terakhir yang diterima.
            $locked = DocumentRequest::query()->with(['assignment', 'unit'])->lockForUpdate()->findOrFail($documentRequest->id);

            if (Gate::forUser($user)->denies('updateDueDate', $locked)) {
                throw ValidationException::withMessages(['due_date' => self::NotChangeableMessage]);
            }

            $previousDueDate = $locked->due_date->toDateString();

            $locked->forceFill([
                'due_date' => $dueDate,
                'due_date_changed_at' => now(),
                'due_soon_reminded_at' => null,
                'due_today_reminded_at' => null,
            ])->save();

            $this->auditLogger->record(AuditAction::DocumentRequestDueDateChanged, $locked, [
                'assignment_id' => $locked->assignment_id,
                'unit' => $locked->unit->code,
                'previous_due_date' => $previousDueDate,
                'due_date' => $dueDate,
                'reason' => $reason,
            ], $user);

            return [$locked, $previousDueDate];
        });

        Notification::send($documentRequest->unit->picUsers, new DocumentRequestDueDateChanged($documentRequest, $previousDueDate, $reason));

        return $documentRequest;
    }
}
