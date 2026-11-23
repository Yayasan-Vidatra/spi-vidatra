<?php

namespace App\Actions;

use App\Enums\AssignmentStatus;
use App\Enums\AuditAction;
use App\Models\Assignment;
use App\Models\Folder;
use App\Models\FolderTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Membuat penugasan beserta kode, nomor urut per tahun, dan tujuh subfolder baku (US-05).
 */
class CreateAssignment
{
    private const int MaxAttempts = 3;

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{title: string, unit_id: int, start_date: string, end_date?: string|null, team_lead_id?: int|null}  $data
     */
    public function handle(User $creator, array $data): Assignment
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): Assignment => $this->create($creator, $data));
            } catch (UniqueConstraintViolationException $exception) {
                // Pembuatan lain mendapat nomor urut yang sama lebih dulu; ambil nomor berikutnya.
                if ($attempt >= self::MaxAttempts) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array{title: string, unit_id: int, start_date: string, end_date?: string|null, team_lead_id?: int|null}  $data
     */
    private function create(User $creator, array $data): Assignment
    {
        $unit = Unit::query()->findOrFail($data['unit_id']);
        $year = Carbon::parse($data['start_date'])->year;
        $title = trim($data['title']);
        $sequenceNumber = $this->nextSequenceNumber($year);

        $assignment = new Assignment([
            'title' => $title,
            'unit_id' => $unit->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
        ]);
        $assignment->forceFill([
            'code' => sprintf('%d-%02d-%s-%s', $year, $sequenceNumber, $unit->code, $title),
            'year' => $year,
            'sequence_number' => $sequenceNumber,
            'status' => AssignmentStatus::Aktif,
            'team_lead_id' => $data['team_lead_id'] ?? null,
            'created_by' => $creator->id,
        ])->save();

        $this->auditLogger->record(AuditAction::AssignmentCreated, $assignment, [
            'code' => $assignment->code,
            'unit' => $unit->code,
        ]);

        if ($assignment->team_lead_id !== null) {
            $this->addTeamLead($assignment, $creator);
        }

        $this->createStandardFolders($assignment, $creator);

        return $assignment;
    }

    private function nextSequenceNumber(int $year): int
    {
        $lastSequenceNumber = Assignment::query()
            ->where('year', $year)
            ->lockForUpdate()
            ->max('sequence_number');

        return ((int) $lastSequenceNumber) + 1;
    }

    private function addTeamLead(Assignment $assignment, User $creator): void
    {
        $member = $assignment->members()->make();
        $member->forceFill([
            'user_id' => $assignment->team_lead_id,
            'can_access_confidential' => false,
            'assigned_by' => $creator->id,
        ])->save();

        $this->auditLogger->record(AuditAction::AuditorAssigned, $assignment, [
            'user_id' => $assignment->team_lead_id,
            'team_lead' => true,
            'can_access_confidential' => false,
        ]);
    }

    private function createStandardFolders(Assignment $assignment, User $creator): void
    {
        $templates = FolderTemplate::query()->orderBy('sort_order')->get();

        foreach ($templates as $template) {
            (new Folder)->forceFill([
                'assignment_id' => $assignment->id,
                'parent_id' => null,
                'folder_template_id' => $template->id,
                'name' => $template->name,
                'classification' => $template->default_classification,
                'is_locked' => false,
                'created_by' => $creator->id,
            ])->save();
        }
    }
}
