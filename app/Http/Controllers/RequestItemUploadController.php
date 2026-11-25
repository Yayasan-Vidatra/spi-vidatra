<?php

namespace App\Http\Controllers;

use App\Actions\StoreRequestItemUpload;
use App\Http\Requests\StoreRequestItemUploadRequest;
use App\Models\DocumentRequest;
use App\Models\RequestItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * PIC mengunggah ke slot permintaan dari H-08 (US-16).
 */
class RequestItemUploadController extends Controller
{
    public function store(
        StoreRequestItemUploadRequest $request,
        DocumentRequest $documentRequest,
        RequestItem $item,
        StoreRequestItemUpload $storeRequestItemUpload,
    ): RedirectResponse {
        $storeRequestItemUpload->handle($request->user(), $item, $request->file('file'));

        Inertia::flash('success', "\"{$item->name}\" diunggah. Berkas sedang dipindai antivirus.");

        return redirect()->route('permintaan-unit.show', $documentRequest);
    }
}
