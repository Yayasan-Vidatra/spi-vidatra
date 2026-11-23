<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssignInitialRoleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{RoleSlug}>
     */
    public static function bootstrapRoles(): array
    {
        return [
            'super admin' => [RoleSlug::SuperAdmin],
            'dewan pengawas' => [RoleSlug::DewanPengawas],
        ];
    }

    #[DataProvider('bootstrapRoles')]
    public function test_assigns_first_holder_of_a_bootstrap_role_and_logs_it(RoleSlug $roleSlug): void
    {
        Log::spy();

        $this->artisan('akun:tetapkan-peran', ['email' => 'Admin@Vidatra.sch.id', 'peran' => $roleSlug->value])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@vidatra.sch.id',
            'role_id' => Role::query()->where('slug', $roleSlug)->value('id'),
        ]);
        Log::shouldHaveReceived('notice')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'akun:tetapkan-peran')
                && $context['email'] === 'admin@vidatra.sch.id'
                && $context['peran'] === $roleSlug->value);
    }

    public function test_assigns_role_to_existing_account_without_role(): void
    {
        $user = User::factory()->create(['email' => 'admin@vidatra.sch.id']);

        $this->artisan('akun:tetapkan-peran', ['email' => 'admin@vidatra.sch.id', 'peran' => 'super-admin'])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->hasRole(RoleSlug::SuperAdmin));
    }

    #[DataProvider('bootstrapRoles')]
    public function test_refuses_when_the_role_already_has_a_holder(RoleSlug $roleSlug): void
    {
        User::factory()->withRole($roleSlug)->create();
        Log::spy();

        $this->artisan('akun:tetapkan-peran', ['email' => 'kedua@vidatra.sch.id', 'peran' => $roleSlug->value])
            ->expectsOutputToContain('Perubahan peran harus lewat persetujuan Dewan Pengawas (US-22).')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'kedua@vidatra.sch.id']);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_refuses_to_change_the_role_of_an_account_that_already_has_one(): void
    {
        $user = User::factory()->withRole(RoleSlug::AuditorSpi)->create(['email' => 'auditor@vidatra.sch.id']);

        $this->artisan('akun:tetapkan-peran', ['email' => 'auditor@vidatra.sch.id', 'peran' => 'super-admin'])
            ->expectsOutputToContain('Perubahan peran harus lewat persetujuan Dewan Pengawas (US-22).')
            ->assertFailed();

        $this->assertTrue($user->fresh()->hasRole(RoleSlug::AuditorSpi));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonBootstrapRoles(): array
    {
        return [
            'auditor spi' => [RoleSlug::AuditorSpi->value],
            'pic unit' => [RoleSlug::PicUnit->value],
            'pengurus yayasan' => [RoleSlug::PengurusYayasan->value],
            'peninjau tamu' => [RoleSlug::PeninjauTamu->value],
            'unknown role' => ['root'],
        ];
    }

    #[DataProvider('nonBootstrapRoles')]
    public function test_refuses_roles_other_than_super_admin_and_dewan_pengawas(string $role): void
    {
        Log::spy();

        $this->artisan('akun:tetapkan-peran', ['email' => 'siti@vidatra.sch.id', 'peran' => $role])
            ->expectsOutputToContain('Perintah ini hanya untuk peran super-admin dan dewan-pengawas.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_refuses_email_outside_the_foundation_domain(): void
    {
        $this->artisan('akun:tetapkan-peran', ['email' => 'admin@gmail.com', 'peran' => 'super-admin'])
            ->expectsOutputToContain('Email harus akun @vidatra.sch.id.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
