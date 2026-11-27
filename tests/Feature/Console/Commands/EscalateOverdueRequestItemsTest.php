<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\EscalationStatus;
use App\Enums\EscalationType;
use App\Enums\RequestItemStatus;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\Escalation;
use App\Models\RequestItem;
use App\Models\User;
use App\Notifications\RequestItemsEscalated;
use App\Services\EscalationRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\AssertsAuditLog;
use Tests\TestCase;

class EscalateOverdueRequestItemsTest extends TestCase
{
    use AssertsAuditLog;
    use LazilyRefreshDatabase;

    private User $overseer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->overseer = User::factory()->withRole(RoleSlug::DewanPengawas)->create();
    }

    private function travelToMorning(string $date): void
    {
        $this->travelTo(CarbonImmutable::parse($date.' 07:00', config('app.business_timezone')));
    }

    /**
     * An open request due on the date with one slot per status, named after its status.
     *
     * @param  list<RequestItemStatus>  $itemStatuses
     */
    private function requestDueOn(string $dueDate, array $itemStatuses = [RequestItemStatus::Menunggu], ?Assignment $assignment = null): DocumentRequest
    {
        $documentRequest = DocumentRequest::factory()
            ->for($assignment ?? Assignment::factory()->create())
            ->create(['due_date' => $dueDate, 'sequence_number' => 2]);

        foreach ($itemStatuses as $index => $status) {
            RequestItem::factory()->for($documentRequest)->withStatus($status)->create(['name' => 'Dokumen '.$status->label(), 'sort_order' => $index + 1]);
        }

        return $documentRequest;
    }

    public function test_slots_not_accepted_are_forwarded_to_dewan_pengawas_the_morning_after_the_tenth_working_day(): void
    {
        // Tenggat Senin 5 April 2027; hari kerja ke-10 sesudahnya Senin 19 April.
        $documentRequest = $this->requestDueOn('2027-04-05', [
            RequestItemStatus::Menunggu, RequestItemStatus::PerluPerbaikan, RequestItemStatus::Diunggah, RequestItemStatus::Diterima,
        ]);
        $secondOverseer = User::factory()->withRole(RoleSlug::DewanPengawas)->create();
        $inactiveOverseer = User::factory()->withRole(RoleSlug::DewanPengawas)->create(['is_active' => false]);
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();

        $this->travelToMorning('2027-04-19');
        $this->artisan('permintaan:eskalasi')->assertSuccessful()->expectsOutputToContain('0 slot diteruskan');
        $this->assertSame(0, Escalation::query()->count());

        $this->travelToMorning('2027-04-20');
        $this->artisan('permintaan:eskalasi')->assertSuccessful()->expectsOutputToContain('3 slot diteruskan');

        $items = $documentRequest->items()->get()->keyBy(fn (RequestItem $item): string => $item->status->value);
        foreach (['menunggu' => 'tertahan di unit', 'perlu_perbaikan' => 'tertahan di unit', 'diunggah' => 'tertahan di SPI'] as $status => $hold) {
            $item = $items[$status];
            $escalation = $item->escalation;
            $this->assertSame(EscalationType::PermintaanDokumen, $escalation->type);
            $this->assertSame(EscalationStatus::Diteruskan, $escalation->status);
            $this->assertSame('2027-04-19', $escalation->charter_deadline->toDateString());
            $this->assertEquals(now(), $escalation->escalated_at);
            $log = $this->assertAuditLogged(AuditAction::RequestItemEscalated, $item, [
                'escalation_id' => $escalation->id,
                'document_request_id' => $documentRequest->id,
                'assignment_id' => $documentRequest->assignment_id,
                'name' => $item->name,
                'status' => $status,
                'hold' => $hold,
                'due_date' => '2027-04-05',
                'charter_deadline' => '2027-04-19',
                'forwarded_to' => [$this->overseer->id, $secondOverseer->id],
            ]);
            $this->assertNull($log->user_id);
        }
        $this->assertNull($items['diterima']->escalation);

        Notification::assertSentToTimes($this->overseer, RequestItemsEscalated::class, 1);
        Notification::assertSentTo($secondOverseer, RequestItemsEscalated::class);
        Notification::assertNotSentTo([$inactiveOverseer, $auditor], RequestItemsEscalated::class);
        Notification::assertSentTo($this->overseer, RequestItemsEscalated::class, function (RequestItemsEscalated $notification) use ($documentRequest): bool {
            $data = $notification->toArray($this->overseer);

            return $data['message'] === sprintf(
                'Eskalasi tenggat Piagam: Permintaan 02 ke %s (penugasan %s) belum dipenuhi 10 hari kerja setelah tenggat 5 April 2027 (batas 19 April 2027). 3 dokumen belum diterima: "Dokumen Menunggu" (Menunggu, tertahan di unit); "Dokumen Perlu Perbaikan" (Perlu Perbaikan, tertahan di unit); "Dokumen Diunggah" (Diunggah, tertahan di SPI).',
                $documentRequest->unit->name,
                $documentRequest->assignment->code,
            )
                && array_column($data['items'], 'hold_label') === ['tertahan di unit', 'tertahan di unit', 'tertahan di SPI']
                && $data['charter_deadline'] === '2027-04-19';
        });
    }

    public function test_the_piagam_period_skips_holidays(): void
    {
        // Tenggat Jumat 5 Maret 2027. Libur 8–15 Maret dan 25–26 Maret: hari kerja ke-10 adalah Rabu 31 Maret.
        $documentRequest = $this->requestDueOn('2027-03-05');

        $this->travelToMorning('2027-03-31');
        $this->artisan('permintaan:eskalasi')->assertSuccessful();
        $this->assertNull($documentRequest->items()->first()->escalation);

        $this->travelToMorning('2027-04-01');
        $this->artisan('permintaan:eskalasi')->assertSuccessful();
        $this->assertSame('2027-03-31', $documentRequest->items()->first()->escalation->charter_deadline->toDateString());
    }

    public function test_does_not_run_on_weekends_or_holidays(): void
    {
        // Tenggat 5 April sudah melewati batas Piagam pada kedua tanggal ini.
        $this->requestDueOn('2027-04-05');

        foreach (['2027-04-24', '2027-05-17'] as $nonWorkingDay) {
            $this->travelToMorning($nonWorkingDay);
            $this->artisan('permintaan:eskalasi')->assertSuccessful()->expectsOutputToContain('bukan hari kerja');
        }

        $this->assertSame(0, Escalation::query()->count());
        Notification::assertNothingSent();
    }

    public function test_each_slot_is_escalated_only_once_even_after_the_deadline_is_moved(): void
    {
        $documentRequest = $this->requestDueOn('2027-04-05');
        $this->travelToMorning('2027-04-20');
        $this->artisan('permintaan:eskalasi')->assertSuccessful();

        $documentRequest->forceFill(['due_date' => '2027-04-01'])->save();
        $this->travelToMorning('2027-04-21');
        $this->artisan('permintaan:eskalasi')->assertSuccessful()->expectsOutputToContain('0 slot diteruskan');

        $this->assertSame(1, Escalation::query()->count());
        $this->assertSame('2027-04-19', Escalation::query()->sole()->charter_deadline->toDateString());
        Notification::assertSentToTimes($this->overseer, RequestItemsEscalated::class, 1);
    }

    public function test_a_new_slot_of_an_already_escalated_request_is_escalated_on_its_own(): void
    {
        $documentRequest = $this->requestDueOn('2027-04-05');
        $this->travelToMorning('2027-04-20');
        $this->artisan('permintaan:eskalasi')->assertSuccessful();
        $lateSlot = RequestItem::factory()->for($documentRequest)->create(['name' => 'Dokumen susulan', 'sort_order' => 2]);

        $this->travelToMorning('2027-04-21');
        $this->artisan('permintaan:eskalasi')->assertSuccessful()->expectsOutputToContain('1 slot diteruskan');

        $this->assertNotNull($lateSlot->escalation);
        Notification::assertSentToTimes($this->overseer, RequestItemsEscalated::class, 2);
    }

    public function test_slots_of_closed_assignments_or_resolved_slots_are_not_escalated(): void
    {
        $this->requestDueOn('2027-04-05', assignment: Assignment::factory()->closed()->create());
        $this->requestDueOn('2027-04-05', [RequestItemStatus::Diterima]);
        $this->requestDueOn('2027-04-07');

        $this->travelToMorning('2027-04-20');
        $this->artisan('permintaan:eskalasi')->assertSuccessful()->expectsOutputToContain('0 slot diteruskan');

        $this->assertSame(0, Escalation::query()->count());
        Notification::assertNothingSent();
    }

    public function test_heads_of_spi_are_notified_alongside_dewan_pengawas_once_each(): void
    {
        $headOfSpi = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $this->mock(EscalationRecipients::class, function ($mock) use ($headOfSpi): void {
            $mock->shouldReceive('all')->andReturn(new Collection([$this->overseer, $headOfSpi]));
        });
        $this->requestDueOn('2027-04-05');

        $this->travelToMorning('2027-04-20');
        $this->artisan('permintaan:eskalasi')->assertSuccessful();

        Notification::assertSentToTimes($this->overseer, RequestItemsEscalated::class, 1);
        Notification::assertSentToTimes($headOfSpi, RequestItemsEscalated::class, 1);
    }

    public function test_recipients_are_active_dewan_pengawas_without_duplicates_and_no_heads_of_spi_yet(): void
    {
        User::factory()->withRole(RoleSlug::DewanPengawas)->create(['is_active' => false]);
        User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $recipients = app(EscalationRecipients::class);

        $this->assertSame([$this->overseer->id], $recipients->all()->modelKeys());
        $this->assertTrue($recipients->headsOfSpi()->isEmpty());
    }

    public function test_command_is_scheduled_on_weekday_mornings_in_the_business_timezone(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'permintaan:eskalasi'));

        $this->assertNotNull($event);
        $this->assertSame('0 7 * * 1-5', $event->expression);
        $this->assertSame('Asia/Makassar', $event->timezone);
    }
}
