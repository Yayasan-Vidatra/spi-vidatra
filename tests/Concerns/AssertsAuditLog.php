<?php

namespace Tests\Concerns;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AssertsAuditLog
{
    /**
     * Assert exactly one log row of the action exists for the subject, with the given properties.
     *
     * @param  array<string, mixed>|null  $properties
     */
    protected function assertAuditLogged(AuditAction $action, Model $subject, ?array $properties = null, ?User $user = null): AuditLog
    {
        $log = AuditLog::query()
            ->where('action', $action)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->sole();

        if ($properties !== null) {
            $this->assertEquals($properties, $log->properties);
        }

        if ($user !== null) {
            $this->assertSame($user->id, $log->user_id);
        }

        return $log;
    }
}
