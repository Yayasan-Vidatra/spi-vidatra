<?php

namespace App\Http\Controllers;

use App\Actions\ReviewRequestItem;
use App\Enums\RequestItemStatus;
use App\Http\Requests\ReviewRequestItemRequest;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\RequestItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Auditor memeriksa slot dari H-07: Diterima atau Perlu Perbaikan (US-17).
 */
class RequestItemReviewController extends Controller
{
    public function store(
        ReviewRequestItemRequest $request,
        Assignment $assignment,
        DocumentRequest $documentRequest,
        RequestItem $item,
        ReviewRequestItem $reviewRequestItem,
    ): RedirectResponse {
        $result = $request->result();

        $reviewRequestItem->handle($request->user(), $item, $result, $request->validated('note'), (int) $request->validated('version_id'));

        Inertia::flash('success', $result === RequestItemStatus::Diterima
            ? "\"{$item->name}\" diterima dan sedang disalin ke 04 Bukti Unit."
            : "\"{$item->name}\" dikembalikan ke unit untuk diperbaiki.");

        return redirect()->route('penugasan.permintaan.show', [$assignment, $documentRequest]);
    }
}
