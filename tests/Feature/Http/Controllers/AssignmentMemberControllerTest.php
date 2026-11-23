<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AssignmentMemberControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    public function test_dewan_pengawas_assigns_an_auditor_who_then_sees_the_assignment(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->create();

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.auditor.store', $assignment), [
            'user_id' => $auditor->id,
            'can_access_confidential' => '1',
        ]);

        $response->assertRedirect(route('penugasan.show', $assignment));
        $this->assertDatabaseHas('assignment_members', [
            'assignment_id' => $assignment->id,
            'user_id' => $auditor->id,
            'can_access_confidential' => true,
            'assigned_by' => $dewanPengawas->id,
        ]);
        $this->assertAuditLogged(AuditAction::AuditorAssigned, $assignment, [
            'user_id' => $auditor->id,
            'email' => $auditor->email,
            'can_access_confidential' => true,
        ], $dewanPengawas);
        $this->actingAsVerified($auditor)->get(route('penugasan.show', $assignment))->assertOk();
    }

    public function test_revoked_auditor_loses_access_and_the_revocation_is_logged(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create(['team_lead_id' => $auditor->id]);
        $member = $assignment->members()->sole();

        $response = $this->actingAsVerified($dewanPengawas)->delete(route('penugasan.auditor.destroy', [$assignment, $member]));

        $response->assertRedirect(route('penugasan.show', $assignment));
        $this->assertModelMissing($member);
        $this->assertNull($assignment->fresh()->team_lead_id);
        $this->assertAuditLogged(AuditAction::AuditorRevoked, $assignment, [
            'user_id' => $auditor->id,
            'email' => $auditor->email,
            'team_lead' => true,
        ], $dewanPengawas);
        $this->actingAsVerified($auditor)->get(route('penugasan.show', $assignment))->assertForbidden();
    }

    public function test_dewan_pengawas_grants_confidential_access_and_the_change_is_logged(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $member = $assignment->members()->sole();

        $response = $this->actingAsVerified($dewanPengawas)->patch(route('penugasan.auditor.update', [$assignment, $member]), [
            'can_access_confidential' => '1',
        ]);

        $response->assertRedirect(route('penugasan.show', $assignment));
        $this->assertTrue($member->fresh()->can_access_confidential);
        $this->assertAuditLogged(AuditAction::ConfidentialAccessChanged, $assignment, [
            'user_id' => $auditor->id,
            'email' => $auditor->email,
            'from' => false,
            'to' => true,
        ]);
    }

    public function test_rejects_assigning_a_user_who_is_not_an_auditor(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $picUnit = $this->userWithRole(RoleSlug::PicUnit);
        $assignment = Assignment::factory()->create();

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.auditor.store', $assignment), ['user_id' => $picUnit->id]);

        $response->assertSessionHasErrors(['user_id' => 'Hanya Auditor SPI yang aktif yang bisa ditunjuk.']);
        $this->assertDatabaseCount('assignment_members', 0);
    }

    public function test_rejects_assigning_the_same_auditor_twice(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.auditor.store', $assignment), ['user_id' => $auditor->id]);

        $response->assertSessionHasErrors(['user_id' => 'Auditor ini sudah ditunjuk di penugasan ini.']);
    }

    public function test_forbids_auditor_from_assigning_or_revoking_auditors(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $colleague = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $member = $assignment->members()->sole();

        $this->actingAsVerified($auditor)
            ->post(route('penugasan.auditor.store', $assignment), ['user_id' => $colleague->id])
            ->assertForbidden();
        $this->actingAsVerified($auditor)
            ->patch(route('penugasan.auditor.update', [$assignment, $member]), ['can_access_confidential' => '1'])
            ->assertForbidden();
        $this->actingAsVerified($auditor)
            ->delete(route('penugasan.auditor.destroy', [$assignment, $member]))
            ->assertForbidden();

        $this->assertSame(1, $assignment->members()->count());
        $this->assertFalse($member->fresh()->can_access_confidential);
    }

    public function test_member_of_another_assignment_returns_404(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $otherAssignment = Assignment::factory()->withAuditor($this->userWithRole(RoleSlug::AuditorSpi))->create();
        $otherMember = $otherAssignment->members()->sole();

        $response = $this->actingAsVerified($dewanPengawas)->delete(route('penugasan.auditor.destroy', [$assignment, $otherMember]));

        $response->assertNotFound();
        $this->assertModelExists($otherMember);
    }
}
