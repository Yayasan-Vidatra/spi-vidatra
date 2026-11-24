<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\File;
use App\Models\FileVersion;
use App\Services\AuditLogger;
use App\Services\FileContentUrl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan isi berkas lewat signed URL (US-11). Tanda tangan dicek middleware `signed`; di sini dicek
 * pemilik dan sesinya, lalu hak akses diperiksa ulang karena bisa berubah sejak tautan dibuat.
 * Setiap buka (pratinjau) dan unduh tercatat di log audit.
 */
class FileContentController extends Controller
{
    public function show(File $file, FileVersion $version, string $mode, FileContentUrl $contentUrl, AuditLogger $auditLogger): StreamedResponse
    {
        abort_unless($contentUrl->isBoundToCurrentSession(), 403, 'Tautan ini bukan milik sesi Anda.');

        $isPreview = $mode === FileContentUrl::ModePreview;

        Gate::authorize($isPreview ? 'preview' : 'download', [$file, $version]);

        $auditLogger->record($isPreview ? AuditAction::FileViewed : AuditAction::FileDownloaded, $file, [
            'version' => $version->version_number,
            'version_id' => $version->id,
        ]);

        return Storage::disk(config('berkas.disk'))->response($version->storage_path, null, [
            'Content-Type' => $version->mime_type,
            'Content-Length' => $version->size,
            'Content-Disposition' => $this->contentDisposition($isPreview ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT, $file->original_name),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Nama asli dipakai apa adanya lewat filename* (UTF-8, di-encode), dengan cadangan ASCII yang hanya
     * berisi karakter cetak aman. Karakter kontrol dibuang agar header tidak bisa disisipi baris baru.
     */
    private function contentDisposition(string $disposition, string $name): string
    {
        $safeName = preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '_', $name) ?? '';
        $safeName = $safeName !== '' ? $safeName : 'berkas';

        $fallback = preg_replace('/[^\x20-\x7E]/', '_', Str::ascii($safeName)) ?? '';
        $fallback = str_replace(['"', '%', ';'], '_', $fallback);

        return HeaderUtils::makeDisposition($disposition, $safeName, $fallback !== '' ? $fallback : 'berkas');
    }
}
