<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithFiles;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class TrashControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithFiles;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function trashedFile(Folder $folder, User $deleter, array $attributes = []): File
    {
        $file = $this->storedFile($folder, $deleter, fileAttributes: $attributes);
        $file->forceFill(['deleted_by' => $deleter->id])->save();
        $file->delete();

        return $file;
    }

    private function generalFolder(): Folder
    {
        return Folder::query()->whereNull('assignment_id')->where('name', 'Templat')->sole();
    }

    public function test_dewan_pengawas_sees_every_trashed_file_with_restore_and_purge(): void
    {
        $this->freezeSecond();
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $file = $this->trashedFile(Folder::factory()->for($assignment)->create(['name' => '03 Kertas Kerja']), $dewanPengawas, ['original_name' => 'KKA Lama.pdf']);
        $this->trashedFile($this->generalFolder(), $dewanPengawas);
        $this->storedFile(Folder::factory()->create(), $dewanPengawas);

        $response = $this->actingAsVerified($dewanPengawas)->get(route('tempat-sampah.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('TempatSampah/Index')
            ->where('retentionDays', 30)
            ->has('files.data', 2)
            ->where('files.data.1.name', 'KKA Lama.pdf')
            ->where('files.data.1.folder', '03 Kertas Kerja')
            ->where('files.data.1.assignment_code', $assignment->code)
            ->where('files.data.1.deleted_by', $dewanPengawas->name)
            ->where('files.data.1.purge_at', $file->fresh()->deleted_at->addDays(30)->toIso8601String())
            ->where('files.data.1.can.restore', true)
            ->where('files.data.1.can.forceDelete', true));
    }

    public function test_auditor_sees_only_trashed_files_of_their_assignments_without_actions(): void
    {
        $this->fakeFileDisk();
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $folder = Folder::factory()->for($assignment)->create();
        $this->trashedFile($folder, $auditor, ['original_name' => 'Milik Penugasan.pdf']);
        $this->trashedFile($folder, $auditor, ['original_name' => 'Rahasia.pdf', 'classification' => Classification::Rahasia]);
        $this->trashedFile(Folder::factory()->for($assignment)->confidential()->create(), $auditor, ['original_name' => 'Di Folder Rahasia.pdf']);
        $this->trashedFile(Folder::factory()->create(), User::factory()->create(), ['original_name' => 'Penugasan Lain.pdf']);
        $this->trashedFile($this->generalFolder(), User::factory()->create(), ['original_name' => 'Folder Umum.pdf']);

        $response = $this->actingAsVerified($auditor)->get(route('tempat-sampah.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('files.data', 1)
            ->where('files.data.0.name', 'Milik Penugasan.pdf')
            ->where('files.data.0.can.restore', false)
            ->where('files.data.0.can.forceDelete', false));
    }

    public function test_auditor_with_confidential_access_also_sees_trashed_rahasia_files(): void
    {
        $this->fakeFileDisk();
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor, canAccessConfidential: true)->create();
        $this->trashedFile(Folder::factory()->for($assignment)->confidential()->create(), $auditor, ['original_name' => 'Indikasi.pdf']);

        $response = $this->actingAsVerified($auditor)->get(route('tempat-sampah.index'));

        $response->assertInertia(fn (Assert $page) => $page->where('files.data.0.name', 'Indikasi.pdf'));
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_other_roles_cannot_open_the_trash(RoleSlug $roleSlug): void
    {
        $this->actingAsVerified($this->userWithRole($roleSlug))->get(route('tempat-sampah.index'))->assertForbidden();
    }

    public function test_dewan_pengawas_restores_a_file_to_its_folder(): void
    {
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $folder = Folder::factory()->create();
        $file = $this->trashedFile($folder, $dewanPengawas, ['original_name' => 'Laporan.pdf']);

        $response = $this->actingAsVerified($dewanPengawas)->post(route('tempat-sampah.pulihkan', $file));

        $response->assertRedirect(route('penugasan.show', ['assignment' => $folder->assignment_id, 'folder' => $folder->id]));
        $file->refresh();
        $this->assertFalse($file->trashed());
        $this->assertNull($file->deleted_by);
        $this->assertSame('Laporan.pdf', $file->original_name);
        $this->assertAuditLogged(AuditAction::FileRestored, $file, [
            'name' => 'Laporan.pdf',
            'restored_as' => 'Laporan.pdf',
            'folder_id' => $folder->id,
        ], $dewanPengawas);
    }

    public function test_restore_renames_the_file_when_an_active_file_has_its_name(): void
    {
        $this->travelTo('2026-11-24 10:00:00');
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $folder = Folder::factory()->create();
        $trashed = $this->trashedFile($folder, $dewanPengawas, ['original_name' => 'Laporan.pdf']);
        $this->storedFile($folder, $dewanPengawas, fileAttributes: ['original_name' => 'Laporan.pdf']);

        $response = $this->actingAsVerified($dewanPengawas)->post(route('tempat-sampah.pulihkan', $trashed));

        $this->assertSame('Laporan (dipulihkan 2026-11-24).pdf', $trashed->fresh()->original_name);
        $response->assertInertiaFlash('success', 'Sudah ada berkas bernama Laporan.pdf, jadi berkas dipulihkan sebagai Laporan (dipulihkan 2026-11-24).pdf.');
    }

    public function test_restore_adds_a_counter_when_the_restored_name_is_also_taken(): void
    {
        $this->travelTo('2026-11-24 10:00:00');
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $folder = Folder::factory()->create();
        $trashed = $this->trashedFile($folder, $dewanPengawas, ['original_name' => 'Catatan']);
        $this->storedFile($folder, $dewanPengawas, fileAttributes: ['original_name' => 'Catatan']);
        $this->storedFile($folder, $dewanPengawas, fileAttributes: ['original_name' => 'Catatan (dipulihkan 2026-11-24)']);

        $this->actingAsVerified($dewanPengawas)->post(route('tempat-sampah.pulihkan', $trashed));

        $this->assertSame('Catatan (dipulihkan 2026-11-24 2)', $trashed->fresh()->original_name);
    }

    public function test_auditor_cannot_restore_even_their_own_file(): void
    {
        $this->fakeFileDisk();
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $file = $this->trashedFile(Folder::factory()->for(Assignment::factory()->withAuditor($auditor))->create(), $auditor);

        $this->actingAsVerified($auditor)->post(route('tempat-sampah.pulihkan', $file))->assertForbidden();

        $this->assertTrue($file->fresh()->trashed());
    }

    public function test_cannot_restore_into_an_archived_folder(): void
    {
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $file = $this->trashedFile(Folder::factory()->archived()->create(), $dewanPengawas);

        $this->actingAsVerified($dewanPengawas)->post(route('tempat-sampah.pulihkan', $file))->assertForbidden();
    }

    public function test_active_file_cannot_be_restored_or_purged_through_the_trash(): void
    {
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $file = $this->storedFile(Folder::factory()->create(), $dewanPengawas);

        $this->actingAsVerified($dewanPengawas)->post(route('tempat-sampah.pulihkan', $file))->assertNotFound();
        $this->actingAsVerified($dewanPengawas)->delete(route('tempat-sampah.destroy', $file))->assertNotFound();
        $this->assertNotNull($file->fresh());
    }

    public function test_dewan_pengawas_purges_a_file_with_its_versions_and_content(): void
    {
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $file = $this->trashedFile(Folder::factory()->create(), $dewanPengawas);
        $quarantined = FileVersion::factory()->for($file)->create(['storage_path' => 'karantina/'.fake()->uuid()]);
        Storage::disk(config('berkas.disk'))->put($quarantined->storage_path, 'virus');
        $paths = [$file->currentVersion->storage_path, $quarantined->storage_path];

        $response = $this->actingAsVerified($dewanPengawas)->delete(route('tempat-sampah.destroy', $file));

        $response->assertRedirect(route('tempat-sampah.index'));
        $this->assertNull(File::withTrashed()->find($file->id));
        $this->assertSame(0, FileVersion::query()->where('file_id', $file->id)->count());
        Storage::disk(config('berkas.disk'))->assertMissing($paths);
        $this->assertAuditLogged(AuditAction::FilePurged, $file, [
            'name' => $file->original_name,
            'folder_id' => $file->folder_id,
            'version_count' => 2,
            'automatic' => false,
        ], $dewanPengawas);
    }

    public function test_auditor_cannot_purge(): void
    {
        $this->fakeFileDisk();
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $file = $this->trashedFile(Folder::factory()->for(Assignment::factory()->withAuditor($auditor))->create(), $auditor);

        $this->actingAsVerified($auditor)->delete(route('tempat-sampah.destroy', $file))->assertForbidden();

        $this->assertNotNull(File::withTrashed()->find($file->id));
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_other_roles_cannot_restore_or_purge(RoleSlug $roleSlug): void
    {
        $this->fakeFileDisk();
        $file = $this->trashedFile(Folder::factory()->create(), User::factory()->create());
        $user = $this->userWithRole($roleSlug);

        $this->actingAsVerified($user)->post(route('tempat-sampah.pulihkan', $file))->assertForbidden();
        $this->actingAsVerified($user)->delete(route('tempat-sampah.destroy', $file))->assertForbidden();
    }
}
