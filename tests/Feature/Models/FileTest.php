<?php

namespace Tests\Feature\Models;

use App\Enums\Classification;
use App\Enums\EncryptionSyncStatus;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FileTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Files across every case the policy distinguishes: assignment membership, confidential access,
     * file label, Rahasia folders at different depths, general folders, and the trash.
     *
     * @return array{auditor: User, confidentialAuditor: User}
     */
    private function fileMatrix(): array
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $confidentialAuditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $assignment = Assignment::factory()->withAuditor($auditor)->withAuditor($confidentialAuditor, canAccessConfidential: true)->create();
        $otherAssignment = Assignment::factory()->create();
        $generalFolder = Folder::query()->whereNull('assignment_id')->where('name', 'Templat')->sole();

        $internalFolder = Folder::factory()->for($assignment)->create();
        $confidentialFolder = Folder::factory()->for($assignment)->confidential()->create();
        $deepFolder = Folder::factory()->childOf(Folder::factory()->childOf($confidentialFolder)->create())->create();
        $generalConfidentialChild = Folder::factory()->create(['assignment_id' => null, 'parent_id' => $generalFolder->id, 'classification' => Classification::Rahasia]);

        foreach ([$internalFolder, $confidentialFolder, $deepFolder, Folder::factory()->for($otherAssignment)->create(), $generalFolder, $generalConfidentialChild] as $folder) {
            foreach (Classification::cases() as $classification) {
                File::factory()->for($folder)->withVersion()->create(['classification' => $classification]);
            }
        }

        File::factory()->for($internalFolder)->withVersion()->create()->delete();

        return ['auditor' => $auditor, 'confidentialAuditor' => $confidentialAuditor];
    }

    public function test_visible_to_scope_matches_the_policy_for_every_role(): void
    {
        $users = [
            ...$this->fileMatrix(),
            'dewanPengawas' => User::factory()->withRole(RoleSlug::DewanPengawas)->create(),
            'outsideAuditor' => User::factory()->withRole(RoleSlug::AuditorSpi)->create(),
            'superAdmin' => User::factory()->withRole(RoleSlug::SuperAdmin)->create(),
            'picUnit' => User::factory()->withRole(RoleSlug::PicUnit)->create(),
        ];
        $allFiles = File::withTrashed()->with('folder')->orderBy('id')->get();

        foreach ($users as $label => $user) {
            $expected = $allFiles->filter(fn (File $file): bool => $user->can('view', $file))->pluck('id')->values()->all();
            $actual = File::query()->visibleTo($user)->orderBy('id')->pluck('id')->all();

            $this->assertSame($expected, $actual, "Scope dan policy berbeda untuk {$label}.");
        }

        $this->assertGreaterThan(0, count(File::query()->visibleTo($users['auditor'])->get()));
        $this->assertSame(0, File::query()->visibleTo($users['superAdmin'])->count());
    }

    public function test_encryption_sync_status_names_the_pending_change(): void
    {
        $plainConfidential = File::factory()->confidential()->withVersion()->create()->load('versions');
        $encryptedInternal = File::factory()->withVersion(versionAttributes: ['is_encrypted' => true])->create()->load('versions');
        $encryptedConfidential = File::factory()->confidential()->withVersion(versionAttributes: ['is_encrypted' => true])->create()->load('versions');

        $this->assertSame(EncryptionSyncStatus::MenungguEnkripsi, $plainConfidential->encryptionSyncStatus());
        $this->assertSame(EncryptionSyncStatus::MenungguDekripsi, $encryptedInternal->encryptionSyncStatus());
        $this->assertNull($encryptedConfidential->encryptionSyncStatus());
    }

    public function test_encryption_mismatch_scope_matches_the_php_check(): void
    {
        $this->fileMatrix();
        File::query()->where('classification', Classification::Umum)->each(
            fn (File $file) => $file->currentVersion->forceFill(['is_encrypted' => true])->save(),
        );
        $allFiles = File::withTrashed()->with(['folder', 'versions'])->orderBy('id')->get();

        $expected = $allFiles->filter(fn (File $file): bool => $file->encryptionSyncStatus() !== null)->pluck('id')->values()->all();
        $actual = File::withTrashed()->withEncryptionMismatch()->orderBy('id')->pluck('id')->all();

        $this->assertNotSame([], $expected);
        $this->assertSame($expected, $actual);
    }
}
