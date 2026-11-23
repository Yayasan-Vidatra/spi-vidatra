<?php

namespace App\Console\Commands;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bootstrap Super Admin dan Dewan Pengawas pertama, di luar alur persetujuan US-22
 * (tercatat di log deviasi PRD). Hanya berlaku sekali per peran.
 */
#[Signature('akun:tetapkan-peran {email : Alamat @vidatra.sch.id} {peran : super-admin atau dewan-pengawas}')]
#[Description('Menetapkan Super Admin atau Dewan Pengawas pertama (bootstrap, sekali per peran)')]
class AssignInitialRole extends Command
{
    /**
     * @var list<RoleSlug>
     */
    private const array BootstrapRoles = [RoleSlug::SuperAdmin, RoleSlug::DewanPengawas];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $roleSlug = RoleSlug::tryFrom((string) $this->argument('peran'));

        $context = [
            'email' => $email,
            'peran' => $this->argument('peran'),
            'os_user' => get_current_user(),
            'host' => gethostname(),
        ];

        $refusal = $this->refusalReason($email, $roleSlug);

        if ($refusal !== null) {
            Log::warning('akun:tetapkan-peran ditolak: '.$refusal, $context);
            $this->components->error($refusal);

            return self::FAILURE;
        }

        $refusal = DB::transaction(function () use ($email, $roleSlug): ?string {
            $role = Role::query()->where('slug', $roleSlug)->lockForUpdate()->firstOrFail();

            if ($role->users()->exists()) {
                return "Sudah ada pengguna dengan peran {$role->name}. Perubahan peran harus lewat persetujuan Dewan Pengawas (US-22).";
            }

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => Str::before($email, '@')],
            );

            if ($user->role_id !== null) {
                return 'Pengguna ini sudah memiliki peran. Perubahan peran harus lewat persetujuan Dewan Pengawas (US-22).';
            }

            $user->role()->associate($role)->save();

            return null;
        });

        if ($refusal !== null) {
            Log::warning('akun:tetapkan-peran ditolak: '.$refusal, $context);
            $this->components->error($refusal);

            return self::FAILURE;
        }

        Log::notice('akun:tetapkan-peran: peran bootstrap ditetapkan di luar alur persetujuan.', $context);
        $this->components->info("Peran {$roleSlug->value} ditetapkan untuk {$email}.");

        return self::SUCCESS;
    }

    private function refusalReason(string $email, ?RoleSlug $roleSlug): ?string
    {
        if (! in_array($roleSlug, self::BootstrapRoles, true)) {
            return 'Perintah ini hanya untuk peran super-admin dan dewan-pengawas.';
        }

        if (! Str::endsWith($email, '@'.config('services.google.allowed_domain'))) {
            return 'Email harus akun @'.config('services.google.allowed_domain').'.';
        }

        return null;
    }
}
