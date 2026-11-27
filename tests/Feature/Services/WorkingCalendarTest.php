<?php

namespace Tests\Feature\Services;

use App\Enums\HolidayType;
use App\Models\Holiday;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkingCalendarTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, bool}>
     */
    public static function days(): array
    {
        return [
            'ordinary weekday' => ['2027-04-07', true],
            'saturday' => ['2027-04-10', false],
            'sunday' => ['2027-04-11', false],
            'libur nasional' => ['2027-03-10', false],
            'cuti bersama' => ['2027-03-09', false],
        ];
    }

    #[DataProvider('days')]
    public function test_working_days_are_weekdays_that_are_not_seeded_holidays(string $date, bool $isWorkingDay): void
    {
        $this->assertSame($isWorkingDay, app(WorkingCalendar::class)->isWorkingDay(CarbonImmutable::parse($date)));
    }

    public function test_adding_working_days_skips_weekends_and_holidays(): void
    {
        $calendar = app(WorkingCalendar::class);

        $this->assertSame('2027-04-19', $calendar->addWorkingDays(CarbonImmutable::parse('2027-04-05'), 10)->toDateString());
        // Nyepi, Idulfitri, dan cuti bersamanya jatuh pada 8–15 Maret 2027.
        $this->assertSame('2027-03-18', $calendar->addWorkingDays(CarbonImmutable::parse('2027-03-05'), 3)->toDateString());
        $this->assertSame('2027-03-05', $calendar->addWorkingDays(CarbonImmutable::parse('2027-03-05'), 0)->toDateString());
    }

    public function test_subtracting_working_days_skips_weekends_and_holidays(): void
    {
        $calendar = app(WorkingCalendar::class);

        $this->assertSame('2027-03-04', $calendar->subWorkingDays(CarbonImmutable::parse('2027-03-17'), 3)->toDateString());
        $this->assertSame('2027-04-05', $calendar->subWorkingDays(CarbonImmutable::parse('2027-04-08'), 3)->toDateString());
    }

    public function test_a_holiday_added_to_the_table_is_honoured(): void
    {
        Holiday::factory()->create(['date' => '2040-01-04', 'type' => HolidayType::CutiBersama]);

        $calendar = app(WorkingCalendar::class);

        $this->assertFalse($calendar->isWorkingDay(CarbonImmutable::parse('2040-01-04')));
        $this->assertSame('2040-01-05', $calendar->addWorkingDays(CarbonImmutable::parse('2040-01-03'), 1)->toDateString());
    }

    public function test_the_date_is_read_in_the_business_timezone(): void
    {
        $calendar = app(WorkingCalendar::class);

        // Jumat 2027-04-09 16:30 UTC sudah Sabtu 00:30 WITA.
        $this->assertFalse($calendar->isWorkingDay(CarbonImmutable::parse('2027-04-09 16:30', 'UTC')));
        $this->assertTrue($calendar->isWorkingDay(CarbonImmutable::parse('2027-04-09 15:30', 'UTC')));
    }

    public function test_seeder_records_2027_holidays_with_their_type(): void
    {
        $this->assertSame(HolidayType::LiburNasional, Holiday::query()->whereDate('date', '2027-05-17')->sole()->type);
        $this->assertSame(HolidayType::CutiBersama, Holiday::query()->whereDate('date', '2027-05-18')->sole()->type);
        $this->assertSame(18, Holiday::query()->whereYear('date', 2027)->where('type', HolidayType::LiburNasional)->count());
        $this->assertSame(8, Holiday::query()->whereYear('date', 2027)->where('type', HolidayType::CutiBersama)->count());
    }
}
