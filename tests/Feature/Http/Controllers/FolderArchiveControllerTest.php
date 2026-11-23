<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\Folder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsAuditLog;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class FolderArchiveControllerTest extends TestCase
{
    use AssertsAuditLog;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    public function test_assigned_auditor_archives_a_subfolder_with_everything_inside(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $standardFolder = Folder::factory()->for($assignment)->standard()->create();
        $folder = Folder::factory()->childOf($standardFolder)->create(['name' => 'Draf Lama']);
        $child = Folder::factory()->childOf($folder)->create();

        $response = $this->actingAsVerified($auditor)->post(route('penugasan.folder.arsip.store', [$assignment, $folder]));

        $response->assertRedirect(route('penugasan.show', $assignment));
        $this->assertNotNull($folder->fresh()->archived_at);
        $this->assertNotNull($child->fresh()->archived_at);
        $this->assertNull($standardFolder->fresh()->archived_at);
        $this->assertAuditLogged(AuditAction::FolderArchived, $folder, ['name' => 'Draf Lama', 'folder_count' => 2], $auditor);
    }

    public function test_archived_folders_leave_the_tree_and_are_listed_separately(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $standardFolder = Folder::factory()->for($assignment)->standard()->create();
        Folder::factory()->childOf($standardFolder)->archived()->create(['name' => 'Draf Lama']);

        $response = $this->actingAsVerified($auditor)->get(route('penugasan.show', $assignment));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('folders', 1)
            ->has('archivedFolders', 1)
            ->where('archivedFolders.0.name', 'Draf Lama'));
    }

    public function test_standard_folder_cannot_be_archived(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $standardFolder = Folder::factory()->for($assignment)->standard()->create();

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.folder.arsip.store', [$assignment, $standardFolder]));

        $response->assertForbidden();
        $this->assertNull($standardFolder->fresh()->archived_at);
    }

    public function test_archived_folder_cannot_receive_new_subfolders(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $assignment = Assignment::factory()->create();
        $archivedFolder = Folder::factory()->for($assignment)->archived()->create();

        $response = $this->actingAsVerified($dewanPengawas)->post(route('penugasan.folder.store', $assignment), [
            'parent_id' => $archivedFolder->id,
            'name' => 'Di dalam arsip',
        ]);

        $response->assertSessionHasErrors(['parent_id' => 'Folder induk tidak ditemukan di penugasan ini.']);
    }
}
