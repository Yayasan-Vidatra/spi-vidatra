<?php

namespace Tests\Feature\Services;

use App\Services\FileTypeDetector;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFiles;
use Tests\TestCase;

class FileTypeDetectorTest extends TestCase
{
    use InteractsWithFiles;

    private function detect(string $content, string $extension): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'deteksi');
        file_put_contents($path, $content);

        try {
            return (new FileTypeDetector)->detect($path, $extension);
        } finally {
            unlink($path);
        }
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function matchingContent(): array
    {
        return [
            'pdf' => ['pdf', 'pdf', 'application/pdf'],
            'docx read by libmagic as zip' => ['docx', 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx read by libmagic as zip' => ['xlsx', 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'pptx read by libmagic as zip' => ['pptx', 'pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'jpg' => ['jpg', 'jpg', 'image/jpeg'],
            'jpeg' => ['jpg', 'jpeg', 'image/jpeg'],
            'png' => ['png', 'png', 'image/png'],
            'zip' => ['zip', 'zip', 'application/zip'],
            'office document uploaded as a plain zip' => ['docx', 'zip', 'application/zip'],
            'upper-case extension' => ['pdf', 'PDF', 'application/pdf'],
        ];
    }

    #[DataProvider('matchingContent')]
    public function test_detects_content_that_matches_its_extension(string $content, string $extension, string $expectedMimeType): void
    {
        $this->assertSame($expectedMimeType, $this->detect($this->content($content), $extension));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function mismatchedContent(): array
    {
        return [
            'exe as pdf' => ['executable', 'pdf'],
            'exe as zip' => ['executable', 'zip'],
            'png as pdf' => ['png', 'pdf'],
            'pdf as png' => ['pdf', 'png'],
            'jpg as png' => ['jpg', 'png'],
            'plain zip as docx' => ['zip', 'docx'],
            'xlsx as docx' => ['xlsx', 'docx'],
            'macro-enabled docm as docx' => ['docm', 'docx'],
            'text as pdf' => ['text', 'pdf'],
            'exe with its own extension' => ['executable', 'exe'],
            'svg is not allowed' => ['text', 'svg'],
        ];
    }

    #[DataProvider('mismatchedContent')]
    public function test_rejects_content_that_does_not_match_its_extension(string $content, string $extension): void
    {
        $this->assertNull($this->detect($this->content($content), $extension));
    }

    public function test_rejects_an_extension_removed_from_the_allowed_list(): void
    {
        config(['berkas.allowed_extensions' => ['pdf']]);

        $this->assertNull($this->detect($this->content('png'), 'png'));
    }

    private function content(string $kind): string
    {
        return match ($kind) {
            'pdf' => self::PdfContent,
            'docx', 'xlsx', 'pptx', 'docm' => $this->officeContent($kind),
            'jpg' => UploadedFile::fake()->image('foto.jpg')->getContent(),
            'png' => UploadedFile::fake()->image('foto.png')->getContent(),
            'zip' => $this->zipContent(),
            'executable' => $this->executableContent(),
            'text' => 'hanya teks biasa',
        };
    }
}
