<?php

namespace App\Http\Controllers;

use App\Actions\CreateDocumentRequest;
use App\Enums\RequestItemStatus;
use App\Http\Requests\StoreDocumentRequestRequest;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\RequestItem;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-07 Permintaan Dokumen (SPI) per penugasan: buat permintaan dan pantau status slot (US-15, US-18).
 */
class DocumentRequestController extends Controller
{
    public function index(Request $request, Assignment $assignment): Response
    {
        Gate::authorize('viewAny', [DocumentRequest::class, $assignment]);

        $documentRequests = $assignment->documentRequests()
            ->with(['unit', 'items'])
            ->orderByDesc('sequence_number')
            ->get();

        return Inertia::render('Permintaan/Index', [
            'assignment' => $this->assignmentData($assignment),
            'documentRequests' => $documentRequests->map(function (DocumentRequest $documentRequest): array {
                $items = $documentRequest->items->each->setRelation('documentRequest', $documentRequest);

                return [
                    'id' => $documentRequest->id,
                    'number' => $documentRequest->sequence_number,
                    'unit' => $documentRequest->unit->name,
                    'due_date' => $documentRequest->due_date->toDateString(),
                    'is_past_due' => $documentRequest->isPastDue(),
                    'status_label' => $documentRequest->status->label(),
                    'item_count' => $items->count(),
                    'accepted_count' => $items->where('status', RequestItemStatus::Diterima)->count(),
                    'overdue_count' => $items->filter->isOverdue()->count(),
                ];
            }),
            'can' => ['create' => $request->user()->can('create', [DocumentRequest::class, $assignment])],
        ]);
    }

    public function create(Assignment $assignment): Response
    {
        Gate::authorize('create', [DocumentRequest::class, $assignment]);

        return Inertia::render('Permintaan/Buat', [
            'assignment' => $this->assignmentData($assignment),
            'units' => Unit::query()->withCount('picUsers')->orderBy('id')->get()->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'pic_count' => $unit->pic_users_count,
            ]),
            'defaultUnitId' => $assignment->unit_id,
            'formatGroups' => collect(RequestItem::formatGroups())
                ->map(fn (array $group, string $key): array => [
                    'key' => $key,
                    'label' => $group['label'],
                    'extensions' => $group['extensions'],
                ])
                ->values(),
            'maxItems' => StoreDocumentRequestRequest::MaxItems,
            'today' => now(config('app.business_timezone'))->toDateString(),
        ]);
    }

    public function store(StoreDocumentRequestRequest $request, Assignment $assignment, CreateDocumentRequest $createDocumentRequest): RedirectResponse
    {
        $documentRequest = $createDocumentRequest->handle($request->user(), $assignment, $request->validated());

        Inertia::flash('success', "Permintaan dokumen ke {$documentRequest->unit->name} dibuat.");

        return redirect()->route('penugasan.permintaan.show', [$assignment, $documentRequest]);
    }

    public function show(Assignment $assignment, DocumentRequest $documentRequest): Response
    {
        $documentRequest->setRelation('assignment', $assignment);
        Gate::authorize('view', $documentRequest);

        $documentRequest->load(['unit', 'creator', 'items']);

        return Inertia::render('Permintaan/Show', [
            'assignment' => $this->assignmentData($assignment),
            'documentRequest' => [
                'id' => $documentRequest->id,
                'number' => $documentRequest->sequence_number,
                'unit' => $documentRequest->unit->name,
                'due_date' => $documentRequest->due_date->toDateString(),
                'is_past_due' => $documentRequest->isPastDue(),
                'status_label' => $documentRequest->status->label(),
                'creator' => $documentRequest->creator->name,
                'created_at' => $documentRequest->created_at->toIso8601String(),
            ],
            'items' => $documentRequest->items->map(function (RequestItem $item) use ($documentRequest): array {
                $item->setRelation('documentRequest', $documentRequest);

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'formats' => $item->acceptedFormatLabels(),
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'is_overdue' => $item->isOverdue(),
                ];
            }),
        ]);
    }

    /**
     * @return array{id: int, code: string, is_closed: bool}
     */
    private function assignmentData(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'code' => $assignment->code,
            'is_closed' => $assignment->isClosed(),
        ];
    }
}
