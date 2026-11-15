<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * Human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
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
