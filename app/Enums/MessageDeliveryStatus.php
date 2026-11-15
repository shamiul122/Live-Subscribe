<?php

namespace App\Enums;

/**
 * Delivery status for outbound messages.
 */
enum MessageDeliveryStatus: string
{
    case Queued = 'queued';
    case Pending = 'pending';
    case Processing = 'processing';
    case Sending = 'sending';
    case Accepted = 'accepted';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Sending => 'Sending',
            self::Accepted => 'Accepted',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
            self::Failed => 'Failed',
        };
    }

    /**
     * @return array<int, string>
     */
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Monotonic ranking used so an out-of-order webhook can never move a
     * message backwards (for example delivered -> sent).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Queued, self::Pending => 0,
            self::Processing, self::Sending => 1,
            self::Accepted => 2,
            self::Sent => 3,
            self::Delivered => 4,
            self::Read => 5,
            self::Failed => 6,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Read, self::Failed], true);
    }
}
