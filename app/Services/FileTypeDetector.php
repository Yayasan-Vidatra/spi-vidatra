<?php

namespace App\Services;

use finfo;
use ZipArchive;

/**
 * Menentukan tipe berkas dari isinya, bukan dari nama (US-09, Rancangan 6.4). Ekstensi nama berkas
 * harus cocok dengan isi: berkas .exe yang diganti nama menjadi .pdf ditolak.
 *
 * DOCX, XLSX, dan PPTX adalah arsip ZIP, sehingga libmagic sering membacanya sebagai application/zip.
 * Untuk ketiganya, isi arsip diperiksa: harus ada [Content_Types].xml yang menyatakan bagian utama
 * dokumen tanpa makro, dan bagian utama itu sendiri.
 */
class FileTypeDetector
{
    /**
     * @var array<string, string>
     */
    private const MimeTypes = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'zip' => 'application/zip',
    ];

    /**
     * @var array<string, array{part: string, content_type: string}>
     */
    private const OfficeMainParts = [
        'docx' => [
            'part' => 'word/document.xml',
            'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
        ],
        'xlsx' => [
            'part' => 'xl/workbook.xml',
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
        ],
        'pptx' => [
            'part' => 'ppt/presentation.xml',
            'content_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml',
        ],
    ];

    /**
     * Bentuk lain yang dilaporkan libmagic untuk arsip ZIP, termasuk dokumen Office.
     *
     * @var list<string>
     */
    private const ZipLikeMimeTypes = ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'];

    private const MaxContentTypesBytes = 1_048_576;

    /**
     * Detect the canonical MIME type of the file at the path, or null when the content does not match
     * the extension or the extension is not allowed.
     */
    public function detect(string $path, string $extension): ?string
    {
        $extension = strtolower($extension);

        if (! $this->isAllowedExtension($extension)) {
            return null;
        }

        $detectedMimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($detectedMimeType === false) {
            return null;
        }

        $expectedMimeType = self::MimeTypes[$extension];

        if (isset(self::OfficeMainParts[$extension])) {
            $looksLikeOffice = $detectedMimeType === $expectedMimeType || in_array($detectedMimeType, self::ZipLikeMimeTypes, true);

            return $looksLikeOffice && $this->isOfficeDocument($path, $extension) ? $expectedMimeType : null;
        }

        if ($extension === 'zip') {
            return in_array($detectedMimeType, ['application/zip', 'application/x-zip-compressed'], true) && $this->isZipArchive($path)
                ? $expectedMimeType
                : null;
        }

        return $detectedMimeType === $expectedMimeType ? $expectedMimeType : null;
    }

    public function isAllowedExtension(string $extension): bool
    {
        $extension = strtolower($extension);

        return isset(self::MimeTypes[$extension]) && in_array($extension, config('berkas.allowed_extensions'), true);
    }

    private function isZipArchive(string $path): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }

        $zip->close();

        return true;
    }

    private function isOfficeDocument(string $path, string $extension): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }

        try {
            $mainPart = self::OfficeMainParts[$extension];
            $contentTypesStat = $zip->statName('[Content_Types].xml');

            if ($contentTypesStat === false || $contentTypesStat['size'] > self::MaxContentTypesBytes) {
                return false;
            }

            if ($zip->locateName($mainPart['part']) === false) {
                return false;
            }

            $contentTypes = $zip->getFromName('[Content_Types].xml');

            // Dokumen bermakro (.docm, .xlsm, .pptm) yang diganti nama tidak menyatakan content type ini.
            return $contentTypes !== false && str_contains($contentTypes, 'ContentType="'.$mainPart['content_type'].'"');
        } finally {
            $zip->close();
        }
    }
}
