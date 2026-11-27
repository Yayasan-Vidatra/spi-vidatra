<?php

namespace App\Http\Controllers;

use App\Actions\ChangeDocumentRequestDueDate;
use App\Http\Requests\UpdateDocumentRequestDueDateRequest;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Ubah tenggat permintaan dari H-07 (US-18).
 */
class DocumentRequestDueDateController extends Controller
{
    public function update(
        UpdateDocumentRequestDueDateRequest $request,
        Assignment $assignment,
        DocumentRequest $documentRequest,
        ChangeDocumentRequestDueDate $changeDueDate,
    ): RedirectResponse {
        $documentRequest = $changeDueDate->handle($request->user(), $documentRequest, $request->validated('due_date'), $request->validated('reason'));

        Inertia::flash('success', 'Tenggat diubah menjadi '.$documentRequest->due_date->locale('id')->translatedFormat('j F Y').'. PIC unit diberi tahu.');

        return redirect()->route('penugasan.permintaan.show', [$assignment, $documentRequest]);
    }
}
