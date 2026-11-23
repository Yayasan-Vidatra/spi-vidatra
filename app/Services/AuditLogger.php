<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur penulisan log audit berantai hash (US-24).
 *
 * Panggil di dalam transaksi aksi yang dicatat: jika aksi gagal, log ikut batal,
 * dan jika penulisan log gagal, exception-nya membatalkan aksi.
 */
class AuditLogger
{
    public function __construct(private Request $request) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(AuditAction $action, ?Model $subject = null, array $properties = [], ?User $user = null): AuditLog
    {
        return DB::transaction(function () use ($action, $subject, $properties, $user): AuditLog {
            // Mengunci baris terakhir sampai transaksi selesai, sehingga dua penulisan
            // bersamaan tidak memakai hash sebelumnya yang sama.
            $previousHash = AuditLog::query()->latest('id')->lockForUpdate()->value('hash');

            $log = (new AuditLog)->forceFill([
                'occurred_at' => now()->startOfSecond(),
                'user_id' => ($user ?? $this->request->user())?->getKey(),
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
                'properties' => $properties === [] ? null : $properties,
                'previous_hash' => $previousHash,
            ]);

            $log->forceFill(['hash' => $log->calculateHash()])->save();

            return $log;
        });
    }
}
