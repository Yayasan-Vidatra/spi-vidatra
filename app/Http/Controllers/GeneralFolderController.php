<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Services\FolderFileList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Folder umum di luar penugasan, yaitu Regulasi dan Pedoman serta Templat (Rancangan 9.1): Dewan Pengawas
 * mengunggah, Auditor SPI melihat dan mengunduh.
 */
class GeneralFolderController extends Controller
{
    public function index(Request $request, FolderFileList $folderFileList): Response
    {
        Gate::authorize('viewAnyGeneral', Folder::class);

        $user = $request->user();
        $folders = Folder::query()
            ->whereNull('assignment_id')
            ->whereNull('parent_id')
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->filter(fn (Folder $folder): bool => $user->can('view', $folder))
            ->values();

        $selectedFolder = $folders->firstWhere('id', $request->integer('folder')) ?? $folders->first();

        return Inertia::render('FolderUmum/Index', [
            'folders' => $folders->map(fn (Folder $folder): array => [
                'id' => $folder->id,
                'name' => $folder->name,
                'classification' => $folder->classification->value,
                'classification_label' => $folder->classification->label(),
            ]),
            'selectedFolder' => $selectedFolder === null ? null : $folderFileList->forFolder($selectedFolder, $user),
            'uploadRules' => $folderFileList->uploadRules(),
        ]);
    }
}
