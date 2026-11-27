<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Permintaan dokumen baru untuk unit PIC (US-15). Untuk sementara hanya disimpan di basis data dan tampil di
 * dasbor; channel surel ditambahkan di US-30.
 */
class DocumentRequestReceived extends Notification
{
    use Queueable;

    public function __construct(public DocumentRequest $documentRequest) {}

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
     * @return array{document_request_id: int, message: string}
     */
    public function toArray(object $notifiable): array
    {
        $documentRequest = $this->documentRequest;

        return [
            'document_request_id' => $documentRequest->id,
            'message' => sprintf(
                'Permintaan dokumen baru dari SPI: %d dokumen, tenggat %s.',
                $documentRequest->items()->count(),
                $documentRequest->due_date->locale('id')->translatedFormat('j F Y'),
            ),
        ];
    }
}
