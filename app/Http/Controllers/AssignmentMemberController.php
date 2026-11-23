<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreAssignmentMemberRequest;
use App\Http\Requests\UpdateAssignmentMemberRequest;
use App\Models\Assignment;
use App\Models\AssignmentMember;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Menunjuk dan mencabut auditor serta mengatur akses Rahasianya (US-06, US-07).
 */
class AssignmentMemberController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function store(StoreAssignmentMemberRequest $request, Assignment $assignment): RedirectResponse
    {
        DB::transaction(function () use ($request, $assignment): void {
            $member = $assignment->members()->make();
            $member->forceFill([
                'user_id' => $request->integer('user_id'),
                'can_access_confidential' => $request->boolean('can_access_confidential'),
                'assigned_by' => $request->user()->id,
            ])->save();

            $this->auditLogger->record(AuditAction::AuditorAssigned, $assignment, [
                'user_id' => $member->user_id,
                'email' => $member->user->email,
                'can_access_confidential' => $member->can_access_confidential,
            ]);
        });

        return redirect()->route('penugasan.show', $assignment);
    }

    public function update(UpdateAssignmentMemberRequest $request, Assignment $assignment, AssignmentMember $member): RedirectResponse
    {
        $canAccessConfidential = $request->boolean('can_access_confidential');

        if ($member->can_access_confidential !== $canAccessConfidential) {
            DB::transaction(function () use ($assignment, $member, $canAccessConfidential): void {
                $member->forceFill(['can_access_confidential' => $canAccessConfidential])->save();

                $this->auditLogger->record(AuditAction::ConfidentialAccessChanged, $assignment, [
                    'user_id' => $member->user_id,
                    'email' => $member->user->email,
                    'from' => ! $canAccessConfidential,
                    'to' => $canAccessConfidential,
                ]);
            });
        }

        return redirect()->route('penugasan.show', $assignment);
    }

    public function destroy(Assignment $assignment, AssignmentMember $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $assignment);

        DB::transaction(function () use ($assignment, $member): void {
            $wasTeamLead = $assignment->team_lead_id === $member->user_id;

            if ($wasTeamLead) {
                $assignment->forceFill(['team_lead_id' => null])->save();
            }

            $member->delete();

            $this->auditLogger->record(AuditAction::AuditorRevoked, $assignment, [
                'user_id' => $member->user_id,
                'email' => $member->user->email,
                'team_lead' => $wasTeamLead,
            ]);
        });

        return redirect()->route('penugasan.show', $assignment);
    }
}
