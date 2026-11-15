<?php

namespace App\Enums;

enum LicenseStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Revoked = 'revoked';

    /**
     * Human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
            self::Revoked => 'Revoked',
        };
    }

    /**
     * Revoked is terminal; expired can still be revoked.
     */
    public function isTerminal(): bool
    {
        return $this === self::Revoked;
    }

    /**
     * Whether the license currently counts against the plan's license
     * capacity.
     */
    public function countsAgainstCapacity(): bool
    {
        return $this === self::Active;
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
