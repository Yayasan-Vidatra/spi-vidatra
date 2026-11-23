<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menetapkan peran akun uji dari DEMO_ACCOUNTS, di luar alur persetujuan US-22.
 *
 * Jalur sementara khusus non-produksi (tercatat di log deviasi PRD). Seeder ini tidak
 * menetapkan peran super-admin dan tidak mengubah peran akun yang sudah Super Admin;
 * Super Admin hanya ditetapkan lewat akun:tetapkan-peran. Sengaja tidak
 * dipanggil DatabaseSeeder; jalankan manual: php artisan db:seed --class=DemoAccountSeeder
 */
class DemoAccountSeeder extends Seeder
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $allowedEnvironments = config('demo.allowed_environments');

        if (! app()->environment($allowedEnvironments)) {
            throw new RuntimeException(sprintf(
                'DemoAccountSeeder ditolak: hanya boleh dijalankan di APP_ENV %s, sedangkan APP_ENV saat ini "%s". '
                .'Di produksi, peran ditetapkan lewat alur persetujuan Dewan Pengawas (US-22).',
                implode(' atau ', $allowedEnvironments),
                app()->environment(),
            ));
        }

        $accounts = $this->parseAccounts((string) config('demo.accounts'));

        if ($accounts === []) {
            $this->command?->warn('DEMO_ACCOUNTS kosong; tidak ada akun yang diproses.');

            return;
        }

        $this->ensureNoSuperAdminIsChanged(array_keys($accounts));

        foreach ($accounts as $email => $roleSlug) {
            $this->assignRole($email, $roleSlug);
        }
    }

    /**
     * Peran Super Admin yang sudah ada tidak boleh diubah lewat seeder ini; dicek sebelum ada data yang ditulis.
     *
     * @param  list<string>  $emails
     */
    private function ensureNoSuperAdminIsChanged(array $emails): void
    {
        $superAdminEmails = User::query()
            ->whereIn('email', $emails)
            ->whereRelation('role', 'slug', RoleSlug::SuperAdmin)
            ->pluck('email');

        if ($superAdminEmails->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'DEMO_ACCOUNTS tidak valid: %s saat ini Super Admin. Peran Super Admin tidak bisa diubah lewat DemoAccountSeeder.',
                $superAdminEmails->implode(', '),
            ));
        }
    }

    /**
     * Parse and validate every entry before anything is written, so a typo changes nothing.
     *
     * @return array<string, RoleSlug>
     */
    private function parseAccounts(string $value): array
    {
        $domain = config('services.google.allowed_domain');
        $accounts = [];

        foreach (array_filter(array_map('trim', explode(',', $value))) as $entry) {
            [$email, $role] = array_pad(array_map('trim', explode(':', $entry, 2)), 2, '');
            $email = Str::lower($email);
            $roleSlug = RoleSlug::tryFrom($role);

            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! Str::endsWith($email, '@'.$domain)) {
                throw new RuntimeException("DEMO_ACCOUNTS tidak valid: \"{$entry}\". Email harus akun @{$domain}.");
            }

            if ($roleSlug === RoleSlug::SuperAdmin) {
                throw new RuntimeException(
                    "DEMO_ACCOUNTS tidak valid: \"{$entry}\". Peran super-admin tidak bisa ditetapkan lewat DemoAccountSeeder; "
                    .'gunakan php artisan akun:tetapkan-peran.'
                );
            }

            if ($roleSlug === null) {
                $validRoles = collect(RoleSlug::cases())
                    ->reject(fn (RoleSlug $slug): bool => $slug === RoleSlug::SuperAdmin)
                    ->map(fn (RoleSlug $slug): string => $slug->value)
                    ->implode(', ');

                throw new RuntimeException("DEMO_ACCOUNTS tidak valid: \"{$entry}\". Peran harus salah satu dari: {$validRoles}.");
            }

            $accounts[$email] = $roleSlug;
        }

        return $accounts;
    }

    private function assignRole(string $email, RoleSlug $roleSlug): void
    {
        DB::transaction(function () use ($email, $roleSlug): void {
            $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
            $user = User::query()->firstOrCreate(['email' => $email], ['name' => Str::before($email, '@')]);
            $previousRoleSlug = $user->role?->slug;

            if ($previousRoleSlug === $roleSlug) {
                $this->command?->line("{$email} sudah berperan {$roleSlug->value}; dilewati.");

                return;
            }

            $user->role()->associate($role)->save();

            $this->auditLogger->record(AuditAction::DemoRoleAssigned, $user, [
                'email' => $email,
                'from' => $previousRoleSlug?->value,
                'to' => $roleSlug->value,
                'source' => 'DemoAccountSeeder',
                'environment' => app()->environment(),
            ]);

            $this->command?->info("{$email} ditetapkan sebagai {$roleSlug->value}.");
        });
    }
}
