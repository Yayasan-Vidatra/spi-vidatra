<?php

namespace Tests\Feature\Http\Controllers;

use App\Actions\ReviewRequestItem;
use App\Enums\AuditAction;
use App\Enums\DocumentRequestStatus;
use App\Enums\EscalationStatus;
use App\Enums\RequestItemStatus;
use App\Enums\RoleSlug;
use App\Enums\ScanStatus;
use App\Jobs\CopyAcceptedRequestItem;
use App\Jobs\ScanFileVersion;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\Escalation;
use App\Models\FileVersion;
use App\Models\RequestItem;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\RequestItemReviewed;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class RequestItemReviewControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    private User $auditor;

    private Assignment $assignment;

    private DocumentRequest $documentRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeFileDisk();
        Queue::fake([CopyAcceptedRequestItem::class, ScanFileVersion::class]);
        Notification::fake();

        $this->auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $this->assignment = Assignment::factory()->withAuditor($this->auditor)->create();
        $this->documentRequest = DocumentRequest::factory()->for($this->assignment)->create([
            'unit_id' => $this->unit('SMP')->id,
        ]);
    }

    private function unit(string $code): Unit
    {
        return Unit::query()->where('code', $code)->sole();
    }

    private function uploadedSlot(ScanStatus $scanStatus = ScanStatus::Bersih, string $name = 'Laporan kas Oktober', int $sortOrder = 1): RequestItem
    {
        $file = $this->storedFile($this->documentRequest->folder, User::factory()->picOf($this->documentRequest->unit)->create(), $scanStatus);

        return RequestItem::factory()->for($this->documentRequest)->withStatus(RequestItemStatus::Diunggah)->create([
            'name' => $name,
            'sort_order' => $sortOrder,
            'file_id' => $file->id,
            'submitted_at' => now(),
        ]);
    }

    private function reviewUrl(RequestItem $item): string
    {
        return route('penugasan.permintaan.slot.periksa', [$this->assignment, $item->documentRequest, $item]);
    }

    /**
     * @return array{result: string, note: string, version_id: int}
     */
    private function payload(RequestItem $item, RequestItemStatus $result = RequestItemStatus::Diterima, string $note = 'Lengkap dan bertanda tangan.'): array
    {
        return ['result' => $result->value, 'note' => $note, 'version_id' => $item->file->current_version_id];
    }

    public function test_auditor_accepts_a_slot_which_locks_it_queues_the_copy_and_notifies_the_unit(): void
    {
        $this->freezeSecond();
        $item = $this->uploadedSlot();
        $pics = User::factory()->picOf($this->unit('SMP'))->count(2)->create();
        $otherUnitPic = User::factory()->picOf($this->unit('SD'))->create();
        $versionId = $item->file->current_version_id;

        $response = $this->actingAsVerified($this->auditor)->post($this->reviewUrl($item), $this->payload($item));

        $response->assertRedirect(route('penugasan.permintaan.show', [$this->assignment, $this->documentRequest]));
        $response->assertInertiaFlash('success', '"Laporan kas Oktober" diterima dan sedang disalin ke 04 Bukti Unit.');
        $item->refresh();
        $this->assertSame(RequestItemStatus::Diterima, $item->status);
        $this->assertSame('Lengkap dan bertanda tangan.', $item->reviewer_note);
        $this->assertSame($this->auditor->id, $item->reviewed_by);
        $this->assertEquals(now(), $item->reviewed_at);
        $this->assertSame($versionId, $item->accepted_version_id);
        $this->assertEquals(now(), $item->evidence_copy_pending_since);
        $this->assertTrue($item->isEvidenceCopyPending());
        Queue::assertPushed(CopyAcceptedRequestItem::class, fn (CopyAcceptedRequestItem $job): bool => $job->item->is($item));
        $this->assertAuditLogged(AuditAction::RequestItemReviewed, $item, [
            'document_request_id' => $this->documentRequest->id,
            'unit' => 'SMP',
            'name' => 'Laporan kas Oktober',
            'result' => 'diterima',
            'note' => 'Lengkap dan bertanda tangan.',
            'file_id' => $item->file_id,
            'version' => 1,
            'version_id' => $versionId,
        ], $this->auditor);
        $this->assertFalse($pics->first()->can('upload', $item));
        Notification::assertSentTo($pics, RequestItemReviewed::class, fn (RequestItemReviewed $notification): bool => $notification->item->is($item));
        Notification::assertNotSentTo($otherUnitPic, RequestItemReviewed::class);
        $this->assertSame('SPI menerima "Laporan kas Oktober".', (new RequestItemReviewed($item))->toArray($pics->first())['message']);
    }

    public function test_perlu_perbaikan_returns_the_slot_to_the_unit_without_a_copy(): void
    {
        $item = $this->uploadedSlot();
        $pic = User::factory()->picOf($this->unit('SMP'))->create();

        $response = $this->actingAsVerified($this->auditor)->post(
            $this->reviewUrl($item),
            $this->payload($item, RequestItemStatus::PerluPerbaikan, 'Kurang tanda tangan kepala sekolah.'),
        );

        $response->assertInertiaFlash('success', '"Laporan kas Oktober" dikembalikan ke unit untuk diperbaiki.');
        $item->refresh();
        $this->assertSame(RequestItemStatus::PerluPerbaikan, $item->status);
        $this->assertSame('Kurang tanda tangan kepala sekolah.', $item->reviewer_note);
        $this->assertNull($item->accepted_version_id);
        $this->assertNull($item->evidence_copy_pending_since);
        $this->assertSame('perlu_perbaikan', $this->assertAuditLogged(AuditAction::RequestItemReviewed, $item)->properties['result']);
        Queue::assertNotPushed(CopyAcceptedRequestItem::class);
        $this->assertTrue($pic->can('upload', $item));
        Notification::assertSentTo($pic, RequestItemReviewed::class);
        $this->assertStringContainsString('meminta perbaikan untuk "Laporan kas Oktober"', (new RequestItemReviewed($item))->toArray($pic)['message']);
    }

    public function test_accepting_an_escalated_slot_resolves_its_escalation(): void
    {
        $this->freezeSecond();
        $item = $this->uploadedSlot();
        $escalation = Escalation::factory()->create(['subject_id' => $item->id, 'escalated_at' => now()->subDays(2)]);

        $this->actingAsVerified($this->auditor)->post($this->reviewUrl($item), $this->payload($item))->assertSessionHasNoErrors();

        $escalation->refresh();
        $this->assertSame(EscalationStatus::Selesai, $escalation->status);
        $this->assertEquals(now(), $escalation->resolved_at);
        $this->assertAuditLogged(AuditAction::RequestItemEscalationResolved, $item, [
            'escalation_id' => $escalation->id,
            'document_request_id' => $this->documentRequest->id,
            'name' => 'Laporan kas Oktober',
            'escalated_at' => now()->subDays(2)->toIso8601String(),
        ], $this->auditor);
    }

    public function test_perlu_perbaikan_keeps_the_escalation_forwarded(): void
    {
        $item = $this->uploadedSlot();
        $escalation = Escalation::factory()->create(['subject_id' => $item->id]);

        $this->actingAsVerified($this->auditor)
            ->post($this->reviewUrl($item), $this->payload($item, RequestItemStatus::PerluPerbaikan, 'Kurang tanda tangan.'))
            ->assertSessionHasNoErrors();

        $this->assertSame(EscalationStatus::Diteruskan, $escalation->fresh()->status);
        $this->assertNull($escalation->fresh()->resolved_at);
    }

    public function test_dewan_pengawas_can_review(): void
    {
        $item = $this->uploadedSlot();

        $this->actingAsVerified($this->userWithRole(RoleSlug::DewanPengawas))
            ->post($this->reviewUrl($item), $this->payload($item))
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestItemStatus::Diterima, $item->fresh()->status);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidReviews(): array
    {
        return [
            'tanpa catatan saat diterima' => [['result' => 'diterima', 'note' => ''], 'note', 'Catatan wajib diisi.'],
            'tanpa catatan saat perlu perbaikan' => [['result' => 'perlu_perbaikan', 'note' => '   '], 'note', 'Catatan wajib diisi.'],
            'catatan terlalu panjang' => [['result' => 'diterima', 'note' => str_repeat('a', 1001)], 'note', 'Catatan paling panjang 1000 karakter.'],
            'tanpa hasil' => [['result' => '', 'note' => 'Oke'], 'result', 'Pilih hasil periksa: Diterima atau Perlu Perbaikan.'],
            'hasil lain' => [['result' => 'menunggu', 'note' => 'Oke'], 'result', 'Hasil periksa harus Diterima atau Perlu Perbaikan.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidReviews')]
    public function test_review_needs_a_result_and_a_note(array $payload, string $field, string $message): void
    {
        $item = $this->uploadedSlot();

        $response = $this->actingAsVerified($this->auditor)->post($this->reviewUrl($item), [...$payload, 'version_id' => $item->file->current_version_id]);

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertSame(RequestItemStatus::Diunggah, $item->fresh()->status);
    }

    public function test_slot_waiting_for_the_scan_cannot_be_reviewed(): void
    {
        $item = $this->uploadedSlot(ScanStatus::Menunggu);

        $response = $this->actingAsVerified($this->auditor)->post($this->reviewUrl($item), $this->payload($item));

        $response->assertSessionHasErrors(['result' => ReviewRequestItem::WaitingForScanMessage]);
        $this->assertSame(RequestItemStatus::Diunggah, $item->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => AuditAction::RequestItemReviewed->value]);
        Queue::assertNotPushed(CopyAcceptedRequestItem::class);
        Notification::assertNothingSent();
    }

    public function test_review_of_an_older_version_is_refused_after_the_pic_uploads_a_newer_one(): void
    {
        $item = $this->uploadedSlot();
        $seenVersionId = $item->file->current_version_id;
        $newerVersion = FileVersion::factory()->for($item->file)->create(['version_number' => 2]);
        $item->file->forceFill(['current_version_id' => $newerVersion->id])->save();

        $response = $this->actingAsVerified($this->auditor)->post($this->reviewUrl($item), [
            'result' => 'diterima', 'note' => 'Lengkap.', 'version_id' => $seenVersionId,
        ]);

        $response->assertSessionHasErrors(['result' => ReviewRequestItem::NewerVersionMessage]);
        $this->assertSame(RequestItemStatus::Diunggah, $item->fresh()->status);
    }

    public function test_accepted_slot_is_final_and_cannot_be_reviewed_again(): void
    {
        $item = $this->uploadedSlot();
        $this->actingAsVerified($this->auditor)->post($this->reviewUrl($item), $this->payload($item));

        $this->actingAsVerified($this->userWithRole(RoleSlug::DewanPengawas))
            ->post($this->reviewUrl($item), $this->payload($item, RequestItemStatus::PerluPerbaikan, 'Salah terima.'))
            ->assertForbidden();

        $this->assertSame(RequestItemStatus::Diterima, $item->fresh()->status);
    }

    public function test_request_becomes_selesai_when_its_last_slot_is_accepted(): void
    {
        $first = $this->uploadedSlot();
        $second = $this->uploadedSlot(name: 'SK pembagian tugas', sortOrder: 2);

        $this->actingAsVerified($this->auditor)->post($this->reviewUrl($first), $this->payload($first));
        $this->assertSame(DocumentRequestStatus::Terbuka, $this->documentRequest->fresh()->status);

        $this->actingAsVerified($this->auditor)->post($this->reviewUrl($second), $this->payload($second));

        $this->assertSame(DocumentRequestStatus::Selesai, $this->documentRequest->fresh()->status);
        $this->assertAuditLogged(AuditAction::DocumentRequestCompleted, $this->documentRequest, [
            'assignment_id' => $this->assignment->id,
            'unit_id' => $this->unit('SMP')->id,
            'item_count' => 2,
        ], $this->auditor);
    }

    public function test_request_stays_terbuka_while_a_slot_needs_fixing(): void
    {
        $first = $this->uploadedSlot();
        $second = $this->uploadedSlot(name: 'SK pembagian tugas', sortOrder: 2);

        $this->actingAsVerified($this->auditor)->post($this->reviewUrl($first), $this->payload($first, RequestItemStatus::PerluPerbaikan, 'Buram.'));
        $this->actingAsVerified($this->auditor)->post($this->reviewUrl($second), $this->payload($second));

        $this->assertSame(DocumentRequestStatus::Terbuka, $this->documentRequest->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => AuditAction::DocumentRequestCompleted->value]);
    }

    public function test_auditor_not_assigned_to_the_assignment_cannot_review(): void
    {
        $item = $this->uploadedSlot();

        $this->actingAsVerified($this->userWithRole(RoleSlug::AuditorSpi))->post($this->reviewUrl($item), $this->payload($item))->assertForbidden();

        $this->assertSame(RequestItemStatus::Diunggah, $item->fresh()->status);
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_roles_outside_spi_cannot_review_through_a_direct_url(RoleSlug $roleSlug): void
    {
        $item = $this->uploadedSlot();
        $user = $roleSlug === RoleSlug::PicUnit
            ? User::factory()->picOf($this->unit('SMP'))->withTwoFactor()->acceptedConfidentiality()->create()
            : $this->userWithRole($roleSlug);

        $this->actingAsVerified($user)->post($this->reviewUrl($item), $this->payload($item))->assertForbidden();

        $this->assertSame(RequestItemStatus::Diunggah, $item->fresh()->status);
    }

    public function test_closed_assignment_cannot_be_reviewed(): void
    {
        $item = $this->uploadedSlot();
        $this->assignment->forceFill(['status' => 'ditutup', 'closed_at' => now()])->save();

        $this->actingAsVerified($this->auditor)->post($this->reviewUrl($item), $this->payload($item))->assertForbidden();
    }

    public function test_slot_of_another_request_or_assignment_is_not_found(): void
    {
        $item = $this->uploadedSlot();
        $otherRequest = DocumentRequest::factory()->for($this->assignment)->create();
        $otherAssignment = Assignment::factory()->withAuditor($this->auditor)->create();

        $this->actingAsVerified($this->auditor)
            ->post(route('penugasan.permintaan.slot.periksa', [$this->assignment, $otherRequest, $item]), $this->payload($item))
            ->assertNotFound();
        $this->actingAsVerified($this->auditor)
            ->post(route('penugasan.permintaan.slot.periksa', [$otherAssignment, $this->documentRequest, $item]), $this->payload($item))
            ->assertNotFound();
    }

    public function test_detail_page_offers_the_review_form_only_for_reviewable_slots(): void
    {
        $clean = $this->uploadedSlot();
        $pending = $this->uploadedSlot(ScanStatus::Menunggu, 'Rekap gaji', 2);
        $accepted = $this->uploadedSlot(name: 'SK pembagian tugas', sortOrder: 3);
        $accepted->forceFill([
            'status' => RequestItemStatus::Diterima,
            'reviewer_note' => 'Lengkap.',
            'reviewed_by' => $this->auditor->id,
            'reviewed_at' => now(),
            'accepted_version_id' => $accepted->file->current_version_id,
        ])->save();

        $response = $this->actingAsVerified($this->auditor)->get(route('penugasan.permintaan.show', [$this->assignment, $this->documentRequest]));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Permintaan/Show')
            ->where('maxNoteLength', 1000)
            ->where('items.0.can_review', true)
            ->where('items.0.review_waits_for_scan', false)
            ->where('items.0.version_id', $clean->file->current_version_id)
            ->where('items.1.can_review', true)
            ->where('items.1.review_waits_for_scan', true)
            ->where('items.2.can_review', false)
            ->where('items.2.reviewer', $this->auditor->name)
            ->where('items.2.evidence_copy_pending', true)
            ->where('items.2.evidence_file', null)
        );
    }

    public function test_pic_cannot_open_the_spi_detail_page(): void
    {
        $pic = User::factory()->picOf($this->unit('SMP'))->withTwoFactor()->acceptedConfidentiality()->create();

        $this->actingAsVerified($pic)->get(route('penugasan.permintaan.show', [$this->assignment, $this->documentRequest]))->assertForbidden();
    }
}
