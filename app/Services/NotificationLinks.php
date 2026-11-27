<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Halaman tujuan notifikasi: permintaan yang dirujuk notifikasi, di H-07 atau H-08 sesuai hak pengguna saat ini.
 */
class NotificationLinks
{
    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return array<string, string> notification id → target URL, only for notifications with a page the user may open
     */
    public function targets(User $user, Collection $notifications): array
    {
        $documentRequests = DocumentRequest::query()
            ->with('assignment')
            ->findMany($notifications->pluck('data.document_request_id')->filter()->unique()->values())
            ->keyBy('id');

        return $notifications
            ->mapWithKeys(fn (DatabaseNotification $notification): array => [
                $notification->id => $this->requestUrl($user, $documentRequests->get($notification->data['document_request_id'] ?? null)),
            ])
            ->filter()
            ->all();
    }

    private function requestUrl(User $user, ?DocumentRequest $documentRequest): ?string
    {
        return match (true) {
            $documentRequest === null => null,
            $user->can('view', $documentRequest) => route('penugasan.permintaan.show', [$documentRequest->assignment_id, $documentRequest]),
            $user->can('viewForUnit', $documentRequest) => route('permintaan-unit.show', $documentRequest),
            default => null,
        };
    }
}
