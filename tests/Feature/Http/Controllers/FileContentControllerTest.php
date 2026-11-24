<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class FileContentControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    /**
     * @return array{User, Assignment, File}
     */
    private function auditorWithFile(string $name = 'Laporan Akhir.pdf'): array
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $file = $this->storedFile(Folder::factory()->for($assignment)->create(), $auditor, fileAttributes: ['original_name' => $name]);

        return [$auditor, $assignment, $file];
    }

    /**
     * Ask the application for a content URL the way the detail page does.
     */
    private function contentUrl(User $user, File $file, string $route = 'berkas.versi.unduh'): string
    {
        return $this->actingAsVerified($user)->get(route($route, [$file, $file->current_version_id]))->headers->get('Location');
    }

    public function test_download_serves_the_content_as_an_attachment_with_safe_headers(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();

        $response = $this->get($this->contentUrl($auditor, $file));

        $response->assertOk();
        $this->assertSame(self::PdfContent, $response->streamedContent());
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Disposition', 'attachment; filename="Laporan Akhir.pdf"');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertAuditLogged(AuditAction::FileDownloaded, $file, ['version' => 1, 'version_id' => $file->current_version_id], $auditor);
    }

    public function test_preview_serves_the_content_inline_and_is_logged_as_opened(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();

        $response = $this->get($this->contentUrl($auditor, $file, 'berkas.versi.pratinjau'));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'inline; filename="Laporan Akhir.pdf"');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertAuditLogged(AuditAction::FileViewed, $file, ['version' => 1, 'version_id' => $file->current_version_id], $auditor);
    }

    public function test_content_type_comes_from_the_detected_type_not_the_stored_bytes(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile('Foto.png');
        $file->currentVersion->forceFill(['mime_type' => 'image/png'])->save();

        $response = $this->get($this->contentUrl($auditor, $file->fresh()));

        $response->assertHeader('Content-Type', 'image/png');
    }

    public function test_file_name_cannot_break_out_of_the_content_disposition_header(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile('Laporan "final"; filename=evil.exe; Ünduh %41.pdf');

        $response = $this->get($this->contentUrl($auditor, $file));

        $this->assertSame(
            "attachment; filename=\"Laporan _final__ filename=evil.exe_ Unduh _41.pdf\"; filename*=utf-8''Laporan%20%22final%22%3B%20filename%3Devil.exe%3B%20%C3%9Cnduh%20%2541.pdf",
            $response->headers->get('Content-Disposition'),
        );
    }

    public function test_expired_url_is_refused(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();
        $url = $this->contentUrl($auditor, $file);

        $this->travel(5)->minutes();
        $this->travel(1)->seconds();

        $this->get($url)->assertForbidden();
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::FileDownloaded)->count());
    }

    public function test_url_is_still_valid_just_before_five_minutes(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();
        $url = $this->contentUrl($auditor, $file);

        $this->travel(4)->minutes();

        $this->get($url)->assertOk();
    }

    public function test_url_issued_to_another_user_is_refused_even_when_that_user_has_access(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();
        $url = $this->contentUrl($auditor, $file);

        $this->actingAsVerified($this->userWithRole(RoleSlug::DewanPengawas))->get($url)->assertForbidden();
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::FileDownloaded)->count());
    }

    public function test_url_from_an_earlier_session_of_the_same_user_is_refused(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();
        $url = $this->contentUrl($auditor, $file);

        $this->post(route('logout'));

        $this->actingAsVerified($auditor)->get($url)->assertForbidden();
    }

    public function test_tampered_url_is_refused(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();
        $otherFile = $this->storedFile($file->folder, $auditor);
        $url = $this->contentUrl($auditor, $file);

        $tampered = str_replace("/berkas/{$file->id}/versi/{$file->current_version_id}/", "/berkas/{$otherFile->id}/versi/{$otherFile->current_version_id}/", $url);

        $this->get($tampered)->assertForbidden();
    }

    public function test_url_without_a_signature_is_refused(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();

        $this->actingAsVerified($auditor)
            ->get(route('berkas.konten', ['file' => $file, 'version' => $file->current_version_id, 'mode' => 'unduh', 'u' => $auditor->id]))
            ->assertForbidden();
    }

    public function test_signed_url_built_for_another_user_cannot_be_used_without_their_session(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $forgedUrl = URL::temporarySignedRoute('berkas.konten', now()->addMinutes(5), [
            'file' => $file->id,
            'version' => $file->current_version_id,
            'mode' => 'unduh',
            'u' => $dewanPengawas->id,
            's' => 'tebakan',
        ]);

        $this->actingAsVerified($dewanPengawas)->get($forgedUrl)->assertForbidden();
    }

    public function test_access_is_checked_again_when_the_url_is_used(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();
        $url = $this->contentUrl($auditor, $file);

        $file->forceFill(['classification' => Classification::Rahasia])->save();

        $this->get($url)->assertForbidden();
    }

    public function test_guest_with_a_content_url_is_redirected_to_login(): void
    {
        $this->fakeFileDisk();
        [$auditor, , $file] = $this->auditorWithFile();
        $url = $this->contentUrl($auditor, $file);

        $this->app['auth']->forgetGuards();
        $this->app['session']->flush();

        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_encrypted_file_is_served_as_its_original_content(): void
    {
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $content = str_repeat('isi asli berkas rahasia ', 60_000);
        $file = $this->storedFile(
            Folder::factory()->confidential()->create(),
            $dewanPengawas,
            fileAttributes: ['classification' => Classification::Rahasia],
            content: $content,
            encrypted: true,
        );

        $response = $this->get($this->contentUrl($dewanPengawas, $file));

        $response->assertOk();
        $response->assertHeader('Content-Length', (string) strlen($content));
        $this->assertSame($content, $response->streamedContent());
    }

    public function test_encrypted_file_is_not_served_without_the_encryption_key(): void
    {
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $file = $this->storedFile(Folder::factory()->confidential()->create(), $dewanPengawas, encrypted: true);
        $url = $this->contentUrl($dewanPengawas, $file);
        $this->withoutEncryptionKey();

        $this->get($url)->assertServiceUnavailable();
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::FileDownloaded)->count());
    }
}
