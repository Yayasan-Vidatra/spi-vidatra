<?php

namespace Tests\Feature\Models;

use App\Enums\RequestItemStatus;
use App\Models\DocumentRequest;
use App\Models\RequestItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequestItemTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function itemDueOn(string $dueDate, RequestItemStatus $status): RequestItem
    {
        $request = DocumentRequest::factory()->create(['due_date' => $dueDate]);

        return RequestItem::factory()->for($request)->withStatus($status)->create();
    }

    public function test_deadline_lasts_until_the_end_of_the_due_date_in_wita(): void
    {
        $item = $this->itemDueOn('2026-12-10', RequestItemStatus::Menunggu);

        // 23.59.59 WITA = 15.59.59 UTC pada tanggal yang sama.
        $this->assertFalse($item->isOverdue(CarbonImmutable::parse('2026-12-10 15:59:59', 'UTC')));
        $this->assertTrue($item->isOverdue(CarbonImmutable::parse('2026-12-10 16:00:00', 'UTC')));
    }

    /**
     * @return array<string, array{RequestItemStatus, bool}>
     */
    public static function statusesAfterTheDeadline(): array
    {
        return [
            'menunggu' => [RequestItemStatus::Menunggu, true],
            'perlu perbaikan' => [RequestItemStatus::PerluPerbaikan, true],
            'diunggah' => [RequestItemStatus::Diunggah, false],
            'diterima' => [RequestItemStatus::Diterima, false],
        ];
    }

    #[DataProvider('statusesAfterTheDeadline')]
    public function test_only_slots_still_awaiting_an_upload_become_overdue(RequestItemStatus $status, bool $expectedOverdue): void
    {
        $item = $this->itemDueOn('2026-12-10', $status);

        $this->assertSame($expectedOverdue, $item->isOverdue(CarbonImmutable::parse('2026-12-11 08:00:00', 'Asia/Makassar')));
    }
}
