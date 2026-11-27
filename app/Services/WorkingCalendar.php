<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Hitungan hari kerja untuk pengingat dan eskalasi tenggat (F-21, US-18): Senin–Jumat dikurangi libur nasional
 * dan cuti bersama di tabel `holidays`. Waktu dibaca sebagai tanggal kalender di zona kerja yayasan (WITA).
 */
class WorkingCalendar
{
    /**
     * Tanggal libur (Y-m-d), dimuat sekali per instance.
     *
     * @var array<string, true>|null
     */
    private ?array $holidayDates = null;

    public function isWorkingDay(CarbonInterface $date): bool
    {
        $day = $this->day($date);

        return ! $day->isWeekend() && ! isset($this->holidayDates()[$day->toDateString()]);
    }

    /**
     * The date that is the given number of working days after the date, not counting the date itself.
     */
    public function addWorkingDays(CarbonInterface $date, int $days): CarbonImmutable
    {
        return $this->shift($date, $days, 1);
    }

    /**
     * The date that is the given number of working days before the date, not counting the date itself.
     */
    public function subWorkingDays(CarbonInterface $date, int $days): CarbonImmutable
    {
        return $this->shift($date, $days, -1);
    }

    private function shift(CarbonInterface $date, int $days, int $direction): CarbonImmutable
    {
        $day = $this->day($date);

        for ($counted = 0; $counted < $days;) {
            $day = $day->addDays($direction);

            if ($this->isWorkingDay($day)) {
                $counted++;
            }
        }

        return $day;
    }

    private function day(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->setTimezone(config('app.business_timezone'))->startOfDay();
    }

    /**
     * @return array<string, true>
     */
    private function holidayDates(): array
    {
        return $this->holidayDates ??= Holiday::query()
            ->get(['date'])
            ->mapWithKeys(fn (Holiday $holiday): array => [$holiday->date->toDateString() => true])
            ->all();
    }
}
