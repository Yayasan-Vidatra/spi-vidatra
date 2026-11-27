<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\DocumentRequestStatus;
use App\Enums\FolderPurpose;
use App\Enums\RequestItemStatus;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\Folder;
use App\Models\RequestItem;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\DocumentRequestReceived;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;

/**
 * Membuat permintaan dokumen ke satu unit (US-15): subfolder permintaan di "02 Permintaan Dokumen", satu slot
 * per dokumen, dan log audit dalam satu transaksi. PIC aktif unit tujuan diberi notifikasi setelah commit.
 */
class CreateDocumentRequest
{
    private const int MaxAttempts = 3;

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{unit_id: int, due_date: string, items: list<array{name: string, description?: string|null, formats: list<string>}>}  $data
     */
    public function handle(User $creator, Assignment $assignment, array $data): DocumentRequest
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $documentRequest = DB::transaction(fn (): DocumentRequest => $this->create($creator, $assignment, $data));

                break;
            } catch (UniqueConstraintViolationException $exception) {
                // Pembuatan lain di penugasan yang sama mendapat nomor urut ini lebih dulu; ambil nomor berikutnya.
                if ($attempt >= self::MaxAttempts) {
                    throw $exception;
                }
            }
        }

        // Satu unit boleh punya lebih dari satu PIC; semuanya diberi tahu.
        Notification::send($documentRequest->unit->picUsers, new DocumentRequestReceived($documentRequest));

        return $documentRequest;
    }

    /**
     * @param  array{unit_id: int, due_date: string, items: list<array{name: string, description?: string|null, formats: list<string>}>}  $data
     */
    private function create(User $creator, Assignment $assignment, array $data): DocumentRequest
    {
        $unit = Unit::query()->findOrFail($data['unit_id']);
        $requestsFolder = $assignment->standardFolder(FolderPurpose::PermintaanDokumen)
            ?? throw new LogicException('Penugasan tidak punya folder 02 Permintaan Dokumen.');
        $sequenceNumber = $this->nextSequenceNumber($assignment);

        // Subfolder mewarisi label folder 02, sehingga isi Rahasia tetap terenkripsi (US-12).
        $folder = (new Folder)->forceFill([
            'assignment_id' => $assignment->id,
            'parent_id' => $requestsFolder->id,
            'folder_template_id' => null,
            'name' => sprintf('Permintaan %02d - %s', $sequenceNumber, $unit->code),
            'classification' => $requestsFolder->classification,
            'is_locked' => false,
            'created_by' => $creator->id,
        ]);
        $folder->save();

        $documentRequest = new DocumentRequest([
            'unit_id' => $unit->id,
            'due_date' => $data['due_date'],
        ]);
        $documentRequest->forceFill([
            'assignment_id' => $assignment->id,
            'sequence_number' => $sequenceNumber,
            'folder_id' => $folder->id,
            'status' => DocumentRequestStatus::Terbuka,
            'created_by' => $creator->id,
        ])->save();

        foreach (array_values($data['items']) as $index => $item) {
            $requestItem = new RequestItem([
                'name' => trim($item['name']),
                'description' => filled($item['description'] ?? null) ? trim($item['description']) : null,
                'accepted_extensions' => RequestItem::extensionsForGroups($item['formats']),
            ]);
            $requestItem->forceFill([
                'document_request_id' => $documentRequest->id,
                'sort_order' => $index + 1,
                'status' => RequestItemStatus::Menunggu,
            ])->save();
        }

        $this->auditLogger->record(AuditAction::DocumentRequestCreated, $documentRequest, [
            'assignment_id' => $assignment->id,
            'assignment_code' => $assignment->code,
            'unit_id' => $unit->id,
            'unit' => $unit->code,
            'due_date' => $documentRequest->due_date->toDateString(),
            'folder_id' => $folder->id,
            'items' => $documentRequest->items()->pluck('name')->all(),
        ], $creator);

        return $documentRequest;
    }

    private function nextSequenceNumber(Assignment $assignment): int
    {
        $lastSequenceNumber = DocumentRequest::query()
            ->where('assignment_id', $assignment->id)
            ->lockForUpdate()
            ->max('sequence_number');

        return ((int) $lastSequenceNumber) + 1;
    }
}
