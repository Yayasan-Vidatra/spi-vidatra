<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\AuditAction;
use App\Models\Assignment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Menutup dan membuka kembali penugasan (US-08, UC-13).
 */
class AssignmentClosureController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('close', $assignment);

        DB::transaction(function () use ($request, $assignment): void {
            $assignment->forceFill([
                'status' => AssignmentStatus::Ditutup,
                'closed_at' => now(),
                'closed_by' => $request->user()->id,
            ])->save();

            $assignment->folders()->update(['is_locked' => true]);

            $this->auditLogger->record(AuditAction::AssignmentClosed, $assignment, ['code' => $assignment->code]);
        });

        Inertia::flash('success', 'Penugasan ditutup. Semua folder kini baca saja dan penugasan pindah ke Arsip.');

        return redirect()->route('penugasan.show', $assignment);
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        Gate::authorize('reopen', $assignment);

        DB::transaction(function () use ($assignment): void {
            $assignment->forceFill([
                'status' => AssignmentStatus::Aktif,
                'closed_at' => null,
                'closed_by' => null,
            ])->save();

            $assignment->folders()->update(['is_locked' => false]);

            $this->auditLogger->record(AuditAction::AssignmentReopened, $assignment, ['code' => $assignment->code]);
        });

        Inertia::flash('success', 'Penugasan dibuka kembali.');

        return redirect()->route('penugasan.show', $assignment);
    }
}
