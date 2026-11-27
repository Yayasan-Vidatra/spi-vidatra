<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Enums\DocumentRequestStatus;
use App\Enums\FolderPurpose;
use App\Enums\RequestItemStatus;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\Folder;
use App\Models\RequestItem;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\DocumentRequestReceived;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class DocumentRequestControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    /**
     * @return array{User, Assignment, Folder}
     */
    private function auditorWithAssignment(Classification $requestsFolderLabel = Classification::Internal): array
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create(['unit_id' => $this->unit('SMP')->id]);
        $requestsFolder = Folder::factory()->for($assignment)->forPurpose(FolderPurpose::PermintaanDokumen)
            ->create(['classification' => $requestsFolderLabel]);

        return [$auditor, $assignment, $requestsFolder];
    }

    private function unit(string $code): Unit
    {
        return Unit::query()->where('code', $code)->sole();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'unit_id' => $this->unit('SMP')->id,
            'due_date' => now()->addWeek()->toDateString(),
            'items' => [
                ['name' => 'Laporan kas Oktober', 'description' => 'Ditandatangani bendahara', 'formats' => ['pdf', 'excel']],
                ['name' => 'Foto kegiatan', 'description' => null, 'formats' => ['gambar']],
            ],
            ...$overrides,
        ];
    }

    public function test_assigned_auditor_creates_a_request_with_a_slot_per_document_in_the_requests_folder(): void
    {
        Notification::fake();
        [$auditor, $assignment, $requestsFolder] = $this->auditorWithAssignment();

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.permintaan.store', $assignment), $this->payload([
            'due_date' => '2026-12-10',
        ]));

        $documentRequest = DocumentRequest::query()->sole();
        $response->assertRedirect(route('penugasan.permintaan.show', [$assignment, $documentRequest]));
        $response->assertInertiaFlash('success', 'Permintaan dokumen ke SMP YPVDP dibuat.');
        $this->assertSame(1, $documentRequest->sequence_number);
        $this->assertSame($this->unit('SMP')->id, $documentRequest->unit_id);
        $this->assertSame(DocumentRequestStatus::Terbuka, $documentRequest->status);
        $this->assertSame('2026-12-10', $documentRequest->due_date->toDateString());
        $this->assertSame($auditor->id, $documentRequest->created_by);

        $folder = $documentRequest->folder;
        $this->assertSame('Permintaan 01 - SMP', $folder->name);
        $this->assertSame($requestsFolder->id, $folder->parent_id);
        $this->assertSame($assignment->id, $folder->assignment_id);

        $items = $documentRequest->items;
        $this->assertSame(['Laporan kas Oktober', 'Foto kegiatan'], $items->pluck('name')->all());
        $this->assertSame('Ditandatangani bendahara', $items[0]->description);
        $this->assertSame(['pdf', 'xlsx'], $items[0]->accepted_extensions);
        $this->assertSame(['jpg', 'jpeg', 'png'], $items[1]->accepted_extensions);
        $this->assertTrue($items->every(fn (RequestItem $item): bool => $item->status === RequestItemStatus::Menunggu && $item->file_id === null));

        $this->assertAuditLogged(AuditAction::DocumentRequestCreated, $documentRequest, [
            'assignment_id' => $assignment->id,
            'assignment_code' => $assignment->code,
            'unit_id' => $this->unit('SMP')->id,
            'unit' => 'SMP',
            'due_date' => '2026-12-10',
            'folder_id' => $folder->id,
            'items' => ['Laporan kas Oktober', 'Foto kegiatan'],
        ], $auditor);
    }

    public function test_subfolder_inherits_the_rahasia_label_of_the_requests_folder(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        [, $assignment] = $this->auditorWithAssignment(Classification::Rahasia);

        $this->actingAsVerified($dewanPengawas)->post(route('penugasan.permintaan.store', $assignment), $this->payload());

        $folder = DocumentRequest::query()->sole()->folder;
        $this->assertSame(Classification::Rahasia, $folder->classification);
    }

    public function test_request_can_go_to_a_unit_other_than_the_assignment_unit_and_numbers_increase_per_assignment(): void
    {
        [$auditor, $assignment] = $this->auditorWithAssignment();

        $this->actingAsVerified($auditor)->post(route('penugasan.permintaan.store', $assignment), $this->payload());
        $this->actingAsVerified($auditor)->post(route('penugasan.permintaan.store', $assignment), $this->payload([
            'unit_id' => $this->unit('PH')->id,
        ]));

        $second = DocumentRequest::query()->where('unit_id', $this->unit('PH')->id)->sole();
        $this->assertSame(2, $second->sequence_number);
        $this->assertSame('Permintaan 02 - PH', $second->folder->name);
        $this->assertSame('PH', $this->assertAuditLogged(AuditAction::DocumentRequestCreated, $second)->properties['unit']);
    }

    public function test_every_active_pic_of_the_target_unit_is_notified_and_no_one_else(): void
    {
        Notification::fake();
        [$auditor, $assignment] = $this->auditorWithAssignment();
        $staff = User::factory()->picOf($this->unit('SMP'))->create();
        $principal = User::factory()->picOf($this->unit('SMP'))->create();
        $inactivePic = User::factory()->picOf($this->unit('SMP'))->inactive()->create();
        $otherUnitPic = User::factory()->picOf($this->unit('SD'))->create();

        $this->actingAsVerified($auditor)->post(route('penugasan.permintaan.store', $assignment), $this->payload([
            'due_date' => '2026-12-10',
        ]));

        $documentRequest = DocumentRequest::query()->sole();
        Notification::assertSentTo([$staff, $principal], DocumentRequestReceived::class, ['documentRequest' => $documentRequest]);
        Notification::assertNotSentTo([$inactivePic, $otherUnitPic, $auditor], DocumentRequestReceived::class);
        $this->assertSame(
            ['document_request_id' => $documentRequest->id, 'message' => 'Permintaan dokumen baru dari SPI: 2 dokumen, tenggat 10 Desember 2026.'],
            (new DocumentRequestReceived($documentRequest))->toArray($staff),
        );
    }

    public function test_notification_is_stored_in_the_database_for_the_dashboard(): void
    {
        [$auditor, $assignment] = $this->auditorWithAssignment();
        $pic = User::factory()->picOf($this->unit('SMP'))->create();

        $this->actingAsVerified($auditor)->post(route('penugasan.permintaan.store', $assignment), $this->payload());

        $notification = $pic->notifications()->sole();
        $this->assertSame(DocumentRequestReceived::class, $notification->type);
        $this->assertSame(DocumentRequest::query()->sole()->id, $notification->data['document_request_id']);
    }

    public function test_empty_payload_is_rejected_with_messages_for_every_required_field(): void
    {
        [$auditor, $assignment] = $this->auditorWithAssignment();

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.permintaan.store', $assignment), []);

        $response->assertSessionHasErrors([
            'unit_id' => 'Pilih unit tujuan.',
            'due_date' => 'Tenggat wajib diisi.',
            'items' => 'Isi minimal satu dokumen yang diminta.',
        ]);
        $this->assertDatabaseCount('document_requests', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidItems(): array
    {
        return [
            'document without a name' => [['name' => '', 'formats' => ['pdf']], 'items.1.name', 'Nama dokumen ke-2 wajib diisi.'],
            'name with a character not allowed in file names' => [['name' => 'Kas/Oktober', 'formats' => ['pdf']], 'items.1.name', 'Nama dokumen ke-2 tidak boleh memuat karakter / \\ : * ? " < > |.'],
            'name used by another document, ignoring case' => [['name' => 'LAPORAN KAS OKTOBER', 'formats' => ['pdf']], 'items.1.name', 'Nama dokumen ke-2 sama dengan dokumen lain di permintaan ini.'],
            'document without a format' => [['name' => 'Rekap', 'formats' => []], 'items.1.formats', 'Pilih minimal satu format untuk dokumen ke-2.'],
            'unknown format group' => [['name' => 'Rekap', 'formats' => ['exe']], 'items.1.formats.0', 'Format dokumen tidak dikenali.'],
            'format chosen twice' => [['name' => 'Rekap', 'formats' => ['pdf', 'pdf']], 'items.1.formats.1', 'Format yang sama dipilih lebih dari sekali untuk dokumen ke-2.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $secondItem
     */
    #[DataProvider('invalidItems')]
    public function test_invalid_documents_are_rejected(array $secondItem, string $field, string $message): void
    {
        [$auditor, $assignment] = $this->auditorWithAssignment();

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.permintaan.store', $assignment), $this->payload([
            'items' => [
                ['name' => 'Laporan kas Oktober', 'formats' => ['pdf']],
                $secondItem,
            ],
        ]));

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_format_group_with_an_extension_that_is_not_an_allowed_upload_is_rejected_on_the_server(): void
    {
        config(['berkas.allowed_extensions' => ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png']]);
        [$auditor, $assignment] = $this->auditorWithAssignment();

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.permintaan.store', $assignment), $this->payload([
            'items' => [['name' => 'Arsip', 'formats' => ['zip']]],
        ]));

        $response->assertSessionHasErrors(['items.0.formats.0' => 'Format dokumen tidak dikenali.']);
    }

    public function test_deadline_cannot_be_before_today_in_wita(): void
    {
        // 23.30 UTC tanggal 9 = 07.30 WITA tanggal 10, sehingga tanggal 9 sudah lewat di WITA.
        $this->travelTo(now()->setTimezone('UTC')->setDateTime(2026, 12, 9, 23, 30));
        [$auditor, $assignment] = $this->auditorWithAssignment();

        $this->actingAsVerified($auditor)
            ->post(route('penugasan.permintaan.store', $assignment), $this->payload(['due_date' => '2026-12-09']))
            ->assertSessionHasErrors(['due_date' => 'Tenggat tidak boleh sebelum hari ini.']);

        $this->actingAsVerified($auditor)
            ->post(route('penugasan.permintaan.store', $assignment), $this->payload(['due_date' => '2026-12-10']))
            ->assertSessionHasNoErrors();
    }

    public function test_auditor_not_assigned_to_the_assignment_cannot_open_or_submit_the_form(): void
    {
        [, $assignment] = $this->auditorWithAssignment();
        $otherAuditor = $this->userWithRole(RoleSlug::AuditorSpi);

        $this->actingAsVerified($otherAuditor)->get(route('penugasan.permintaan.create', $assignment))->assertForbidden();
        $this->actingAsVerified($otherAuditor)->post(route('penugasan.permintaan.store', $assignment), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_pic_unit_cannot_create_a_request_through_a_direct_url(): void
    {
        [, $assignment] = $this->auditorWithAssignment();
        $pic = User::factory()->picOf($this->unit('SMP'))->withTwoFactor()->acceptedConfidentiality()->create();

        $this->actingAsVerified($pic)->post(route('penugasan.permintaan.store', $assignment), $this->payload())->assertForbidden();
        $this->actingAsVerified($pic)->get(route('penugasan.permintaan.index', $assignment))->assertForbidden();
        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_closed_assignment_rejects_new_requests(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        [, $assignment] = $this->auditorWithAssignment();
        $assignment->forceFill(['status' => 'ditutup', 'closed_at' => now()])->save();

        $this->actingAsVerified($dewanPengawas)->post(route('penugasan.permintaan.store', $assignment), $this->payload())->assertForbidden();
    }

    public function test_create_page_offers_the_assignment_unit_and_allowed_format_groups(): void
    {
        [$auditor, $assignment] = $this->auditorWithAssignment();
        User::factory()->picOf($this->unit('SMP'))->create();

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.permintaan.create', $assignment));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Permintaan/Buat')
            ->where('defaultUnitId', $this->unit('SMP')->id)
            ->where('units.1.code', 'SMP')
            ->where('units.1.pic_count', 1)
            ->where('units.0.pic_count', 0)
            ->where('formatGroups', fn ($groups): bool => collect($groups)->pluck('key')->all() === ['pdf', 'word', 'excel', 'powerpoint', 'gambar', 'zip'])
        );
    }

    public function test_index_and_detail_show_slot_status_and_mark_overdue_slots(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Makassar')->setDateTime(2026, 12, 11, 8, 0));
        [$auditor, $assignment] = $this->auditorWithAssignment();
        $documentRequest = DocumentRequest::factory()->for($assignment)->create(['due_date' => '2026-12-10']);
        RequestItem::factory()->for($documentRequest)->create(['name' => 'Laporan kas', 'sort_order' => 1, 'accepted_extensions' => ['pdf', 'xlsx']]);
        RequestItem::factory()->for($documentRequest)->withStatus(RequestItemStatus::Diterima)->create(['name' => 'Notulen', 'sort_order' => 2]);

        $this->actingAsVerified($auditor)->get(route('penugasan.permintaan.index', $assignment))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Permintaan/Index')
                ->where('documentRequests.0.item_count', 2)
                ->where('documentRequests.0.accepted_count', 1)
                ->where('documentRequests.0.overdue_count', 1)
                ->where('can.create', true)
            );

        $this->actingAsVerified($auditor)->get(route('penugasan.permintaan.show', [$assignment, $documentRequest]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Permintaan/Show')
                ->where('documentRequest.is_past_due', true)
                ->where('items.0.name', 'Laporan kas')
                ->where('items.0.formats', ['PDF', 'Excel'])
                ->where('items.0.is_overdue', true)
                ->where('items.1.status', 'diterima')
                ->where('items.1.is_overdue', false)
            );
    }

    public function test_request_of_another_assignment_is_not_found_through_this_assignment(): void
    {
        [$auditor, $assignment] = $this->auditorWithAssignment();
        $otherRequest = DocumentRequest::factory()->create();

        $this->actingAsVerified($auditor)->get(route('penugasan.permintaan.show', [$assignment, $otherRequest]))->assertNotFound();
    }

    public function test_auditor_cannot_view_requests_of_an_assignment_they_are_not_assigned_to(): void
    {
        $documentRequest = DocumentRequest::factory()->create();
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);

        $this->actingAsVerified($auditor)->get(route('penugasan.permintaan.show', [$documentRequest->assignment, $documentRequest]))->assertForbidden();
    }

    public function test_files_cannot_be_uploaded_into_a_request_folder_through_h05(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $documentRequest = DocumentRequest::factory()->create();

        $this->actingAsVerified($dewanPengawas)
            ->post(route('folder.berkas.store', $documentRequest->folder), ['files' => []])
            ->assertForbidden();
    }
}
