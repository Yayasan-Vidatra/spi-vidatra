<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Pengingat hari-H ke PIC unit: tenggat permintaan hari ini dan masih ada slot yang belum diunggah atau perlu
 * diperbaiki (US-18). Untuk sementara hanya disimpan di basis data; channel surel ditambahkan di US-30.
 */
class DocumentRequestDueToday extends Notification
{
    use Queueable;

    public function __construct(public DocumentRequest $documentRequest, public int $pendingItemCount) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{document_request_id: int, due_date: string, pending_item_count: int, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'document_request_id' => $this->documentRequest->id,
            'due_date' => $this->documentRequest->due_date->toDateString(),
            'pending_item_count' => $this->pendingItemCount,
            'message' => sprintf(
                'Hari ini tenggat Permintaan %02d dari SPI. Masih ada %d dokumen yang belum diunggah atau perlu diperbaiki; unggah paling lambat pukul 23.59 WITA.',
                $this->documentRequest->sequence_number,
                $this->pendingItemCount,
            ),
        ];
    }
}
