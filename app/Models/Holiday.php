<?php

namespace App\Models;

use App\Enums\HolidayType;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Libur nasional atau cuti bersama untuk hitungan hari kerja (F-21). Diisi HolidaySeeder.
 */
#[Fillable(['date', 'description', 'type'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => HolidayType::class,
        ];
    }

    /**
     * Holidays from today on, keyed by date, so date fields can warn about a deadline on a holiday.
     *
     * @return array<string, string>
     */
    public static function upcomingDescriptions(): array
    {
        return self::query()
            ->whereDate('date', '>=', now(config('app.business_timezone'))->toDateString())
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn (self $holiday): array => [$holiday->date->toDateString() => $holiday->description])
            ->all();
    }
}
