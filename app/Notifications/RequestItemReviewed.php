<?php

namespace App\Notifications;

use App\Enums\RequestItemStatus;
use App\Models\RequestItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Hasil periksa slot oleh auditor (US-17); dikirim ke PIC unit tujuan (tambahan F-10). Untuk sementara hanya
 * disimpan di basis data dan tampil di dasbor; channel surel ditambahkan di US-30.
 */
class RequestItemReviewed extends Notification
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
     * @return array{document_request_id: int, request_item_id: int, result: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'document_request_id' => $this->item->document_request_id,
            'request_item_id' => $this->item->id,
            'result' => $this->item->status->value,
            'message' => $this->item->status === RequestItemStatus::Diterima
                ? sprintf('SPI menerima "%s".', $this->item->name)
                : sprintf('SPI meminta perbaikan untuk "%s". Lihat catatan auditor, lalu unggah ulang.', $this->item->name),
        ];
    }
}
