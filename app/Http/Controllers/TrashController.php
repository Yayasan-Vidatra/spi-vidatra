<?php

namespace App\Http\Controllers;

use App\Actions\PurgeFile;
use App\Actions\RestoreTrashedFile;
use App\Enums\RoleSlug;
use App\Models\File;
use App\Services\FolderFileList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-14 Tempat Sampah (US-13). Dewan Pengawas melihat semua berkas terhapus serta memulihkan atau menghapus
 * permanen; Auditor SPI hanya melihat berkas terhapus dari penugasannya, dengan aturan Rahasia yang sama.
 */
class TrashController extends Controller
{
    private const int PerPage = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', File::class);

        $user = $request->user();
        $retentionDays = config('berkas.trash_retention_days');

        $files = File::onlyTrashed()
            ->visibleTo($user)
            ->unless($user->hasRole(RoleSlug::DewanPengawas), fn (Builder $query) => $query
                ->whereHas('folder', fn (Builder $folders) => $folders->whereNotNull('assignment_id')))
            ->with(['folder.assignment', 'deleter', 'currentVersion'])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(self::PerPage);

        return Inertia::render('TempatSampah/Index', [
            'files' => $files->through(fn (File $file): array => [
                'id' => $file->id,
                'name' => $file->original_name,
                'classification' => $file->classification->value,
                'classification_label' => $file->classification->label(),
                'folder' => $file->folder->name,
                'assignment_code' => $file->folder->assignment?->code,
                'version_number' => $file->currentVersion?->version_number,
                'deleted_by' => $file->deleter?->name,
                'deleted_at' => $file->deleted_at->toIso8601String(),
                'purge_at' => $file->deleted_at->copy()->addDays($retentionDays)->toIso8601String(),
                'can' => [
                    'restore' => $user->can('restore', $file),
                    'forceDelete' => $user->can('forceDelete', $file),
                ],
            ]),
            'retentionDays' => $retentionDays,
        ]);
    }

    public function restore(File $file, RestoreTrashedFile $restoreTrashedFile, FolderFileList $folderFileList): RedirectResponse
    {
        abort_unless($file->trashed(), 404);
        Gate::authorize('restore', $file);

        $restored = $restoreTrashedFile->handle($file);

        Inertia::flash('success', $restored->original_name === $file->original_name
            ? "Berkas {$restored->original_name} dipulihkan."
            : "Sudah ada berkas bernama {$file->original_name}, jadi berkas dipulihkan sebagai {$restored->original_name}.");

        return redirect()->to($folderFileList->folderUrl($restored->folder));
    }

    public function destroy(File $file, PurgeFile $purgeFile): RedirectResponse
    {
        abort_unless($file->trashed(), 404);
        Gate::authorize('forceDelete', $file);

        $purgeFile->handle($file);

        Inertia::flash('success', "Berkas {$file->original_name} dihapus permanen.");

        return redirect()->route('tempat-sampah.index');
    }
}
