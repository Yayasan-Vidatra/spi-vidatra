<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\Assignment;
use App\Models\Folder;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Mengarsipkan subfolder penugasan beserta seluruh isinya (US-07).
 */
class FolderArchiveController extends Controller
{
    public function store(Assignment $assignment, Folder $folder, AuditLogger $auditLogger): RedirectResponse
    {
        $folder->setRelation('assignment', $assignment);

        Gate::authorize('archive', $folder);

        DB::transaction(function () use ($folder, $auditLogger): void {
            $folderIds = $folder->selfAndDescendants()->pluck('id');

            Folder::query()->whereKey($folderIds)->update(['archived_at' => now()]);

            $auditLogger->record(AuditAction::FolderArchived, $folder, [
                'name' => $folder->name,
                'folder_count' => $folderIds->count(),
            ]);
        });

        return redirect()->route('penugasan.show', $assignment);
    }
}
