<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\RequestItemStatus;
use App\Jobs\CopyAcceptedRequestItem;
use App\Models\File;
use App\Models\RequestItem;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckPendingEvidenceCopiesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acceptedItem(?int $pendingForMinutes, bool $copied = false): RequestItem
    {
        return RequestItem::factory()->withStatus(RequestItemStatus::Diterima)->create([
            'evidence_copy_pending_since' => $pendingForMinutes === null ? null : now()->subMinutes($pendingForMinutes),
            'evidence_file_id' => $copied ? File::factory()->create()->id : null,
        ]);
    }

    public function test_reschedules_copies_pending_longer_than_an_hour_and_logs_them(): void
    {
        $this->freezeSecond();
        Queue::fake([CopyAcceptedRequestItem::class]);
        Log::spy();
        $stale = $this->acceptedItem(61);
        $neverScheduled = $this->acceptedItem(null);
        $recent = $this->acceptedItem(30);
        $copied = $this->acceptedItem(null, copied: true);
        $notAccepted = RequestItem::factory()->withStatus(RequestItemStatus::Diunggah)->create();

        $this->artisan('permintaan:periksa-salinan')->assertSuccessful()->expectsOutputToContain('2 salinan dijadwalkan ulang');

        Queue::assertPushed(CopyAcceptedRequestItem::class, 2);
        Queue::assertPushed(CopyAcceptedRequestItem::class, fn (CopyAcceptedRequestItem $job): bool => $job->item->is($stale));
        Queue::assertPushed(CopyAcceptedRequestItem::class, fn (CopyAcceptedRequestItem $job): bool => $job->item->is($neverScheduled));
        $this->assertEquals(now(), $stale->fresh()->evidence_copy_pending_since);
        $this->assertEquals(now()->subMinutes(30), $recent->fresh()->evidence_copy_pending_since);
        $this->assertNull($copied->fresh()->evidence_copy_pending_since);
        $this->assertNull($notAccepted->fresh()->evidence_copy_pending_since);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['request_item_ids'] === [$stale->id, $neverScheduled->id]);
    }

    public function test_nothing_pending_logs_nothing(): void
    {
        Queue::fake([CopyAcceptedRequestItem::class]);
        Log::spy();

        $this->artisan('permintaan:periksa-salinan')->assertSuccessful();

        Queue::assertNothingPushed();
        Log::shouldNotHaveReceived('warning');
    }

    public function test_command_is_scheduled_every_fifteen_minutes(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'permintaan:periksa-salinan'));

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
    }
}
