<?php

namespace Tests\Feature\Policies;

use App\Enums\Classification;
use App\Enums\RequestItemStatus;
use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\File;
use App\Models\RequestItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequestItemPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function uploadedSlot(Assignment $assignment, Classification $classification = Classification::Internal, RequestItemStatus $status = RequestItemStatus::Diunggah): RequestItem
    {
        $documentRequest = DocumentRequest::factory()->for($assignment)->create(['unit_id' => Unit::query()->where('code', 'SMP')->value('id')]);
        $file = File::factory()->for($documentRequest->folder)->withVersion()->create(['classification' => $classification]);

        return RequestItem::factory()->for($documentRequest)->withStatus($status)->create(['file_id' => $file->id]);
    }

    public function test_dewan_pengawas_and_assigned_auditors_review_an_uploaded_slot(): void
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $item = $this->uploadedSlot(Assignment::factory()->withAuditor($auditor)->create());

        $this->assertTrue(User::factory()->withRole(RoleSlug::DewanPengawas)->create()->can('review', $item));
        $this->assertTrue($auditor->can('review', $item));
    }

    public function test_auditor_not_assigned_to_the_assignment_cannot_review(): void
    {
        $item = $this->uploadedSlot(Assignment::factory()->create());

        $this->assertFalse(User::factory()->withRole(RoleSlug::AuditorSpi)->create()->can('review', $item));
    }

    /**
     * @return array<string, array{RoleSlug}>
     */
    public static function rolesThatCannotReview(): array
    {
        return [
            'pic unit' => [RoleSlug::PicUnit],
            'pengurus yayasan' => [RoleSlug::PengurusYayasan],
            'super admin' => [RoleSlug::SuperAdmin],
            'peninjau tamu' => [RoleSlug::PeninjauTamu],
        ];
    }

    #[DataProvider('rolesThatCannotReview')]
    public function test_roles_outside_spi_cannot_review(RoleSlug $roleSlug): void
    {
        $item = $this->uploadedSlot(Assignment::factory()->create());
        $user = $roleSlug === RoleSlug::PicUnit
            ? User::factory()->picOf($item->documentRequest->unit)->create()
            : User::factory()->withRole($roleSlug)->create();

        $this->assertFalse($user->can('review', $item));
    }

    public function test_closed_assignment_cannot_be_reviewed(): void
    {
        $item = $this->uploadedSlot(Assignment::factory()->closed()->create());

        $this->assertFalse(User::factory()->withRole(RoleSlug::DewanPengawas)->create()->can('review', $item));
    }

    /**
     * @return array<string, array{RequestItemStatus}>
     */
    public static function statusesThatAreNotReviewable(): array
    {
        return [
            'menunggu' => [RequestItemStatus::Menunggu],
            'perlu perbaikan' => [RequestItemStatus::PerluPerbaikan],
            'diterima' => [RequestItemStatus::Diterima],
        ];
    }

    #[DataProvider('statusesThatAreNotReviewable')]
    public function test_only_an_uploaded_slot_can_be_reviewed(RequestItemStatus $status): void
    {
        $item = $this->uploadedSlot(Assignment::factory()->create(), status: $status);

        $this->assertFalse(User::factory()->withRole(RoleSlug::DewanPengawas)->create()->can('review', $item));
    }

    public function test_rahasia_slot_file_needs_confidential_access_to_review(): void
    {
        $auditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $confidentialAuditor = User::factory()->withRole(RoleSlug::AuditorSpi)->create();
        $assignment = Assignment::factory()->withAuditor($auditor)->withAuditor($confidentialAuditor, canAccessConfidential: true)->create();
        $item = $this->uploadedSlot($assignment, Classification::Rahasia);

        $this->assertFalse($auditor->can('review', $item));
        $this->assertTrue($confidentialAuditor->can('review', $item));
    }

    public function test_slot_without_a_file_cannot_be_reviewed(): void
    {
        $item = RequestItem::factory()->withStatus(RequestItemStatus::Diunggah)->create();

        $this->assertFalse(User::factory()->withRole(RoleSlug::DewanPengawas)->create()->can('review', $item));
    }
}
