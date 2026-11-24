<?php

namespace App\Http\Controllers;

use App\Actions\RestoreFileVersion;
use App\Models\File;
use App\Models\FileVersion;
use App\Services\FileContentUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Pulihkan versi (US-10) serta tautan pratinjau dan unduh (US-11). Tautan di halaman tidak pernah
 * kedaluwarsa karena hak dicek ulang di sini lalu diarahkan ke signed URL baru berumur 5 menit.
 */
class FileVersionController extends Controller
{
    public function restore(Request $request, File $file, FileVersion $version, RestoreFileVersion $restoreFileVersion): RedirectResponse
    {
        Gate::authorize('restoreVersion', [$file, $version]);

        $newVersion = $restoreFileVersion->handle($request->user(), $file, $version);

        Inertia::flash('success', "Versi {$version->version_number} dipulihkan sebagai versi {$newVersion->version_number}.");

        return redirect()->route('berkas.show', $file);
    }

    public function download(Request $request, File $file, FileVersion $version, FileContentUrl $contentUrl): RedirectResponse
    {
        Gate::authorize('download', [$file, $version]);

        return redirect()->away($contentUrl->generate($file, $version, $request->user(), FileContentUrl::ModeDownload));
    }

    public function preview(Request $request, File $file, FileVersion $version, FileContentUrl $contentUrl): RedirectResponse
    {
        Gate::authorize('preview', [$file, $version]);

        return redirect()->away($contentUrl->generate($file, $version, $request->user(), FileContentUrl::ModePreview));
    }
}
