<?php

namespace App\Notifications;

use App\Models\RequestItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Unggahan ke slot terdeteksi virus sehingga slot kembali ke Perlu Perbaikan (US-16); dikirim ke PIC unit.
 * Untuk sementara hanya disimpan di basis data dan tampil di dasbor; channel surel ditambahkan di US-30.
 */
class RequestItemInfected extends Notification
{
    use Queueable;

    public function __construct(public RequestItem $item) {}

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
        return [
            'document_request_id' => $this->item->document_request_id,
            'request_item_id' => $this->item->id,
            'message' => sprintf('Berkas "%s" terdeteksi virus dan dikarantina. Unggah ulang berkas yang bersih.', $this->item->name),
        ];
    }
}
