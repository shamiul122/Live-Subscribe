<?php

namespace App\Enums;

enum OrganizationMembershipStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function isActive(): bool
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
