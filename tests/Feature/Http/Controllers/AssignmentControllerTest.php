<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\RoleSlug;
use App\Enums\ScanStatus;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\FolderTemplate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AssignmentControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    private const array StandardFolderNames = [
        '01 Perencanaan',
        '02 Permintaan Dokumen',
        '03 Kertas Kerja',
        '04 Bukti Unit',
        '05 Temuan dan Tanggapan',
        '06 Laporan',
        '07 Tindak Lanjut',
    ];

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'Audit Keuangan Semester 1',
            'unit_id' => Unit::query()->where('code', 'SMP')->value('id'),
            'start_date' => '2026-02-10',
            'end_date' => '2026-03-31',
            ...$overrides,
        ];
    }

    private function createAssignmentAs(User $user, array $overrides = []): Assignment
    {
        $this->actingAsVerified($user)->post(route('penugasan.store'), $this->validPayload($overrides));

        return Assignment::query()->latest('id')->firstOrFail();
    }

    public function test_dewan_pengawas_creates_assignment_with_code_and_standard_folders(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.store'), $this->validPayload());

        $assignment = Assignment::query()->sole();
        $response->assertRedirect(route('penugasan.show', $assignment));
        $response->assertInertiaFlash('success', 'Penugasan 2026-01-SMP-Audit Keuangan Semester 1 dibuat.');
        $this->assertSame('2026-01-SMP-Audit Keuangan Semester 1', $assignment->code);
        $this->assertSame(2026, $assignment->year);
        $this->assertSame('aktif', $assignment->status->value);
        $this->assertSame($dewanPengawas->id, $assignment->created_by);
        $this->assertSame(self::StandardFolderNames, $assignment->folders()->orderBy('id')->pluck('name')->all());
        $this->assertSame(['internal'], $assignment->folders()->pluck('classification')->map->value->unique()->values()->all());
        $this->assertSame(7, $assignment->folders()->whereNotNull('folder_template_id')->whereNull('parent_id')->count());
        $this->assertAuditLogged(AuditAction::AssignmentCreated, $assignment, ['code' => $assignment->code, 'unit' => 'SMP'], $dewanPengawas);
    }

    public function test_new_assignment_appears_in_the_active_list(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = $this->createAssignmentAs($dewanPengawas);

        $response = $this->actingAsVerified($dewanPengawas)->get(route('penugasan.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Penugasan/Index')
            ->where('tab', 'aktif')
            ->has('assignments', 1)
            ->where('assignments.0.id', $assignment->id)
            ->where('assignments.0.code', '2026-01-SMP-Audit Keuangan Semester 1'));
    }

    public function test_sequence_number_continues_within_a_year_and_restarts_in_a_new_year(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $this->createAssignmentAs($dewanPengawas, ['title' => 'Audit Pertama']);

        $second = $this->createAssignmentAs($dewanPengawas, ['title' => 'Audit Kedua', 'unit_id' => Unit::query()->where('code', 'SD')->value('id')]);
        $nextYear = $this->createAssignmentAs($dewanPengawas, ['title' => 'Audit Tahun Depan', 'start_date' => '2027-01-15', 'end_date' => null]);

        $this->assertSame('2026-02-SD-Audit Kedua', $second->code);
        $this->assertSame('2027-01-SMP-Audit Tahun Depan', $nextYear->code);
    }

    public function test_standard_folders_follow_the_templates_stored_in_the_database(): void
    {
        FolderTemplate::query()->where('sort_order', 7)->update(['name' => '07 Pemantauan Rekomendasi']);
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);

        $assignment = $this->createAssignmentAs($dewanPengawas);

        $this->assertTrue($assignment->folders()->where('name', '07 Pemantauan Rekomendasi')->exists());
        $this->assertFalse($assignment->folders()->where('name', '07 Tindak Lanjut')->exists());
    }

    public function test_team_lead_becomes_a_member_and_the_assignment_is_logged(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);

        $assignment = $this->createAssignmentAs($dewanPengawas, ['team_lead_id' => $auditor->id]);

        $this->assertSame($auditor->id, $assignment->team_lead_id);
        $this->assertDatabaseHas('assignment_members', [
            'assignment_id' => $assignment->id,
            'user_id' => $auditor->id,
            'can_access_confidential' => false,
        ]);
        $this->assertAuditLogged(AuditAction::AuditorAssigned, $assignment, [
            'user_id' => $auditor->id,
            'team_lead' => true,
            'can_access_confidential' => false,
        ]);
    }

    public function test_retries_with_a_fresh_sequence_number_when_another_creation_takes_it_first(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $unit = Unit::query()->where('code', 'SMP')->firstOrFail();
        $insertAttempts = 0;
        Assignment::creating(function (Assignment $assignment) use (&$insertAttempts, $unit, $dewanPengawas): void {
            if (++$insertAttempts === 1) {
                // Pembuatan lain menyimpan nomor urut yang sama tepat sebelum insert ini.
                DB::table('assignments')->insert([
                    'code' => 'BENTROK',
                    'title' => 'Bentrok',
                    'year' => $assignment->year,
                    'sequence_number' => $assignment->sequence_number,
                    'unit_id' => $unit->id,
                    'status' => 'aktif',
                    'start_date' => '2026-02-01',
                    'created_by' => $dewanPengawas->id,
                ]);
            }
        });

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.store'), $this->validPayload());

        $response->assertRedirect();
        $this->assertSame(2, $insertAttempts);
        $this->assertSame(1, Assignment::query()->where('title', 'Audit Keuangan Semester 1')->count());
    }

    public function test_database_rejects_two_assignments_with_the_same_year_and_sequence_number(): void
    {
        Assignment::factory()->create(['year' => 2026, 'sequence_number' => 5]);

        $this->expectException(UniqueConstraintViolationException::class);

        Assignment::factory()->create(['year' => 2026, 'sequence_number' => 5]);
    }

    public function test_failure_while_creating_folders_rolls_back_the_assignment_and_its_log(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        Folder::creating(fn () => throw new RuntimeException('Gagal membuat folder.'));

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.store'), $this->validPayload());

        $response->assertServerError();
        $this->assertDatabaseCount('assignments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_failure_while_writing_the_audit_log_rolls_back_the_assignment(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        AuditLog::creating(fn () => throw new RuntimeException('Gagal menulis log.'));

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.store'), $this->validPayload());

        $response->assertServerError();
        $this->assertDatabaseCount('assignments', 0);
        $this->assertSame(0, Folder::query()->whereNotNull('assignment_id')->count());
    }

    public function test_requires_unit_topic_and_start_date(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.store'), []);

        $response->assertSessionHasErrors([
            'title' => 'Topik penugasan wajib diisi.',
            'unit_id' => 'Pilih unit yang diaudit.',
            'start_date' => 'Tanggal mulai wajib diisi.',
        ]);
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_rejects_end_date_before_start_date(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.store'), $this->validPayload(['end_date' => '2026-02-01']));

        $response->assertSessionHasErrors(['end_date' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
    }

    public function test_rejects_team_lead_who_is_not_an_auditor(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $picUnit = $this->userWithRole(RoleSlug::PicUnit);

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.store'), $this->validPayload(['team_lead_id' => $picUnit->id]));

        $response->assertSessionHasErrors(['team_lead_id' => 'Ketua tim harus Auditor SPI yang aktif.']);
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_forbids_auditor_from_creating_assignments(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);

        $this->actingAsVerified($auditor)->get(route('penugasan.create'))->assertForbidden();
        $response = $this->actingAsVerified($auditor)->post(route('penugasan.store'), $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_create_page_lists_units_and_active_auditors(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        User::factory()->withRole(RoleSlug::AuditorSpi)->inactive()->create();

        $response = $this->actingAsVerified($dewanPengawas)->get(route('penugasan.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Penugasan/Buat')
            ->has('units', 5)
            ->has('auditors', 1)
            ->where('auditors.0.id', $auditor->id));
    }

    public function test_auditor_sees_only_assignments_they_are_assigned_to(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assigned = Assignment::factory()->withAuditor($auditor)->create();
        Assignment::factory()->create();

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('assignments', 1)
            ->where('assignments.0.id', $assigned->id)
            ->where('can.create', false));
    }

    public function test_list_is_ordered_by_year_then_sequence_for_grouping(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $older = Assignment::factory()->create(['year' => 2026, 'sequence_number' => 1]);
        $newest = Assignment::factory()->create(['year' => 2027, 'sequence_number' => 1]);
        $later = Assignment::factory()->create(['year' => 2026, 'sequence_number' => 2]);

        $response = $this->actingAsVerified($dewanPengawas)->get(route('penugasan.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.id', $newest->id)
            ->where('assignments.0.year', 2027)
            ->where('assignments.1.id', $later->id)
            ->where('assignments.2.id', $older->id));
    }

    public function test_archive_tab_lists_only_closed_assignments(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        Assignment::factory()->create();
        $closed = Assignment::factory()->closed()->create();

        $response = $this->actingAsVerified($dewanPengawas)->get(route('penugasan.index', ['tab' => 'arsip']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'arsip')
            ->has('assignments', 1)
            ->where('assignments.0.id', $closed->id));
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_forbids_assignment_pages_for_roles_without_access(RoleSlug $roleSlug): void
    {
        $user = $this->userWithRole($roleSlug);
        $assignment = Assignment::factory()->create();

        $this->actingAsVerified($user)->get(route('penugasan.index'))->assertForbidden();
        $this->actingAsVerified($user)->get(route('penugasan.show', $assignment))->assertForbidden();
    }

    public function test_assigned_auditor_can_open_the_assignment_room(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        Folder::factory()->for($assignment)->standard()->create(['name' => '01 Perencanaan']);

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.show', $assignment));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Penugasan/Show')
            ->where('assignment.code', $assignment->code)
            ->where('folders.0.name', '01 Perencanaan')
            ->where('folders.0.can.rename', false)
            ->where('folders.0.can.createSubfolder', true)
            ->has('members', 1)
            ->where('can.close', false)
            ->where('can.manageMembers', false));
    }

    public function test_direct_url_to_an_assignment_the_auditor_is_not_assigned_to_is_forbidden(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $otherAssignment = Assignment::factory()->create();

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.show', $otherAssignment));

        $response->assertForbidden();
    }

    public function test_confidential_folders_are_left_out_of_the_room_for_auditor_without_access(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        Folder::factory()->for($assignment)->create(['name' => 'Kertas Kerja Umum']);
        $confidential = Folder::factory()->for($assignment)->confidential()->create(['name' => 'Indikasi Fraud']);
        Folder::factory()->childOf($confidential)->create(['name' => 'Bukti Fraud']);

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.show', $assignment));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('folders', 1)
            ->where('folders.0.name', 'Kertas Kerja Umum'));
    }

    public function test_confidential_folders_are_shown_to_auditor_given_access(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor, canAccessConfidential: true)->create();
        Folder::factory()->for($assignment)->confidential()->create(['name' => 'Indikasi Fraud']);

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.show', $assignment));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('folders', 1)
            ->where('folders.0.name', 'Indikasi Fraud')
            ->where('folders.0.classification', 'rahasia'));
    }

    public function test_room_lists_the_files_of_the_selected_folder(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        Folder::factory()->for($assignment)->standard()->create(['name' => '01 Perencanaan']);
        $workingPapers = Folder::factory()->for($assignment)->standard()->create(['name' => '03 Kertas Kerja']);
        File::factory()->for($workingPapers)->withVersion(ScanStatus::Menunggu)->create(['original_name' => 'KKA Kas.xlsx', 'created_by' => $auditor->id]);

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.show', ['assignment' => $assignment, 'folder' => $workingPapers->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('selectedFolder.id', $workingPapers->id)
            ->where('selectedFolder.can.upload', true)
            ->has('selectedFolder.files', 1)
            ->where('selectedFolder.files.0.name', 'KKA Kas.xlsx')
            ->where('selectedFolder.files.0.version_number', 1)
            ->where('selectedFolder.files.0.classification', 'internal')
            ->where('selectedFolder.files.0.scan_status', 'menunggu')
            ->where('selectedFolder.files.0.uploader', $auditor->name)
            ->where('uploadRules.allowed_extensions', config('berkas.allowed_extensions')));
    }

    public function test_room_selects_the_first_top_level_folder_by_default(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $planning = Folder::factory()->for($assignment)->standard()->create(['name' => '01 Perencanaan']);
        Folder::factory()->for($assignment)->standard()->create(['name' => '02 Permintaan Dokumen']);

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.show', $assignment));

        $response->assertInertia(fn (Assert $page) => $page->where('selectedFolder.id', $planning->id));
    }

    public function test_room_does_not_select_a_confidential_folder_or_list_rahasia_files_for_auditor_without_access(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $folder = Folder::factory()->for($assignment)->create(['name' => 'Kertas Kerja']);
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create(['name' => 'Indikasi Fraud']);
        File::factory()->for($folder)->withVersion()->create(['original_name' => 'Umum.pdf']);
        File::factory()->for($folder)->confidential()->withVersion()->create(['original_name' => 'Rahasia.pdf']);

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.show', ['assignment' => $assignment, 'folder' => $confidentialFolder->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('selectedFolder.id', $folder->id)
            ->has('selectedFolder.files', 1)
            ->where('selectedFolder.files.0.name', 'Umum.pdf'));
    }

    public function test_room_of_a_closed_assignment_lists_files_without_upload(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->closed()->create();
        $folder = Folder::factory()->for($assignment)->locked()->create();
        File::factory()->for($folder)->withVersion()->create(['original_name' => 'Laporan Final.pdf']);

        $response = $this->actingAsVerified($dewanPengawas)->get(route('penugasan.show', $assignment));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('selectedFolder.can.upload', false)
            ->where('selectedFolder.is_locked', true)
            ->where('selectedFolder.files.0.name', 'Laporan Final.pdf'));
    }
}
