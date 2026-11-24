<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Jobs\SyncFileEncryption;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\TestCase;

class CheckPendingFileEncryptionTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use LazilyRefreshDatabase;

    /**
     * A file labelled Rahasia whose only version is still stored unencrypted.
     */
    private function pendingConfidentialFile(?int $pendingForMinutes): File
    {
        return File::factory()->confidential()->withVersion()->create([
            'encryption_pending_since' => $pendingForMinutes === null ? null : now()->subMinutes($pendingForMinutes),
        ]);
    }

    public function test_reschedules_and_logs_a_file_pending_longer_than_ten_minutes(): void
    {
        $this->freezeSecond();
        Queue::fake([SyncFileEncryption::class]);
        Log::spy();
        $file = $this->pendingConfidentialFile(11);
        $pendingSince = $file->encryption_pending_since;

        $this->artisan('berkas:periksa-enkripsi')->assertSuccessful();

        Queue::assertPushed(SyncFileEncryption::class, fn (SyncFileEncryption $job): bool => $job->file->is($file));
        $this->assertEquals(now(), $file->fresh()->encryption_pending_since);
        $log = $this->assertAuditLogged(AuditAction::FileEncryptionRescheduled, $file, ['pending_since' => $pendingSince->toIso8601String()]);
        $this->assertNull($log->user_id);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['file_ids'] === [$file->id]);
    }

    public function test_reschedules_a_mismatched_file_that_was_never_scheduled(): void
    {
        Queue::fake([SyncFileEncryption::class]);
        $file = $this->pendingConfidentialFile(null);

        $this->artisan('berkas:periksa-enkripsi')->assertSuccessful();

        Queue::assertPushed(SyncFileEncryption::class, fn (SyncFileEncryption $job): bool => $job->file->is($file));
        $this->assertAuditLogged(AuditAction::FileEncryptionRescheduled, $file, ['pending_since' => null]);
    }

    public function test_leaves_a_file_pending_for_less_than_ten_minutes_alone(): void
    {
        Queue::fake([SyncFileEncryption::class]);
        $this->pendingConfidentialFile(9);

        $this->artisan('berkas:periksa-enkripsi')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::FileEncryptionRescheduled)->count());
    }

    public function test_threshold_can_be_changed(): void
    {
        Queue::fake([SyncFileEncryption::class]);
        $this->pendingConfidentialFile(3);

        $this->artisan('berkas:periksa-enkripsi', ['--menit' => 2])->assertSuccessful();

        Queue::assertPushed(SyncFileEncryption::class, 1);
    }

    public function test_detects_files_waiting_for_decryption_and_files_beneath_rahasia_folders(): void
    {
        Queue::fake([SyncFileEncryption::class]);
        $waitingForDecryption = File::factory()->withVersion(versionAttributes: ['is_encrypted' => true])->create(['classification' => Classification::Internal]);
        $beneathConfidentialFolder = File::factory()->for(Folder::factory()->childOf(Folder::factory()->confidential()->create()))->withVersion()->create();
        $trashed = $this->pendingConfidentialFile(null);
        $trashed->delete();

        $this->artisan('berkas:periksa-enkripsi')->assertSuccessful();

        Queue::assertPushed(SyncFileEncryption::class, 3);
        foreach ([$waitingForDecryption, $beneathConfidentialFolder, $trashed] as $file) {
            Queue::assertPushed(SyncFileEncryption::class, fn (SyncFileEncryption $job): bool => $job->file->is($file));
        }
    }

    public function test_ignores_files_whose_content_already_follows_the_label(): void
    {
        Queue::fake([SyncFileEncryption::class]);
        File::factory()->confidential()->withVersion(versionAttributes: ['is_encrypted' => true])->create(['encryption_pending_since' => now()->subHour()]);
        File::factory()->withVersion()->create();

        $this->artisan('berkas:periksa-enkripsi')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_reports_instead_of_rescheduling_when_the_key_is_missing(): void
    {
        Queue::fake([SyncFileEncryption::class]);
        Log::spy();
        $this->withoutEncryptionKey();
        $this->pendingConfidentialFile(30);

        $this->artisan('berkas:periksa-enkripsi')->assertFailed();

        Queue::assertNothingPushed();
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $context['file_count'] === 1);
    }

    public function test_is_scheduled_every_five_minutes(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'berkas:periksa-enkripsi'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
