<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\TestCase;

class PurgeTrashTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use LazilyRefreshDatabase;

    private function trashedFile(int $daysAgo): File
    {
        $file = $this->storedFile(Folder::factory()->create(), User::factory()->create());
        $file->forceFill(['deleted_at' => now()->subDays($daysAgo)])->save();

        return $file;
    }

    public function test_permanently_deletes_files_that_reached_thirty_days_in_the_trash(): void
    {
        $this->freezeSecond();
        $this->fakeFileDisk();
        $expired = $this->trashedFile(30);
        $restoredFrom = $expired->currentVersion;
        $restoredCopy = FileVersion::factory()->for($expired)->create(['restored_from_version_id' => $restoredFrom->id]);
        $this->putVersionContent($restoredCopy, 'salinan');
        $recent = $this->trashedFile(29);
        $active = $this->storedFile(Folder::factory()->create(), User::factory()->create());

        $this->artisan('berkas:kosongkan-sampah')->assertSuccessful();

        $this->assertNull(File::withTrashed()->find($expired->id));
        $this->assertSame(0, FileVersion::query()->where('file_id', $expired->id)->count());
        Storage::disk(config('berkas.disk'))->assertMissing([$restoredFrom->storage_path, $restoredCopy->storage_path]);
        $this->assertTrue(File::withTrashed()->find($recent->id)->trashed());
        Storage::disk(config('berkas.disk'))->assertExists($recent->currentVersion->storage_path);
        $this->assertNotNull(File::query()->find($active->id));
        $log = AuditLog::query()->where('action', AuditAction::FilePurged)->sole();
        $this->assertSame($expired->id, $log->subject_id);
        $this->assertNull($log->user_id);
        $this->assertEquals(['name' => $expired->original_name, 'folder_id' => $expired->folder_id, 'version_count' => 2, 'automatic' => true], $log->properties);
    }

    public function test_keeps_the_audit_trail_of_the_purged_file(): void
    {
        $this->fakeFileDisk();
        $expired = $this->trashedFile(31);
        $earlierLogCount = AuditLog::query()->count();

        $this->artisan('berkas:kosongkan-sampah')->assertSuccessful();

        $this->assertSame($earlierLogCount + 1, AuditLog::query()->count());
        $this->assertAuditLogged(AuditAction::FilePurged, $expired);
    }

    public function test_is_scheduled_daily(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'berkas:kosongkan-sampah'));

        $this->assertNotNull($event);
        $this->assertSame('0 2 * * *', $event->expression);
    }
}
