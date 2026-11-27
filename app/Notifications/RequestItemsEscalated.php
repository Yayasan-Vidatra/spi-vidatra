<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use App\Models\RequestItem;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Eskalasi tenggat Piagam (F-21, UC-17): slot permintaan yang belum Diterima 10 hari kerja setelah tenggat,
 * dikirim ke Dewan Pengawas dan Kepala SPI, satu notifikasi per permintaan per pemeriksaan. Tiap slot memuat
 * statusnya dan di pihak mana ia tertahan. Untuk sementara hanya disimpan di basis data; surel di US-30.
 */
class RequestItemsEscalated extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, RequestItem>  $items
     */
    public function __construct(public DocumentRequest $documentRequest, public Collection $items, public CarbonInterface $charterDeadline) {}

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
     * @return array{document_request_id: int, assignment_id: int, due_date: string, charter_deadline: string, items: list<array{request_item_id: int, name: string, status: string, status_label: string, hold_label: string}>, message: string}
     */
    public function toArray(object $notifiable): array
    {
        $documentRequest = $this->documentRequest;

        return [
            'document_request_id' => $documentRequest->id,
            'assignment_id' => $documentRequest->assignment_id,
            'due_date' => $documentRequest->due_date->toDateString(),
            'charter_deadline' => $this->charterDeadline->toDateString(),
            'items' => $this->items->map(fn (RequestItem $item): array => [
                'request_item_id' => $item->id,
                'name' => $item->name,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'hold_label' => $item->status->holdLabel(),
            ])->values()->all(),
            'message' => sprintf(
                'Eskalasi tenggat Piagam: Permintaan %02d ke %s (penugasan %s) belum dipenuhi 10 hari kerja setelah tenggat %s (batas %s). %d dokumen belum diterima: %s.',
                $documentRequest->sequence_number,
                $documentRequest->unit->name,
                $documentRequest->assignment->code,
                $documentRequest->due_date->locale('id')->translatedFormat('j F Y'),
                $this->charterDeadline->locale('id')->translatedFormat('j F Y'),
                $this->items->count(),
                $this->items->map(fn (RequestItem $item): string => sprintf('"%s" (%s, %s)', $item->name, $item->status->label(), $item->status->holdLabel()))->implode('; '),
            ),
        ];
    }
}
