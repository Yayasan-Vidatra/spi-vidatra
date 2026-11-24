<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\Classification;
use App\Jobs\SyncFileEncryption;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EncryptConfidentialFilesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_schedules_only_files_whose_encryption_does_not_match_their_label(): void
    {
        Queue::fake([SyncFileEncryption::class]);
        $plainConfidential = File::factory()->confidential()->withVersion()->create();
        $plainInConfidentialFolder = File::factory()->for(Folder::factory()->confidential())->withVersion()->create();
        $trashedConfidential = File::factory()->confidential()->withVersion()->create();
        $trashedConfidential->delete();
        File::factory()->withVersion()->create();
        File::factory()->confidential()->withVersion(versionAttributes: ['is_encrypted' => true])->create();
        $encryptedInternal = File::factory()->withVersion(versionAttributes: ['is_encrypted' => true])->create(['classification' => Classification::Internal]);

        $this->artisan('berkas:enkripsi-rahasia')->assertSuccessful();

        Queue::assertPushed(SyncFileEncryption::class, 4);
        foreach ([$plainConfidential, $plainInConfidentialFolder, $trashedConfidential, $encryptedInternal] as $file) {
            Queue::assertPushed(SyncFileEncryption::class, fn (SyncFileEncryption $job): bool => $job->file->is($file));
        }
    }

    public function test_refuses_to_run_without_the_encryption_key(): void
    {
        Queue::fake([SyncFileEncryption::class]);
        config(['berkas.encryption.key_path' => sys_get_temp_dir().'/tidak-ada.key']);
        File::factory()->confidential()->withVersion()->create();

        $this->artisan('berkas:enkripsi-rahasia')->assertFailed();

        Queue::assertNothingPushed();
    }
}
