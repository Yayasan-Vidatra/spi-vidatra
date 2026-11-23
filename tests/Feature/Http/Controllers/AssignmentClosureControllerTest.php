<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Folder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AssignmentClosureControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    public function test_dewan_pengawas_closes_assignment_locking_every_folder(): void
    {
        $this->freezeTime();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $standardFolder = Folder::factory()->for($assignment)->standard()->create();
        $subfolder = Folder::factory()->childOf($standardFolder)->create();

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.penutupan.store', $assignment));

        $response->assertRedirect(route('penugasan.show', $assignment));
        $assignment->refresh();
        $this->assertSame('ditutup', $assignment->status->value);
        $this->assertSame(now()->toDateTimeString(), $assignment->closed_at->toDateTimeString());
        $this->assertSame($dewanPengawas->id, $assignment->closed_by);
        $this->assertTrue($standardFolder->fresh()->is_locked);
        $this->assertTrue($subfolder->fresh()->is_locked);
        $this->assertAuditLogged(AuditAction::AssignmentClosed, $assignment, ['code' => $assignment->code], $dewanPengawas);
    }

    public function test_closed_assignment_moves_to_the_archive_tab(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $this->actingAsVerified($dewanPengawas)->post(route('penugasan.penutupan.store', $assignment));

        $activeTab = $this->actingAsVerified($dewanPengawas)->get(route('penugasan.index'));
        $archiveTab = $this->actingAsVerified($dewanPengawas)->get(route('penugasan.index', ['tab' => 'arsip']));

        $activeTab->assertInertia(fn (Assert $page) => $page->has('assignments', 0));
        $archiveTab->assertInertia(fn (Assert $page) => $page->has('assignments', 1)->where('assignments.0.id', $assignment->id));
    }

    public function test_closed_assignment_stays_readable_for_its_auditor(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->closed()->withAuditor($auditor)->create();
        Folder::factory()->for($assignment)->locked()->create();

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.show', $assignment));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('assignment.status', 'ditutup')
            ->where('folders.0.can.createSubfolder', false)
            ->where('folders.0.can.changeLabel', false));
    }

    public function test_forbids_auditor_from_closing_an_assignment(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.penutupan.store', $assignment));

        $response->assertForbidden();
        $this->assertSame('aktif', $assignment->fresh()->status->value);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_only_dewan_pengawas_can_reopen_and_reopening_unlocks_folders(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->closed()->create();
        $folder = Folder::factory()->for($assignment)->locked()->create();

        $response = $this->actingAsVerified($dewanPengawas)->delete(route('penugasan.penutupan.destroy', $assignment));

        $response->assertRedirect(route('penugasan.show', $assignment));
        $assignment->refresh();
        $this->assertSame('aktif', $assignment->status->value);
        $this->assertNull($assignment->closed_at);
        $this->assertNull($assignment->closed_by);
        $this->assertFalse($folder->fresh()->is_locked);
        $this->assertAuditLogged(AuditAction::AssignmentReopened, $assignment, ['code' => $assignment->code], $dewanPengawas);
    }

    public function test_forbids_auditor_from_reopening_an_assignment(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->closed()->withAuditor($auditor)->create();

        $response = $this->actingAsVerified($auditor)->delete(route('penugasan.penutupan.destroy', $assignment));

        $response->assertForbidden();
        $this->assertSame('ditutup', $assignment->fresh()->status->value);
    }

    public function test_closing_an_already_closed_assignment_is_forbidden(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->closed()->create();

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.penutupan.store', $assignment));

        $response->assertForbidden();
    }

    public function test_closed_assignment_rejects_folder_and_member_changes(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->closed()->withAuditor($auditor)->create();
        $folder = Folder::factory()->for($assignment)->locked()->create(['name' => 'Kertas Kerja']);
        $member = $assignment->members()->sole();

        $this->actingAsVerified($dewanPengawas)
            ->post(route('penugasan.folder.store', $assignment), ['parent_id' => $folder->id, 'name' => 'Baru'])
            ->assertForbidden();
        $this->actingAsVerified($dewanPengawas)
            ->patch(route('penugasan.folder.update', [$assignment, $folder]), ['name' => 'Diubah'])
            ->assertForbidden();
        $this->actingAsVerified($dewanPengawas)
            ->post(route('penugasan.folder.arsip.store', [$assignment, $folder]))
            ->assertForbidden();
        $this->actingAsVerified($dewanPengawas)
            ->delete(route('penugasan.auditor.destroy', [$assignment, $member]))
            ->assertForbidden();

        $this->assertSame('Kertas Kerja', $folder->fresh()->name);
        $this->assertNull($folder->fresh()->archived_at);
        $this->assertModelExists($member);
        $this->assertSame(1, $assignment->folders()->count());
    }

    public function test_failure_while_writing_the_audit_log_keeps_the_assignment_open(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $folder = Folder::factory()->for($assignment)->create();
        AuditLog::creating(fn () => throw new RuntimeException('Gagal menulis log.'));

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.penutupan.store', $assignment));

        $response->assertServerError();
        $this->assertSame('aktif', $assignment->fresh()->status->value);
        $this->assertFalse($folder->fresh()->is_locked);
    }
}
