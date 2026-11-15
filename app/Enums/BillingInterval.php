<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';

    /**
     * Human readable label for the interval.
     */
    public function label(): string
    {
        return match ($this) {
            self::Month => 'Monthly',
            self::Year => 'Yearly',
        };
    }

    /**
     * Advance a date by this interval, avoiding end-of-month overflow.
     */
    public function addTo(Carbon $date, int $count = 1): Carbon
    {
        return match ($this) {
            self::Month => $date->copy()->addMonthsNoOverflow($count),
            self::Year => $date->copy()->addYearsNoOverflow($count),
        };
    }

    /**
     * All values accepted by validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
