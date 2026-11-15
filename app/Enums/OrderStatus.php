<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * A pending order can still be paid. Paid, expired and cancelled orders
     * cannot be retried in place.
     */
    public function isPayable(): bool
    {
        return $this === self::Pending;
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
