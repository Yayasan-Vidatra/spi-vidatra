<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\DocumentRequestStatus;
use App\Enums\EscalationStatus;
use App\Enums\EscalationType;
use App\Enums\RequestItemStatus;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\Escalation;
use App\Models\RequestItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-03 Dasbor sementara (US-18, UC-17): notifikasi terbaru untuk semua peran, slot Terlambat untuk tim SPI, dan
 * eskalasi tenggat Piagam yang masih Diteruskan untuk Dewan Pengawas. Ringkasan lengkap sesuai peran di US-28.
 */
class DashboardController extends Controller
{
    public const int NotificationLimit = 20;

    public const int ListLimit = 50;

    public function show(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::now(config('app.business_timezone'))->startOfDay();

        return Inertia::render('Dasbor', [
            'notifications' => $this->notifications($user),
            'overdueItems' => $user->can('viewAny', Assignment::class) ? $this->overdueItems($user, $today) : null,
            'escalations' => $user->can('viewAny', Escalation::class) ? $this->escalations() : null,
        ]);
    }

    /**
     * Latest notifications, each linked to the request page this user may open.
     *
     * @return list<array{id: string, message: string, created_at: string, is_read: bool, url: string|null}>
     */
    private function notifications(User $user): array
    {
        $notifications = $user->notifications()->latest()->limit(self::NotificationLimit)->get();
        $documentRequests = DocumentRequest::query()
            ->with('assignment')
            ->findMany($notifications->pluck('data.document_request_id')->filter()->unique())
            ->keyBy('id');

        return $notifications->map(fn (DatabaseNotification $notification): array => [
            'id' => $notification->id,
            'message' => $notification->data['message'] ?? '',
            'created_at' => $notification->created_at->toIso8601String(),
            'is_read' => $notification->read_at !== null,
            'url' => $this->requestUrl($user, $documentRequests->get($notification->data['document_request_id'] ?? null)),
        ])->values()->all();
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

    /**
     * Terlambat slots (KF-08) of open requests in active assignments the user may see, oldest deadline first.
     *
     * @return array{total: int, items: list<array<string, mixed>>}
     */
    private function overdueItems(User $user, CarbonImmutable $today): array
    {
        $query = RequestItem::query()
            ->whereIn('request_items.status', [RequestItemStatus::Menunggu, RequestItemStatus::PerluPerbaikan])
            ->whereHas('documentRequest', fn (Builder $requests) => $requests
                ->where('status', DocumentRequestStatus::Terbuka)
                ->whereDate('due_date', '<', $today->toDateString())
                ->whereHas('assignment', fn (Builder $assignments) => $assignments
                    ->where('status', AssignmentStatus::Aktif)
                    // Sama dengan AssignmentPolicy::view: auditor hanya melihat penugasan tempat ia ditunjuk.
                    ->unless($user->hasRole(RoleSlug::DewanPengawas), fn (Builder $query) => $query->whereMember($user))));

        $items = (clone $query)
            ->with(['documentRequest.assignment', 'documentRequest.unit'])
            ->join('document_requests', 'document_requests.id', '=', 'request_items.document_request_id')
            ->orderBy('document_requests.due_date')
            ->orderBy('request_items.document_request_id')
            ->orderBy('request_items.sort_order')
            ->select('request_items.*')
            ->limit(self::ListLimit)
            ->get();

        return [
            'total' => $query->count(),
            'items' => $items->map(fn (RequestItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'status_label' => $item->status->label(),
                'assignment_code' => $item->documentRequest->assignment->code,
                'request_number' => $item->documentRequest->sequence_number,
                'unit' => $item->documentRequest->unit->name,
                'due_date' => $item->documentRequest->due_date->toDateString(),
                'days_late' => (int) CarbonImmutable::parse($item->documentRequest->due_date->toDateString(), config('app.business_timezone'))->diffInDays($today),
                'url' => route('penugasan.permintaan.show', [$item->documentRequest->assignment_id, $item->document_request_id]),
            ])->values()->all(),
        ];
    }

    /**
     * Escalations still Diteruskan (UC-17 langkah 3), oldest first.
     *
     * @return array{total: int, items: list<array<string, mixed>>}
     */
    private function escalations(): array
    {
        $query = Escalation::query()
            ->where('type', EscalationType::PermintaanDokumen)
            ->where('status', EscalationStatus::Diteruskan);

        $escalations = (clone $query)
            ->with(['subject' => fn (MorphTo $subject) => $subject->morphWith([
                RequestItem::class => ['documentRequest.assignment', 'documentRequest.unit'],
            ])])
            ->orderBy('escalated_at')
            ->orderBy('id')
            ->limit(self::ListLimit)
            ->get();

        return [
            'total' => $query->count(),
            'items' => $escalations->map(function (Escalation $escalation): array {
                /** @var RequestItem $item */
                $item = $escalation->subject;
                $documentRequest = $item->documentRequest;

                return [
                    'id' => $escalation->id,
                    'name' => $item->name,
                    'status_label' => $item->status->label(),
                    'hold_label' => $item->status->holdLabel(),
                    'assignment_code' => $documentRequest->assignment->code,
                    'request_number' => $documentRequest->sequence_number,
                    'unit' => $documentRequest->unit->name,
                    'due_date' => $documentRequest->due_date->toDateString(),
                    'charter_deadline' => $escalation->charter_deadline->toDateString(),
                    'escalated_at' => $escalation->escalated_at->toIso8601String(),
                    'url' => route('penugasan.permintaan.show', [$documentRequest->assignment_id, $documentRequest]),
                ];
            })->values()->all(),
        ];
    }
}
