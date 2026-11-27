<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\DocumentRequestStatus;
use App\Enums\RequestItemStatus;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\Escalation;
use App\Models\RequestItem;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\DocumentRequestReceived;
use App\Notifications\RequestItemsEscalated;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2027-04-20 09:00', config('app.business_timezone')));
    }

    private function unit(string $code): Unit
    {
        return Unit::query()->where('code', $code)->sole();
    }

    private function slot(Assignment $assignment, string $dueDate, RequestItemStatus $status = RequestItemStatus::Menunggu, string $name = 'Laporan kas'): RequestItem
    {
        $documentRequest = DocumentRequest::factory()->for($assignment)->create(['due_date' => $dueDate, 'unit_id' => $this->unit('SMP')->id]);

        return RequestItem::factory()->for($documentRequest)->withStatus($status)->create(['name' => $name]);
    }

    public function test_pic_sees_own_notifications_linked_to_h08_without_spi_lists(): void
    {
        $pic = User::factory()->picOf($this->unit('SMP'))->withTwoFactor()->acceptedConfidentiality()->create();
        $documentRequest = $this->slot(Assignment::factory()->create(), '2027-04-30')->documentRequest;
        $pic->notify(new DocumentRequestReceived($documentRequest));
        $this->userWithRole(RoleSlug::AuditorSpi)->notify(new DocumentRequestReceived($documentRequest));

        $this->actingAsVerified($pic)->get(route('dasbor'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dasbor')
            ->has('notifications', 1)
            ->where('notifications.0.message', 'Permintaan dokumen baru dari SPI: 1 dokumen, tenggat 30 April 2027.')
            ->where('notifications.0.is_read', false)
            ->where('notifications.0.url', route('permintaan-unit.show', $documentRequest))
            ->where('auth.user.unread_notification_count', 1)
            ->where('overdueItems', null)
            ->where('escalations', null)
        );
    }

    public function test_auditor_sees_overdue_slots_of_own_active_assignments_only(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $overdue = $this->slot($assignment, '2027-04-12', RequestItemStatus::PerluPerbaikan, 'Rekap gaji');
        $older = $this->slot($assignment, '2027-04-05', name: 'Laporan kas');
        $this->slot($assignment, '2027-04-12', RequestItemStatus::Diunggah);
        $this->slot($assignment, '2027-04-20');
        $this->slot(Assignment::factory()->create(), '2027-04-12');
        $this->slot(Assignment::factory()->withAuditor($auditor)->closed()->create(), '2027-04-12');
        $completed = $this->slot($assignment, '2027-04-12');
        $completed->documentRequest->forceFill(['status' => DocumentRequestStatus::Selesai])->save();

        $this->actingAsVerified($auditor)->get(route('dasbor'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overdueItems.total', 2)
            ->has('overdueItems.items', 2)
            ->where('overdueItems.items.0.id', $older->id)
            ->where('overdueItems.items.0.days_late', 15)
            ->where('overdueItems.items.1.id', $overdue->id)
            ->where('overdueItems.items.1.name', 'Rekap gaji')
            ->where('overdueItems.items.1.status_label', 'Perlu Perbaikan')
            ->where('overdueItems.items.1.assignment_code', $assignment->code)
            ->where('overdueItems.items.1.unit', 'SMP YPVDP')
            ->where('overdueItems.items.1.due_date', '2027-04-12')
            ->where('overdueItems.items.1.days_late', 8)
            ->where('overdueItems.items.1.url', route('penugasan.permintaan.show', [$assignment, $overdue->documentRequest]))
            ->where('escalations', null)
        );
    }

    public function test_dewan_pengawas_sees_overdue_slots_of_all_assignments_and_open_escalations(): void
    {
        $overseer = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $heldAtSpi = $this->slot($assignment, '2027-04-05', RequestItemStatus::Diunggah, 'Rekap gaji');
        $heldAtUnit = $this->slot(Assignment::factory()->create(), '2027-04-05');
        $escalation = Escalation::factory()->create(['subject_id' => $heldAtSpi->id, 'charter_deadline' => '2027-04-19']);
        Escalation::factory()->create(['subject_id' => $heldAtUnit->id, 'escalated_at' => now()->subDay()]);
        Escalation::factory()->resolved()->create();

        $this->actingAsVerified($overseer)->get(route('dasbor'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overdueItems.total', 1)
            ->where('overdueItems.items.0.id', $heldAtUnit->id)
            ->where('escalations.total', 2)
            ->where('escalations.items.1.id', $escalation->id)
            ->where('escalations.items.1.name', 'Rekap gaji')
            ->where('escalations.items.1.status_label', 'Diunggah')
            ->where('escalations.items.1.hold_label', 'tertahan di SPI')
            ->where('escalations.items.1.due_date', '2027-04-05')
            ->where('escalations.items.1.charter_deadline', '2027-04-19')
            ->where('escalations.items.1.url', route('penugasan.permintaan.show', [$assignment, $heldAtSpi->documentRequest]))
            ->where('escalations.items.0.hold_label', 'tertahan di unit')
        );
    }

    public function test_escalation_notification_links_dewan_pengawas_to_h07(): void
    {
        $overseer = $this->userWithRole(RoleSlug::DewanPengawas);
        $item = $this->slot(Assignment::factory()->create(), '2027-04-05');
        $overseer->notify(new RequestItemsEscalated($item->documentRequest, collect([$item]), CarbonImmutable::parse('2027-04-19')));

        $this->actingAsVerified($overseer)->get(route('dasbor'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.0.url', route('penugasan.permintaan.show', [$item->documentRequest->assignment_id, $item->documentRequest]))
        );
    }

    public function test_user_marks_own_notifications_read_one_or_all(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $documentRequest = $this->slot(Assignment::factory()->create(), '2027-04-30')->documentRequest;
        $auditor->notify(new DocumentRequestReceived($documentRequest));
        $auditor->notify(new DocumentRequestReceived($documentRequest));
        [$first, $second] = $auditor->notifications()->get()->all();

        $this->actingAsVerified($auditor)->post(route('notifikasi.dibaca', $first->id))->assertRedirect(route('dasbor'));
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNull($second->fresh()->read_at);

        $this->actingAsVerified($auditor)->post(route('notifikasi.dibaca-semua'))->assertRedirect(route('dasbor'));
        $this->assertSame(0, $auditor->unreadNotifications()->count());
    }

    public function test_notification_of_another_user_cannot_be_marked_read(): void
    {
        $owner = $this->userWithRole(RoleSlug::AuditorSpi);
        $owner->notify(new DocumentRequestReceived($this->slot(Assignment::factory()->create(), '2027-04-30')->documentRequest));
        $notification = $owner->notifications()->sole();

        $this->actingAsVerified($this->userWithRole(RoleSlug::AuditorSpi))
            ->post(route('notifikasi.dibaca', $notification->id))
            ->assertNotFound();
        $this->actingAsVerified($this->userWithRole(RoleSlug::AuditorSpi))->post(route('notifikasi.dibaca-semua'));

        $this->assertNull($notification->fresh()->read_at);
    }
}
