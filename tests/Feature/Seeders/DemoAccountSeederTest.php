<?php

namespace Tests\Feature\Seeders;

use App\Enums\AuditAction;
use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\AssertsAuditLog;
use Tests\TestCase;

class DemoAccountSeederTest extends TestCase
{
    use AssertsAuditLog;

    /*
     * Bukan LazilyRefreshDatabase: migrasi harus berjalan sebelum test mengubah APP_ENV
     * ke production, karena migrate:fresh di production meminta konfirmasi.
     */
    use RefreshDatabase;

    private function runSeederIn(string $environment, string $accounts): void
    {
        $this->app->detectEnvironment(fn (): string => $environment);
        config(['demo.accounts' => $accounts]);

        $this->artisan('db:seed', ['--class' => 'DemoAccountSeeder', '--force' => true])->run();
    }

    public function test_refuses_to_run_in_production(): void
    {
        try {
            $this->runSeederIn('production', 'auditor@vidatra.sch.id:auditor-spi');
            $this->fail('DemoAccountSeeder seharusnya menolak berjalan di production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('DemoAccountSeeder ditolak', $exception->getMessage());
            $this->assertStringContainsString('APP_ENV saat ini "production"', $exception->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'auditor@vidatra.sch.id']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedEnvironments(): array
    {
        return [
            'local' => ['local'],
            'staging' => ['staging'],
        ];
    }

    #[DataProvider('allowedEnvironments')]
    public function test_creates_listed_accounts_with_their_roles_and_logs_each_assignment(string $environment): void
    {
        $this->runSeederIn($environment, 'auditor@vidatra.sch.id:auditor-spi, Ketua.DP@vidatra.sch.id:dewan-pengawas');

        $auditor = User::query()->where('email', 'auditor@vidatra.sch.id')->sole();
        $dewanPengawas = User::query()->where('email', 'ketua.dp@vidatra.sch.id')->sole();
        $this->assertTrue($auditor->hasRole(RoleSlug::AuditorSpi));
        $this->assertTrue($dewanPengawas->hasRole(RoleSlug::DewanPengawas));
        $this->assertAuditLogged(AuditAction::DemoRoleAssigned, $auditor, [
            'email' => 'auditor@vidatra.sch.id',
            'from' => null,
            'to' => 'auditor-spi',
            'source' => 'DemoAccountSeeder',
            'environment' => $environment,
        ]);
        $this->assertAuditLogged(AuditAction::DemoRoleAssigned, $dewanPengawas);
    }

    public function test_assigns_a_role_to_an_existing_account_and_logs_the_previous_role(): void
    {
        $user = User::factory()->withRole(RoleSlug::PicUnit)->create(['email' => 'siti@vidatra.sch.id']);

        $this->runSeederIn('local', 'siti@vidatra.sch.id:auditor-spi');

        $this->assertTrue($user->fresh()->hasRole(RoleSlug::AuditorSpi));
        $this->assertAuditLogged(AuditAction::DemoRoleAssigned, $user, [
            'email' => 'siti@vidatra.sch.id',
            'from' => 'pic-unit',
            'to' => 'auditor-spi',
            'source' => 'DemoAccountSeeder',
            'environment' => 'local',
        ]);
    }

    public function test_running_again_does_not_log_an_unchanged_role_twice(): void
    {
        $this->runSeederIn('local', 'auditor@vidatra.sch.id:auditor-spi');

        $this->runSeederIn('local', 'auditor@vidatra.sch.id:auditor-spi');

        $this->assertDatabaseCount('audit_logs', 1);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidEntries(): array
    {
        return [
            'unknown role' => ['auditor@vidatra.sch.id:auditor-spi,baru@vidatra.sch.id:root', 'Peran harus salah satu dari'],
            'email outside the foundation domain' => ['luar@gmail.com:auditor-spi', 'Email harus akun @vidatra.sch.id'],
            'entry without a role' => ['auditor@vidatra.sch.id', 'Peran harus salah satu dari'],
        ];
    }

    #[DataProvider('invalidEntries')]
    public function test_rejects_invalid_entries_before_changing_anything(string $accounts, string $expectedMessage): void
    {
        try {
            $this->runSeederIn('local', $accounts);
            $this->fail('DemoAccountSeeder seharusnya menolak entri yang tidak valid.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($expectedMessage, $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_refuses_the_super_admin_role_before_changing_anything(): void
    {
        $existingUser = User::factory()->withRole(RoleSlug::PicUnit)->create(['email' => 'siti@vidatra.sch.id']);

        try {
            $this->runSeederIn('local', 'auditor@vidatra.sch.id:auditor-spi,siti@vidatra.sch.id:super-admin');
            $this->fail('DemoAccountSeeder seharusnya menolak peran super-admin.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Peran super-admin tidak bisa ditetapkan lewat DemoAccountSeeder', $exception->getMessage());
            $this->assertStringContainsString('akun:tetapkan-peran', $exception->getMessage());
        }

        $this->assertTrue($existingUser->fresh()->hasRole(RoleSlug::PicUnit));
        $this->assertDatabaseMissing('users', ['email' => 'auditor@vidatra.sch.id']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_refuses_to_change_the_role_of_an_existing_super_admin_before_changing_anything(): void
    {
        $superAdmin = User::factory()->withRole(RoleSlug::SuperAdmin)->create(['email' => 'admin@vidatra.sch.id']);

        try {
            $this->runSeederIn('local', 'auditor@vidatra.sch.id:auditor-spi,admin@vidatra.sch.id:auditor-spi');
            $this->fail('DemoAccountSeeder seharusnya menolak mengubah peran Super Admin.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('admin@vidatra.sch.id saat ini Super Admin', $exception->getMessage());
        }

        $this->assertTrue($superAdmin->fresh()->hasRole(RoleSlug::SuperAdmin));
        $this->assertDatabaseMissing('users', ['email' => 'auditor@vidatra.sch.id']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_is_not_part_of_the_default_database_seeder(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config(['demo.accounts' => 'auditor@vidatra.sch.id:auditor-spi']);

        $this->artisan('db:seed', ['--force' => true]);

        $this->assertDatabaseMissing('users', ['email' => 'auditor@vidatra.sch.id']);
    }
}
