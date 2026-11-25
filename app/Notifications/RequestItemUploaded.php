<?php

namespace App\Notifications;

use App\Models\RequestItem;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * PIC mengunggah ke slot permintaan (US-16); dikirim ke pembuat permintaan saja. Untuk sementara hanya
 * disimpan di basis data dan tampil di dasbor; channel surel ditambahkan di US-30.
 */
class RequestItemUploaded extends Notification
{
    use Queueable;

    public function __construct(public RequestItem $item, public User $uploader) {}

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
     * @return array{document_request_id: int, request_item_id: int, message: string}
     */
    public function toArray(object $notifiable): array
    {
        $documentRequest = $this->item->documentRequest;

        return [
            'document_request_id' => $documentRequest->id,
            'request_item_id' => $this->item->id,
            'message' => sprintf(
                '%s (%s) mengunggah "%s" untuk %s%s.',
                $this->uploader->name,
                $documentRequest->unit->name,
                $this->item->name,
                $documentRequest->assignment->code,
                $this->item->isSubmittedLate() ? ', melewati tenggat' : '',
            ),
        ];
    }
}
