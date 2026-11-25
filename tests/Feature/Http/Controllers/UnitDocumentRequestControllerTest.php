<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\RequestItemStatus;
use App\Enums\RoleSlug;
use App\Enums\ScanStatus;
use App\Models\DocumentRequest;
use App\Models\RequestItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFiles;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class UnitDocumentRequestControllerTest extends TestCase
{
    use InteractsWithFiles;
    use InteractsWithRoles;
    use LazilyRefreshDatabase;

    private function unit(string $code): Unit
    {
        return Unit::query()->where('code', $code)->sole();
    }

    private function pic(string $unitCode = 'SMP'): User
    {
        return User::factory()->picOf($this->unit($unitCode))->withTwoFactor()->acceptedConfidentiality()->create();
    }

    public function test_pic_sees_only_requests_for_their_unit(): void
    {
        $pic = $this->pic();
        $ownRequest = DocumentRequest::factory()->create(['unit_id' => $this->unit('SMP')->id]);
        RequestItem::factory()->for($ownRequest)->create();
        DocumentRequest::factory()->create(['unit_id' => $this->unit('SD')->id]);

        $response = $this->actingAsVerified($pic)->get(route('permintaan-unit.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('PermintaanUnit/Index')
            ->where('unit', 'SMP YPVDP')
            ->has('documentRequests', 1)
            ->where('documentRequests.0.id', $ownRequest->id)
            ->where('documentRequests.0.pending_count', 1)
            ->where('auth.can.viewUnitRequests', true)
        );
    }

    public function test_request_detail_does_not_reveal_the_assignment(): void
    {
        $pic = $this->pic();
        $documentRequest = DocumentRequest::factory()->create(['unit_id' => $this->unit('SMP')->id]);
        RequestItem::factory()->for($documentRequest)->create();

        $response = $this->actingAsVerified($pic)->get(route('permintaan-unit.show', $documentRequest));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('PermintaanUnit/Show')
            ->missing('assignment')
            ->missing('documentRequest.assignment_code')
        );
        $response->assertDontSee($documentRequest->assignment->code);
    }

    public function test_detail_shows_slot_versions_notes_and_upload_state(): void
    {
        $this->fakeFileDisk();
        $pic = $this->pic();
        $documentRequest = DocumentRequest::factory()->create(['unit_id' => $this->unit('SMP')->id]);
        $file = $this->storedFile($documentRequest->folder, $pic, ScanStatus::Bersih, ['original_name' => 'Notulen.pdf']);
        $pendingFile = $this->storedFile($documentRequest->folder, $pic, ScanStatus::Menunggu, ['original_name' => 'Rekap.pdf']);
        RequestItem::factory()->for($documentRequest)->create([
            'name' => 'Notulen', 'sort_order' => 1, 'file_id' => $file->id,
            'status' => RequestItemStatus::PerluPerbaikan, 'reviewer_note' => 'Kurang tanda tangan',
        ]);
        RequestItem::factory()->for($documentRequest)->create([
            'name' => 'Rekap', 'sort_order' => 2, 'file_id' => $pendingFile->id, 'status' => RequestItemStatus::Diunggah,
        ]);
        RequestItem::factory()->for($documentRequest)->withStatus(RequestItemStatus::Diterima)->create(['name' => 'SK', 'sort_order' => 3]);

        $response = $this->actingAsVerified($pic)->get(route('permintaan-unit.show', $documentRequest));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.0.reviewer_note', 'Kurang tanda tangan')
            ->where('items.0.can_upload', true)
            ->where('items.0.versions.0.download_url', route('berkas.versi.unduh', [$file, $file->currentVersion]))
            ->where('items.1.versions.0.scan_status', 'menunggu')
            ->where('items.1.versions.0.download_url', null)
            ->where('items.2.is_locked', true)
            ->where('items.2.can_upload', false)
        );
    }

    public function test_pic_of_another_unit_cannot_open_the_request_through_a_direct_url(): void
    {
        $documentRequest = DocumentRequest::factory()->create(['unit_id' => $this->unit('SMP')->id]);

        $this->actingAsVerified($this->pic('SD'))->get(route('permintaan-unit.show', $documentRequest))->assertForbidden();
    }

    public function test_pic_without_a_unit_is_signed_out_instead_of_seeing_requests(): void
    {
        $pic = User::factory()->withRole(RoleSlug::PicUnit)->withTwoFactor()->acceptedConfidentiality()->create(['unit_id' => null]);

        $this->actingAsVerified($pic)->get(route('permintaan-unit.index'))->assertRedirect(route('akses.belum-diberi'));
    }

    /**
     * @return array<string, array{RoleSlug}>
     */
    public static function rolesOtherThanPicUnit(): array
    {
        return [
            'dewan pengawas' => [RoleSlug::DewanPengawas],
            'auditor spi' => [RoleSlug::AuditorSpi],
            'super admin' => [RoleSlug::SuperAdmin],
            'pengurus yayasan' => [RoleSlug::PengurusYayasan],
            'peninjau tamu' => [RoleSlug::PeninjauTamu],
        ];
    }

    #[DataProvider('rolesOtherThanPicUnit')]
    public function test_other_roles_cannot_open_h08(RoleSlug $roleSlug): void
    {
        $user = $this->userWithRole($roleSlug);
        $documentRequest = DocumentRequest::factory()->create();

        $this->actingAsVerified($user)->get(route('permintaan-unit.index'))->assertForbidden();
        $this->actingAsVerified($user)->get(route('permintaan-unit.show', $documentRequest))->assertForbidden();
    }
}
