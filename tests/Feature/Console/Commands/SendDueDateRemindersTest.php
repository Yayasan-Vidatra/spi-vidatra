<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\RequestItemStatus;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\RequestItem;
use App\Models\User;
use App\Notifications\DocumentRequestDueSoon;
use App\Notifications\DocumentRequestDueToday;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendDueDateRemindersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function travelToMorning(string $date): void
    {
        $this->travelTo(CarbonImmutable::parse($date.' 07:00', config('app.business_timezone')));
    }

    /**
     * An open request due on the date, with slots in the given statuses and one PIC for its unit.
     *
     * @param  list<RequestItemStatus>  $itemStatuses
     * @return array{DocumentRequest, User}
     */
    private function requestDueOn(string $dueDate, array $itemStatuses = [RequestItemStatus::Menunggu], ?Assignment $assignment = null): array
    {
        $documentRequest = DocumentRequest::factory()
            ->for($assignment ?? Assignment::factory()->create())
            ->create(['due_date' => $dueDate, 'sequence_number' => 2]);

        foreach ($itemStatuses as $status) {
            RequestItem::factory()->for($documentRequest)->withStatus($status)->create();
        }

        return [$documentRequest, User::factory()->picOf($documentRequest->unit)->create()];
    }

    public function test_h3_reminder_goes_to_the_unit_pic_three_working_days_before_the_deadline(): void
    {
        Notification::fake();
        $this->travelToMorning('2027-04-07');
        [$dueMonday, $pic] = $this->requestDueOn('2027-04-12', [RequestItemStatus::Menunggu, RequestItemStatus::PerluPerbaikan, RequestItemStatus::Diunggah]);
        [$dueTuesday, $otherPic] = $this->requestDueOn('2027-04-13');
        $picOfAnotherUnit = User::factory()->picOf($dueTuesday->unit)->create();

        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful()->expectsOutputToContain('1 pengingat H-3 dan 0 pengingat hari-H');

        Notification::assertSentTo($pic, DocumentRequestDueSoon::class, function (DocumentRequestDueSoon $notification) use ($dueMonday, $pic): bool {
            $data = $notification->toArray($pic);

            return $notification->documentRequest->is($dueMonday)
                && $data['pending_item_count'] === 2
                && $data['message'] === 'Pengingat: tenggat Permintaan 02 dari SPI jatuh pada Senin, 12 April 2027. Masih ada 2 dokumen yang belum diunggah atau perlu diperbaiki.';
        });
        Notification::assertNotSentTo([$otherPic, $picOfAnotherUnit], DocumentRequestDueSoon::class);
        $this->assertNotNull($dueMonday->fresh()->due_soon_reminded_at);
        $this->assertNull($dueTuesday->fresh()->due_soon_reminded_at);
    }

    public function test_h3_counts_working_days_around_holidays(): void
    {
        Notification::fake();
        // 8–15 Maret 2027: Nyepi, Idulfitri, dan cuti bersamanya. Tiga hari kerja sebelum Selasa 16 Maret adalah Rabu 3 Maret.
        [, $pic] = $this->requestDueOn('2027-03-16');

        $this->travelToMorning('2027-03-02');
        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travelToMorning('2027-03-03');
        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful();
        Notification::assertSentTo($pic, DocumentRequestDueSoon::class);
    }

    public function test_each_reminder_is_sent_once(): void
    {
        Notification::fake();
        $this->travelToMorning('2027-04-07');
        [, $pic] = $this->requestDueOn('2027-04-12');

        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful();
        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful();
        $this->travelToMorning('2027-04-08');
        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful();

        Notification::assertSentToTimes($pic, DocumentRequestDueSoon::class, 1);
    }

    public function test_missed_h3_is_sent_late_while_the_deadline_has_not_arrived(): void
    {
        Notification::fake();
        [, $pic] = $this->requestDueOn('2027-04-12');
        $this->travelToMorning('2027-04-09');

        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful();

        Notification::assertSentTo($pic, DocumentRequestDueSoon::class);
    }

    public function test_due_today_reminder_is_sent_on_the_deadline_once_instead_of_h3(): void
    {
        Notification::fake();
        $this->travelToMorning('2027-04-12');
        [$documentRequest, $pic] = $this->requestDueOn('2027-04-12');

        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful()->expectsOutputToContain('0 pengingat H-3 dan 1 pengingat hari-H');
        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful();

        Notification::assertSentToTimes($pic, DocumentRequestDueToday::class, 1);
        Notification::assertSentTo($pic, DocumentRequestDueToday::class, fn (DocumentRequestDueToday $notification): bool => $notification->toArray($pic)['message']
            === 'Hari ini tenggat Permintaan 02 dari SPI. Masih ada 1 dokumen yang belum diunggah atau perlu diperbaiki; unggah paling lambat pukul 23.59 WITA.');
        Notification::assertNotSentTo($pic, DocumentRequestDueSoon::class);
        $this->assertNotNull($documentRequest->fresh()->due_today_reminded_at);
        $this->assertNull($documentRequest->fresh()->due_soon_reminded_at);
    }

    public function test_due_today_reminder_is_sent_on_a_deadline_that_falls_on_a_holiday(): void
    {
        Notification::fake();
        $this->travelToMorning('2027-03-10');
        [, $pic] = $this->requestDueOn('2027-03-10');

        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful();

        Notification::assertSentTo($pic, DocumentRequestDueToday::class);
    }

    public function test_no_reminder_without_slots_awaiting_upload_or_for_closed_completed_or_past_requests(): void
    {
        Notification::fake();
        $this->travelToMorning('2027-04-12');
        [$allUploaded] = $this->requestDueOn('2027-04-12', [RequestItemStatus::Diunggah, RequestItemStatus::Diterima]);
        [$closedAssignment] = $this->requestDueOn('2027-04-12', assignment: Assignment::factory()->closed()->create());
        [$completed] = $this->requestDueOn('2027-04-12');
        $completed->forceFill(['status' => 'selesai'])->save();
        [$pastDue] = $this->requestDueOn('2027-04-09');

        $this->artisan('permintaan:kirim-pengingat')->assertSuccessful()->expectsOutputToContain('0 pengingat H-3 dan 0 pengingat hari-H');

        Notification::assertNothingSent();
        foreach ([$allUploaded, $closedAssignment, $completed, $pastDue] as $documentRequest) {
            $this->assertNull($documentRequest->fresh()->due_today_reminded_at);
        }
    }

    public function test_command_is_scheduled_every_morning_in_the_business_timezone(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'permintaan:kirim-pengingat'));

        $this->assertNotNull($event);
        $this->assertSame('0 7 * * *', $event->expression);
        $this->assertSame('Asia/Makassar', $event->timezone);
    }
}
