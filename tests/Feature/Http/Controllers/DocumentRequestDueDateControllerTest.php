<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\DocumentRequestStatus;
use App\Enums\EscalationStatus;
use App\Enums\FolderPurpose;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\Escalation;
use App\Models\Folder;
use App\Models\RequestItem;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\DocumentRequestDueDateChanged;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class DocumentRequestDueDateControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    private User $auditor;

    private Assignment $assignment;

    private DocumentRequest $documentRequest;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2027-04-07 10:00', config('app.business_timezone')));
        $this->auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $this->assignment = Assignment::factory()->withAuditor($this->auditor)->create();
        Folder::factory()->for($this->assignment)->forPurpose(FolderPurpose::PermintaanDokumen)->create();
        $this->documentRequest = DocumentRequest::factory()->for($this->assignment)->create([
            'unit_id' => Unit::query()->where('code', 'SMP')->value('id'),
            'sequence_number' => 2,
            'due_date' => '2027-04-12',
            'due_soon_reminded_at' => now()->subDay(),
            'due_today_reminded_at' => now()->subHour(),
        ]);
        RequestItem::factory()->for($this->documentRequest)->create();
    }

    private function url(?DocumentRequest $documentRequest = null, ?Assignment $assignment = null): string
    {
        return route('penugasan.permintaan.tenggat.update', [$assignment ?? $this->assignment, $documentRequest ?? $this->documentRequest]);
    }

    public function test_assigned_auditor_changes_the_deadline_with_a_reason(): void
    {
        $this->freezeSecond();
        $pic = User::factory()->picOf($this->documentRequest->unit)->create();
        $otherUnitPic = User::factory()->picOf(Unit::query()->where('code', 'SD')->sole())->create();

        $response = $this->actingAsVerified($this->auditor)->patch($this->url(), [
            'due_date' => '2027-04-26',
            'reason' => 'Unit sedang ujian akhir semester.',
        ]);

        $response->assertRedirect(route('penugasan.permintaan.show', [$this->assignment, $this->documentRequest]));
        $response->assertInertiaFlash('success', 'Tenggat diubah menjadi 26 April 2027. PIC unit diberi tahu.');
        $documentRequest = $this->documentRequest->fresh();
        $this->assertSame('2027-04-26', $documentRequest->due_date->toDateString());
        $this->assertEquals(now(), $documentRequest->due_date_changed_at);
        $this->assertNull($documentRequest->due_soon_reminded_at);
        $this->assertNull($documentRequest->due_today_reminded_at);
        $this->assertAuditLogged(AuditAction::DocumentRequestDueDateChanged, $documentRequest, [
            'assignment_id' => $this->assignment->id,
            'unit' => 'SMP',
            'previous_due_date' => '2027-04-12',
            'due_date' => '2027-04-26',
            'reason' => 'Unit sedang ujian akhir semester.',
        ], $this->auditor);
        Notification::assertSentTo($pic, DocumentRequestDueDateChanged::class, fn (DocumentRequestDueDateChanged $notification): bool => $notification->toArray($pic)['message']
            === 'SPI mengubah tenggat Permintaan 02 dari 12 April 2027 menjadi 26 April 2027. Alasan: Unit sedang ujian akhir semester.');
        Notification::assertNotSentTo($otherUnitPic, DocumentRequestDueDateChanged::class);
    }

    public function test_dewan_pengawas_can_change_the_deadline(): void
    {
        $this->actingAsVerified($this->userWithRole(RoleSlug::DewanPengawas))
            ->patch($this->url(), ['due_date' => '2027-04-20', 'reason' => 'Diperpanjang.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2027-04-20', $this->documentRequest->fresh()->due_date->toDateString());
    }

    public function test_escalations_already_forwarded_stay_as_they_are(): void
    {
        $escalation = Escalation::factory()->create(['subject_id' => $this->documentRequest->items()->first()->id, 'charter_deadline' => '2027-04-01']);

        $this->actingAsVerified($this->auditor)->patch($this->url(), ['due_date' => '2027-05-03', 'reason' => 'Diperpanjang.']);

        $escalation->refresh();
        $this->assertSame(EscalationStatus::Diteruskan, $escalation->status);
        $this->assertSame('2027-04-01', $escalation->charter_deadline->toDateString());
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'no reason' => [['due_date' => '2027-04-20', 'reason' => ''], 'reason', 'Alasan perubahan tenggat wajib diisi.'],
            'reason too long' => [['due_date' => '2027-04-20', 'reason' => str_repeat('a', 501)], 'reason', 'Alasan paling panjang 500 karakter.'],
            'no date' => [['due_date' => '', 'reason' => 'Diperpanjang.'], 'due_date', 'Tenggat baru wajib diisi.'],
            'bad date' => [['due_date' => '20-04-2027', 'reason' => 'Diperpanjang.'], 'due_date', 'Tenggat tidak valid.'],
            'before today' => [['due_date' => '2027-04-06', 'reason' => 'Diperpanjang.'], 'due_date', 'Tenggat tidak boleh sebelum hari ini.'],
            'same date' => [['due_date' => '2027-04-12', 'reason' => 'Diperpanjang.'], 'due_date', 'Tenggat baru sama dengan tenggat sekarang.'],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_change_needs_a_valid_new_date_and_a_reason(array $payload, string $field, string $message): void
    {
        $this->actingAsVerified($this->auditor)->patch($this->url(), $payload)->assertSessionHasErrors([$field => $message]);

        $this->assertSame('2027-04-12', $this->documentRequest->fresh()->due_date->toDateString());
        Notification::assertNothingSent();
    }

    public function test_today_and_a_holiday_are_accepted_as_the_new_deadline(): void
    {
        $this->actingAsVerified($this->auditor)->patch($this->url(), ['due_date' => '2027-04-07', 'reason' => 'Dipercepat.'])->assertSessionHasNoErrors();
        $this->actingAsVerified($this->auditor)->patch($this->url(), ['due_date' => '2027-05-17', 'reason' => 'Diperpanjang.'])->assertSessionHasNoErrors();

        $this->assertSame('2027-05-17', $this->documentRequest->fresh()->due_date->toDateString());
    }

    /**
     * @return array<string, array{RoleSlug}>
     */
    public static function rolesOutsideSpi(): array
    {
        return [
            'pic unit' => [RoleSlug::PicUnit],
            'pengurus yayasan' => [RoleSlug::PengurusYayasan],
            'super admin' => [RoleSlug::SuperAdmin],
        ];
    }

    #[DataProvider('rolesOutsideSpi')]
    public function test_roles_outside_spi_cannot_change_the_deadline(RoleSlug $roleSlug): void
    {
        $this->actingAsVerified($this->userWithRole($roleSlug))
            ->patch($this->url(), ['due_date' => '2027-04-20', 'reason' => 'Diperpanjang.'])
            ->assertForbidden();

        $this->assertSame('2027-04-12', $this->documentRequest->fresh()->due_date->toDateString());
    }

    public function test_auditor_not_assigned_to_the_assignment_cannot_change_the_deadline(): void
    {
        $this->actingAsVerified($this->userWithRole(RoleSlug::AuditorSpi))
            ->patch($this->url(), ['due_date' => '2027-04-20', 'reason' => 'Diperpanjang.'])
            ->assertForbidden();
    }

    public function test_deadline_of_a_completed_request_or_closed_assignment_cannot_be_changed(): void
    {
        $this->documentRequest->forceFill(['status' => DocumentRequestStatus::Selesai])->save();
        $this->actingAsVerified($this->auditor)->patch($this->url(), ['due_date' => '2027-04-20', 'reason' => 'Diperpanjang.'])->assertForbidden();

        $this->documentRequest->forceFill(['status' => DocumentRequestStatus::Terbuka])->save();
        $this->assignment->forceFill(['status' => 'ditutup', 'closed_at' => now()])->save();
        $this->actingAsVerified($this->auditor)->patch($this->url(), ['due_date' => '2027-04-20', 'reason' => 'Diperpanjang.'])->assertForbidden();
    }

    public function test_request_of_another_assignment_is_not_found(): void
    {
        $otherAssignment = Assignment::factory()->withAuditor($this->auditor)->create();

        $this->actingAsVerified($this->auditor)
            ->patch($this->url(assignment: $otherAssignment), ['due_date' => '2027-04-20', 'reason' => 'Diperpanjang.'])
            ->assertNotFound();
    }

    public function test_detail_page_offers_the_change_with_upcoming_holidays_only_to_who_may_change_it(): void
    {
        $showUrl = route('penugasan.permintaan.show', [$this->assignment, $this->documentRequest]);

        $this->actingAsVerified($this->auditor)->get($showUrl)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('documentRequest.can.updateDueDate', true)
            ->where('documentRequest.due_date_changed_at', null)
            ->where('today', '2027-04-07')
            ->where('holidays.2027-05-17', 'Iduladha 1448 H')
            ->missing('holidays.2027-03-10')
        );

        $this->documentRequest->forceFill(['status' => DocumentRequestStatus::Selesai])->save();
        $this->actingAsVerified($this->auditor)->get($showUrl)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('documentRequest.can.updateDueDate', false)
            ->where('holidays', [])
        );
    }
}
