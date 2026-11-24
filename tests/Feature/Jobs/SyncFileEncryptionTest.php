<?php

namespace Tests\Feature\Jobs;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Jobs\SyncFileEncryption;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\User;
use App\Services\Encryption\EncryptionKeyMissingException;
use App\Services\FileVersionContent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithFiles;
use Tests\TestCase;

class SyncFileEncryptionTest extends TestCase
{
    use InteractsWithFiles;
    use LazilyRefreshDatabase;

    private function readBack(FileVersion $version): string
    {
        return implode('', iterator_to_array(app(FileVersionContent::class)->read($version), false));
    }

    /**
     * @return array{File, FileVersion, FileVersion}
     */
    private function fileWithTwoPlainVersions(Folder $folder): array
    {
        $file = $this->storedFile($folder, User::factory()->create(), content: 'versi satu');
        $first = $file->currentVersion;
        $second = FileVersion::factory()->for($file)->create(['size' => 9, 'is_encrypted' => false]);
        $this->putVersionContent($second, 'versi dua');
        $file->forceFill(['current_version_id' => $second->id])->save();

        return [$file->fresh(), $first, $second];
    }

    public function test_encrypts_every_version_of_a_rahasia_file_and_removes_the_plain_content(): void
    {
        $this->fakeFileDisk();
        [$file, $first, $second] = $this->fileWithTwoPlainVersions(Folder::factory()->create());
        $file->forceFill(['classification' => Classification::Rahasia])->save();
        $oldPaths = [$first->storage_path, $second->storage_path];

        SyncFileEncryption::dispatchSync($file);

        foreach ([[$first, 'versi satu'], [$second, 'versi dua']] as [$version, $content]) {
            $version->refresh();
            $this->assertTrue($version->is_encrypted);
            $this->assertStringNotContainsString($content, $this->storedBytes($version));
            $this->assertSame($content, $this->readBack($version));
            $this->assertSame('versi/'.$version->stored_name, $version->storage_path);
        }
        Storage::disk(config('berkas.disk'))->assertMissing($oldPaths);
        $this->assertSame(2, AuditLog::query()->where('action', AuditAction::FileEncrypted)->where('subject_id', $file->id)->count());
    }

    public function test_clears_the_pending_marker_once_every_version_follows_the_label(): void
    {
        $this->fakeFileDisk();
        $file = $this->storedFile(Folder::factory()->create(), User::factory()->create(), fileAttributes: [
            'classification' => Classification::Rahasia,
            'encryption_pending_since' => now()->subMinutes(3),
        ]);

        SyncFileEncryption::dispatchSync($file);

        $this->assertNull($file->fresh()->encryption_pending_since);
    }

    public function test_keeps_the_pending_marker_when_the_label_changed_again_during_the_job(): void
    {
        $this->fakeFileDisk();
        $file = $this->storedFile(Folder::factory()->create(), User::factory()->create(), fileAttributes: [
            'classification' => Classification::Rahasia,
            'encryption_pending_since' => now()->subMinutes(3),
        ]);
        // Label diturunkan lagi tepat setelah versi pertama dienkripsi.
        FileVersion::updated(function () use ($file): void {
            File::query()->whereKey($file->id)->toBase()->update(['classification' => Classification::Internal->value]);
        });

        SyncFileEncryption::dispatchSync($file);

        $this->assertNotNull($file->fresh()->encryption_pending_since);
    }

    public function test_file_beneath_a_rahasia_folder_is_encrypted_whatever_its_own_label(): void
    {
        $this->fakeFileDisk();
        $internalChild = Folder::factory()->childOf(Folder::factory()->confidential()->create())->create();
        $file = $this->storedFile($internalChild, User::factory()->create(), content: 'lampiran');

        SyncFileEncryption::dispatchSync($file);

        $this->assertTrue($file->currentVersion->fresh()->is_encrypted);
    }

    public function test_decrypts_every_version_when_the_file_is_no_longer_rahasia(): void
    {
        $this->fakeFileDisk();
        $file = $this->storedFile(Folder::factory()->create(), User::factory()->create(), fileAttributes: ['classification' => Classification::Internal], content: 'sudah turun', encrypted: true);

        SyncFileEncryption::dispatchSync($file);

        $version = $file->currentVersion->fresh();
        $this->assertFalse($version->is_encrypted);
        $this->assertSame('sudah turun', $this->storedBytes($version));
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::FileDecrypted)->where('subject_id', $file->id)->count());
    }

    public function test_quarantined_version_stays_in_quarantine_when_encrypted(): void
    {
        $this->fakeFileDisk();
        $file = $this->storedFile(Folder::factory()->confidential()->create(), User::factory()->create(), content: 'isi terinfeksi');
        $version = $file->currentVersion;
        Storage::disk(config('berkas.disk'))->move($version->storage_path, 'karantina/'.$version->stored_name);
        $version->forceFill(['storage_path' => 'karantina/'.$version->stored_name])->save();

        SyncFileEncryption::dispatchSync($file);

        $version->refresh();
        $this->assertTrue($version->is_encrypted);
        $this->assertStringStartsWith('karantina/', $version->storage_path);
    }

    public function test_does_nothing_when_every_version_already_matches(): void
    {
        $this->fakeFileDisk();
        $file = $this->storedFile(Folder::factory()->create(), User::factory()->create());
        $path = $file->currentVersion->storage_path;

        SyncFileEncryption::dispatchSync($file);

        $this->assertSame($path, $file->currentVersion->fresh()->storage_path);
        $this->assertSame(0, AuditLog::query()->whereIn('action', [AuditAction::FileEncrypted, AuditAction::FileDecrypted])->count());
    }

    public function test_includes_files_in_the_trash(): void
    {
        $this->fakeFileDisk();
        $file = $this->storedFile(Folder::factory()->confidential()->create(), User::factory()->create(), content: 'di tempat sampah');
        $file->delete();

        SyncFileEncryption::dispatchSync($file);

        $this->assertTrue($file->currentVersion->fresh()->is_encrypted);
    }

    public function test_fails_without_changing_anything_when_the_key_is_missing(): void
    {
        $this->fakeFileDisk();
        $file = $this->storedFile(Folder::factory()->confidential()->create(), User::factory()->create(), content: 'tanpa kunci');
        $path = $file->currentVersion->storage_path;
        $this->withoutEncryptionKey();

        $this->assertThrows(fn () => SyncFileEncryption::dispatchSync($file), EncryptionKeyMissingException::class);

        $version = $file->currentVersion->fresh();
        $this->assertFalse($version->is_encrypted);
        $this->assertSame($path, $version->storage_path);
        $this->assertSame(['versi/'.$version->stored_name], Storage::disk(config('berkas.disk'))->allFiles());
    }
}
