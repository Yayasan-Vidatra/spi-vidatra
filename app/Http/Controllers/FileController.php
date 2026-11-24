<?php

namespace App\Http\Controllers;

use App\Actions\StoreFileUpload;
use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\UpdateFileRequest;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FolderFileList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unggah berkas (US-09), H-06 Detail Berkas (US-10, US-11), dan ubah label berkas (US-07).
 */
class FileController extends Controller
{
    private const int AccessHistoryLimit = 100;

    public function __construct(private FolderFileList $folderFileList) {}

    public function store(StoreFileRequest $request, Folder $folder, StoreFileUpload $storeFileUpload): RedirectResponse
    {
        $uploads = $request->uploads();

        foreach ($uploads as $upload) {
            $storeFileUpload->handle($request->user(), $folder, $upload);
        }

        Inertia::flash('success', count($uploads).' berkas diunggah. Berkas bisa dibuka setelah lolos pemindaian antivirus.');

        return redirect()->to($this->folderFileList->folderUrl($folder));
    }

    public function show(Request $request, File $file): Response
    {
        Gate::authorize('view', $file);

        $user = $request->user();
        $folder = $file->folder;
        $versions = $file->versions()->with(['uploader', 'restoredFrom'])->orderByDesc('version_number')->get();
        $currentVersion = $versions->firstWhere('id', $file->current_version_id);

        return Inertia::render('Berkas/Show', [
            'file' => [
                'id' => $file->id,
                'name' => $file->original_name,
                'classification' => $file->classification->value,
                'classification_label' => $file->classification->label(),
                'folder_path' => $this->folderPath($folder),
                'folder_url' => $this->folderFileList->folderUrl($folder),
                'assignment_code' => $folder->assignment?->code,
                'is_locked' => $folder->is_locked || $folder->isArchived(),
            ],
            'preview' => $currentVersion !== null && $user->can('preview', [$file, $currentVersion])
                ? [
                    'url' => route('berkas.versi.pratinjau', [$file, $currentVersion]),
                    'type' => $currentVersion->mime_type === 'application/pdf' ? 'pdf' : 'image',
                ]
                : null,
            'versions' => $versions->map(fn (FileVersion $version): array => [
                'id' => $version->id,
                'number' => $version->version_number,
                'is_current' => $version->id === $file->current_version_id,
                'size' => $version->size,
                'mime_type' => $version->mime_type,
                'sha256' => $version->sha256,
                'scan_status' => $version->scan_status->value,
                'scan_status_label' => $version->scan_status->label(),
                'uploader' => $version->uploader->name,
                'uploaded_at' => $version->created_at->toIso8601String(),
                'restored_from' => $version->restoredFrom?->version_number,
                'download_url' => $user->can('download', [$file, $version]) ? route('berkas.versi.unduh', [$file, $version]) : null,
                'can' => ['restore' => $user->can('restoreVersion', [$file, $version])],
            ])->values(),
            'allowedLabels' => collect(Classification::cases())
                ->filter(fn (Classification $label): bool => $user->can('changeLabel', [$file, $label]))
                ->map(fn (Classification $label): array => ['value' => $label->value, 'label' => $label->label()])
                ->values(),
            'accessHistory' => $user->can('viewAccessHistory', $file) ? $this->accessHistory($file) : null,
            'can' => ['changeLabel' => $user->can('changeLabel', $file)],
        ]);
    }

    public function update(UpdateFileRequest $request, File $file, AuditLogger $auditLogger): RedirectResponse
    {
        $classification = $request->enum('classification', Classification::class);

        if ($file->classification !== $classification) {
            Gate::authorize('changeLabel', [$file, $classification]);

            DB::transaction(function () use ($file, $classification, $auditLogger): void {
                $oldClassification = $file->classification;
                $file->forceFill(['classification' => $classification])->save();

                $auditLogger->record(AuditAction::FileLabelChanged, $file, [
                    'from' => $oldClassification->value,
                    'to' => $classification->value,
                ]);
            });

            Inertia::flash('success', "Label berkas diubah menjadi {$classification->label()}.");
        }

        return redirect()->route('berkas.show', $file);
    }

    private function folderPath(Folder $folder): string
    {
        $names = [];

        for ($current = $folder; $current !== null; $current = $current->parent) {
            array_unshift($names, $current->name);
        }

        return implode(' / ', $names);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accessHistory(File $file): array
    {
        return AuditLog::query()
            ->where('subject_type', $file->getMorphClass())
            ->where('subject_id', $file->id)
            ->with('user')
            ->orderByDesc('id')
            ->limit(self::AccessHistoryLimit)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'user' => $log->user instanceof User ? $log->user->name : 'Sistem',
                'version' => $log->properties['version'] ?? null,
                'ip_address' => $log->ip_address,
                'occurred_at' => $log->occurred_at->toIso8601String(),
            ])
            ->all();
    }
}
