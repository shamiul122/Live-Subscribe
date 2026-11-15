<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Suspended = 'suspended';

    /**
     * Human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trialing',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * A non-terminal subscription still blocks a second subscription for the
     * same organization. Only expired is terminal.
     */
    public function isNonTerminal(): bool
    {
        return $this !== self::Expired;
    }

    public function isTerminal(): bool
    {
        return $this === self::Expired;
    }

    /**
     * Whether the status can currently grant feature access. Date windows are
     * still validated separately by the entitlement service.
     */
    public function grantsEntitlement(): bool
    {
        return match ($this) {
            self::Trialing, self::Active, self::PastDue, self::Cancelled => true,
            self::Suspended, self::Expired => false,
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

    /**
     * Values that still occupy the single non-terminal slot per organization.
     *
     * @return array<int, string>
     */
    public static function nonTerminalValues(): array
    {
        return array_values(array_filter(
            self::values(),
            fn (string $value): bool => $value !== self::Expired->value,
        ));
    }
}
