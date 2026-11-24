<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\File;
use App\Models\Folder;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fileIn(Folder $folder, string $name, array $attributes = []): File
    {
        return File::factory()->for($folder)->withVersion()->create(['original_name' => $name, ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function resultNames(User $user, array $query): array
    {
        $names = [];

        $this->actingAsVerified($user)->get(route('cari.index', $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$names): void {
                $page->component('Cari/Index');
                $names = array_column($page->toArray()['props']['results']['data'] ?? [], 'name');
            });

        return $names;
    }

    public function test_finds_files_by_part_of_their_name(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $folder = Folder::factory()->create();
        $this->fileIn($folder, 'Laporan Kas Semester 1.pdf');
        $this->fileIn($folder, 'kas kecil.xlsx');
        $this->fileIn($folder, 'Surat Tugas.pdf');

        $this->assertSame(['kas kecil.xlsx', 'Laporan Kas Semester 1.pdf'], $this->resultNames($dewanPengawas, ['q' => 'kas']));
    }

    public function test_like_wildcards_in_the_search_term_are_matched_literally(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $folder = Folder::factory()->create();
        $this->fileIn($folder, 'Realisasi 100% anggaran.pdf');
        $this->fileIn($folder, 'Realisasi 1000 anggaran.pdf');
        $this->fileIn($folder, 'kas_kecil.pdf');
        $this->fileIn($folder, 'kasXkecil.pdf');

        $this->assertSame(['Realisasi 100% anggaran.pdf'], $this->resultNames($dewanPengawas, ['q' => '100%']));
        $this->assertSame(['kas_kecil.pdf'], $this->resultNames($dewanPengawas, ['q' => 'kas_']));
    }

    public function test_filters_by_label_unit_year_and_assignment(): void
    {
        $dewanPengawas = $this->userWithRole(RoleSlug::DewanPengawas);
        $smp = Unit::query()->where('code', 'SMP')->sole();
        $sma = Unit::query()->where('code', 'SMA')->sole();
        $smpAssignment = Assignment::factory()->create(['unit_id' => $smp->id, 'year' => 2026]);
        $smaAssignment = Assignment::factory()->create(['unit_id' => $sma->id, 'year' => 2025]);
        $this->fileIn(Folder::factory()->for($smpAssignment)->create(), 'SMP Internal.pdf');
        $this->fileIn(Folder::factory()->for($smpAssignment)->create(), 'SMP Umum.pdf', ['classification' => Classification::Umum]);
        $this->fileIn(Folder::factory()->for($smaAssignment)->create(), 'SMA Internal.pdf');
        $this->fileIn(Folder::query()->whereNull('assignment_id')->where('name', 'Templat')->sole(), 'Templat KKA.pdf');

        $this->assertSame(['SMP Umum.pdf'], $this->resultNames($dewanPengawas, ['classification' => 'umum']));
        $this->assertSame(['SMA Internal.pdf'], $this->resultNames($dewanPengawas, ['unit_id' => $sma->id]));
        $this->assertSame(['SMA Internal.pdf'], $this->resultNames($dewanPengawas, ['year' => 2025]));
        $this->assertSame(['SMP Internal.pdf', 'SMP Umum.pdf'], $this->resultNames($dewanPengawas, ['assignment_id' => $smpAssignment->id]));
        $this->assertSame(['SMP Internal.pdf'], $this->resultNames($dewanPengawas, ['q' => 'SMP', 'classification' => 'internal', 'year' => 2026]));
    }

    public function test_auditor_only_finds_files_they_may_open(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create();
        $folder = Folder::factory()->for($assignment)->create();
        $this->fileIn($folder, 'Audit Kas Boleh.pdf');
        $this->fileIn($folder, 'Audit Kas Label Rahasia.pdf', ['classification' => Classification::Rahasia]);
        $this->fileIn(Folder::factory()->childOf(Folder::factory()->for($assignment)->confidential()->create())->create(), 'Audit Kas di Folder Rahasia.pdf');
        $this->fileIn(Folder::factory()->create(), 'Audit Kas Penugasan Lain.pdf');
        $this->fileIn(Folder::query()->whereNull('assignment_id')->where('name', 'Templat')->sole(), 'Audit Kas Templat.pdf');
        $this->fileIn($folder, 'Audit Kas Terhapus.pdf')->delete();

        $this->assertSame(['Audit Kas Boleh.pdf', 'Audit Kas Templat.pdf'], $this->resultNames($auditor, ['q' => 'Audit Kas']));
    }

    public function test_filtering_by_another_assignment_does_not_reveal_its_files(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        Assignment::factory()->withAuditor($auditor)->create();
        $otherAssignment = Assignment::factory()->create();
        $this->fileIn(Folder::factory()->for($otherAssignment)->create(), 'Bukan Milik.pdf');

        $this->assertSame([], $this->resultNames($auditor, ['assignment_id' => $otherAssignment->id]));
    }

    public function test_assignment_and_year_options_only_list_visible_assignments(): void
    {
        $auditor = $this->userWithRole(RoleSlug::AuditorSpi);
        $assignment = Assignment::factory()->withAuditor($auditor)->create(['year' => 2026]);
        Assignment::factory()->create(['year' => 2024]);

        $response = $this->actingAsVerified($auditor)->get(route('cari.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('results', null)
            ->has('options.assignments', 1)
            ->where('options.assignments.0.code', $assignment->code)
            ->where('options.years', [2026]));
    }

    public function test_rejects_an_unknown_label(): void
    {
        $response = $this->actingAsVerified($this->userWithRole(RoleSlug::DewanPengawas))->get(route('cari.index', ['classification' => 'publik']));

        $response->assertSessionHasErrors(['classification' => 'Label tidak valid.']);
    }

    #[DataProvider('rolesWithoutAssignmentAccess')]
    public function test_other_roles_cannot_search(RoleSlug $roleSlug): void
    {
        $this->actingAsVerified($this->userWithRole($roleSlug))->get(route('cari.index', ['q' => 'kas']))->assertForbidden();
    }
}
