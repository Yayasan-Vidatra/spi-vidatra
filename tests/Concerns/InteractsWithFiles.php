<?php

namespace Tests\Concerns;

use App\Enums\ScanStatus;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

trait InteractsWithFiles
{
    protected const string PdfContent = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

    /**
     * @var array<string, array{part: string, content_type: string}>
     */
    private const array OfficeParts = [
        'docx' => ['part' => 'word/document.xml', 'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'],
        'xlsx' => ['part' => 'xl/workbook.xml', 'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'],
        'pptx' => ['part' => 'ppt/presentation.xml', 'content_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml'],
        'docm' => ['part' => 'word/document.xml', 'content_type' => 'application/vnd.ms-word.document.macroEnabled.main+xml'],
    ];

    protected function fakeFileDisk(): void
    {
        Storage::fake(config('berkas.disk'));
    }

    protected function pdfUpload(string $name = 'Laporan.pdf', string $content = self::PdfContent): TestingFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /**
     * A Windows executable, which libmagic reports as application/x-dosexec.
     */
    protected function executableContent(): string
    {
        return 'MZ'.str_repeat("\0", 58).pack('V', 64)."PE\0\0".str_repeat("\0", 200);
    }

    /**
     * Build a minimal Office Open XML package. Pass "docm" for a macro-enabled Word document.
     */
    protected function officeContent(string $kind): string
    {
        $part = self::OfficeParts[$kind];

        return $this->zipContent([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Override PartName="/'.$part['part'].'" ContentType="'.$part['content_type'].'"/></Types>',
            $part['part'] => '<?xml version="1.0" encoding="UTF-8"?><document/>',
        ]);
    }

    /**
     * @param  array<string, string>  $entries
     */
    protected function zipContent(array $entries = ['catatan.txt' => 'isi catatan']): string
    {
        $path = tempnam(sys_get_temp_dir(), 'berkas-zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();
        $content = file_get_contents($path);
        unlink($path);

        return $content;
    }

    /**
     * Create a file whose current version content exists on the faked disk.
     *
     * @param  array<string, mixed>  $fileAttributes
     */
    protected function storedFile(Folder $folder, User $uploader, ScanStatus $scanStatus = ScanStatus::Bersih, array $fileAttributes = [], string $content = self::PdfContent): File
    {
        $file = File::factory()
            ->for($folder)
            ->withVersion($scanStatus, ['size' => strlen($content), 'sha256' => hash('sha256', $content)])
            ->create(['created_by' => $uploader->id, ...$fileAttributes]);

        Storage::disk(config('berkas.disk'))->put($file->currentVersion->storage_path, $content);

        return $file;
    }
}
