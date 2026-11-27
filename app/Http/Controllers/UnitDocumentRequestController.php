<?php

namespace App\Http\Controllers;

use App\Enums\DocumentRequestStatus;
use App\Enums\RequestItemStatus;
use App\Models\DocumentRequest;
use App\Models\FileVersion;
use App\Models\RequestItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-08 Permintaan untuk Unit Saya (US-16): PIC hanya melihat permintaan untuk unitnya, disaring di query.
 * Kode dan judul penugasan tidak ditampilkan ke unit.
 */
class UnitDocumentRequestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAnyForUnit', DocumentRequest::class);

        $documentRequests = DocumentRequest::query()
            ->where('unit_id', $request->user()->unit_id)
            ->with('items')
            ->orderByRaw('status = ? desc', [DocumentRequestStatus::Terbuka->value])
            ->orderBy('due_date')
            ->get();

        return Inertia::render('PermintaanUnit/Index', [
            'unit' => $request->user()->unit->name,
            'documentRequests' => $documentRequests->map(function (DocumentRequest $documentRequest): array {
                $items = $documentRequest->items->each->setRelation('documentRequest', $documentRequest);

                return [
                    'id' => $documentRequest->id,
                    'number' => $documentRequest->sequence_number,
                    'due_date' => $documentRequest->due_date->toDateString(),
                    'is_past_due' => $documentRequest->isPastDue(),
                    'status_label' => $documentRequest->status->label(),
                    'created_at' => $documentRequest->created_at->toDateString(),
                    'item_count' => $items->count(),
                    'pending_count' => $items->filter(fn (RequestItem $item): bool => $item->status->awaitsUpload())->count(),
                    'overdue_count' => $items->filter->isOverdue()->count(),
                ];
            }),
        ]);
    }

    public function show(Request $request, DocumentRequest $documentRequest): Response
    {
        Gate::authorize('viewForUnit', $documentRequest);

        $user = $request->user();
        $documentRequest->load(['unit', 'creator', 'assignment', 'items.file.versions.uploader']);

        return Inertia::render('PermintaanUnit/Show', [
            'documentRequest' => [
                'id' => $documentRequest->id,
                'number' => $documentRequest->sequence_number,
                'unit' => $documentRequest->unit->name,
                'due_date' => $documentRequest->due_date->toDateString(),
                'is_past_due' => $documentRequest->isPastDue(),
                'status_label' => $documentRequest->status->label(),
                'creator' => $documentRequest->creator->name,
                'created_at' => $documentRequest->created_at->toDateString(),
            ],
            'items' => $documentRequest->items->map(fn (RequestItem $item): array => $this->itemData(
                $item->setRelation('documentRequest', $documentRequest),
                $user,
            )),
            'maxSizeMb' => config('berkas.max_size_mb'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemData(RequestItem $item, User $user): array
    {
        $versions = $item->file?->versions->sortByDesc('version_number')->values() ?? collect();

        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'formats' => $item->acceptedFormatLabels(),
            'accept' => implode(',', array_map(fn (string $extension): string => '.'.$extension, $item->accepted_extensions)),
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'is_overdue' => $item->isOverdue(),
            'is_submitted_late' => $item->isSubmittedLate(),
            'is_locked' => $item->status === RequestItemStatus::Diterima,
            'reviewer_note' => $item->reviewer_note,
            'system_note' => $item->system_note,
            'can_upload' => $user->can('upload', $item),
            'versions' => $versions->map(fn (FileVersion $version): array => [
                'id' => $version->id,
                'number' => $version->version_number,
                'uploaded_at' => $version->created_at->toIso8601String(),
                'uploader' => $version->uploader->name,
                'scan_status' => $version->scan_status->value,
                'scan_status_label' => $version->scan_status->label(),
                'download_url' => $user->can('download', [$item->file, $version])
                    ? route('berkas.versi.unduh', [$item->file, $version])
                    : null,
            ]),
        ];
    }
}
