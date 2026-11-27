<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tenggat permintaan diubah SPI (US-18); dikirim ke PIC unit tujuan beserta alasannya. Untuk sementara hanya
 * disimpan di basis data; channel surel ditambahkan di US-30.
 */
class DocumentRequestDueDateChanged extends Notification
{
    use Queueable;

    public function __construct(public DocumentRequest $documentRequest, public string $previousDueDate, public string $reason) {}

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
     * @return array{document_request_id: int, previous_due_date: string, due_date: string, reason: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'document_request_id' => $this->documentRequest->id,
            'previous_due_date' => $this->previousDueDate,
            'due_date' => $this->documentRequest->due_date->toDateString(),
            'reason' => $this->reason,
            'message' => sprintf(
                'SPI mengubah tenggat Permintaan %02d dari %s menjadi %s. Alasan: %s',
                $this->documentRequest->sequence_number,
                CarbonImmutable::parse($this->previousDueDate)->locale('id')->translatedFormat('j F Y'),
                $this->documentRequest->due_date->locale('id')->translatedFormat('j F Y'),
                $this->reason,
            ),
        ];
    }
}
