<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Models\Folder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFiles;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class GeneralFolderControllerTest extends TestCase
{
    use InteractsWithFiles;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    private function generalFolder(string $name): Folder
    {
        return Folder::query()->whereNull('assignment_id')->where('name', $name)->sole();
    }

    public function test_dewan_pengawas_sees_the_general_folders_and_can_upload(): void
    {
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $templates = $this->generalFolder('Templat');
        $this->storedFile($templates, $dewanPengawas, fileAttributes: ['original_name' => 'Format KKA.pdf']);

        $response = $this->actingAsVerified($dewanPengawas)->get(route('folder-umum.index', ['folder' => $templates->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('FolderUmum/Index')
            ->where('folders.0.name', 'Regulasi dan Pedoman')
            ->where('folders.1.name', 'Templat')
            ->where('selectedFolder.id', $templates->id)
            ->where('selectedFolder.can.upload', true)
            ->where('selectedFolder.files.0.name', 'Format KKA.pdf')
            ->where('selectedFolder.files.0.scan_status', 'bersih')
            ->where('selectedFolder.files.0.uploader', $dewanPengawas->name)
            ->where('uploadRules.max_size_mb', 50),
        );
    }

    public function test_auditor_sees_the_files_but_cannot_upload(): void
    {
        $response = $this->actingAsVerified($this->userWithRole(RoleSlug::AuditorSpi))->get(route('folder-umum.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('selectedFolder.name', 'Regulasi dan Pedoman')
            ->where('selectedFolder.can.upload', false),
        );
    }

    public function test_auditor_does_not_see_rahasia_files_in_a_general_folder(): void
    {
        $this->fakeFileDisk();
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $folder = $this->generalFolder('Regulasi dan Pedoman');
        $this->storedFile($folder, $dewanPengawas, fileAttributes: ['original_name' => 'SOP.pdf']);
        $this->storedFile($folder, $dewanPengawas, fileAttributes: ['original_name' => 'Draf Rahasia.pdf', 'classification' => Classification::Rahasia]);

        $response = $this->actingAsVerified($this->userWithRole(RoleSlug::AuditorSpi))->get(route('folder-umum.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('selectedFolder.files', 1)
            ->where('selectedFolder.files.0.name', 'SOP.pdf'),
        );
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_other_roles_cannot_open_the_general_folders(RoleSlug $roleSlug): void
    {
        $this->actingAsVerified($this->userWithRole($roleSlug))->get(route('folder-umum.index'))->assertForbidden();
    }
}
