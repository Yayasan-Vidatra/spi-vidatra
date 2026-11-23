<?php

namespace Tests\Feature\Services;

use App\Enums\AuditAction;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_records_who_did_what_to_which_object_and_from_where(): void
    {
        $this->travelTo('2026-11-23 09:30:15');
        $user = User::factory()->create();
        $assignment = Assignment::factory()->create();
        $this->app->instance('request', Request::create('/', 'POST', server: [
            'REMOTE_ADDR' => '10.1.2.3',
            'HTTP_USER_AGENT' => 'Peramban Uji',
        ]));

        $log = app(AuditLogger::class)->record(AuditAction::AssignmentClosed, $assignment, ['code' => 'X'], $user);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'occurred_at' => '2026-11-23 09:30:15',
            'user_id' => $user->id,
            'action' => 'penugasan.ditutup',
            'subject_type' => 'penugasan',
            'subject_id' => $assignment->id,
            'ip_address' => '10.1.2.3',
            'user_agent' => 'Peramban Uji',
        ]);
        $this->assertSame(['code' => 'X'], $log->fresh()->properties);
    }

    public function test_each_row_stores_the_hash_of_the_previous_row(): void
    {
        $logger = app(AuditLogger::class);

        $first = $logger->record(AuditAction::AssignmentCreated);
        $second = $logger->record(AuditAction::AssignmentClosed);

        $this->assertNull($first->previous_hash);
        $this->assertSame($first->hash, $second->previous_hash);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $second->hash);
    }

    public function test_hash_recomputed_from_the_stored_row_matches_after_a_mysql_round_trip(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record(AuditAction::AssignmentCreated);
        $log = $logger->record(AuditAction::FolderLabelChanged, properties: [
            'to' => 'rahasia',
            'from' => 'internal',
            'detail' => ['zeta' => 1, 'alpha' => ['b' => true, 'a' => null]],
        ]);

        $storedLog = AuditLog::query()->findOrFail($log->id);

        $this->assertSame($log->hash, $storedLog->calculateHash());
    }

    public function test_tampering_with_a_stored_row_breaks_its_hash(): void
    {
        $log = app(AuditLogger::class)->record(AuditAction::FolderRenamed, properties: ['from' => 'A', 'to' => 'B']);

        DB::table('audit_logs')->where('id', $log->id)->update(['properties' => json_encode(['from' => 'A', 'to' => 'C'])]);

        $this->assertNotSame($log->hash, AuditLog::query()->findOrFail($log->id)->calculateHash());
    }

    public function test_rows_cannot_be_updated_through_the_application(): void
    {
        $log = app(AuditLogger::class)->record(AuditAction::AssignmentCreated);

        $this->expectException(LogicException::class);

        $log->forceFill(['action' => AuditAction::AssignmentClosed])->save();
    }

    public function test_rows_cannot_be_deleted_through_the_application(): void
    {
        $log = app(AuditLogger::class)->record(AuditAction::AssignmentCreated);

        $this->expectException(LogicException::class);

        $log->delete();
    }

    public function test_log_is_discarded_when_the_surrounding_action_fails(): void
    {
        try {
            DB::transaction(function (): void {
                app(AuditLogger::class)->record(AuditAction::AssignmentCreated);

                throw new RuntimeException('Aksi gagal setelah log ditulis.');
            });
        } catch (RuntimeException) {
            // Kegagalan aksi yang disengaja.
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }
}
