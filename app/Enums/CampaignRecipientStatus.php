<?php

namespace App\Enums;

/**
 * Per-recipient lifecycle for a campaign.
 *
 * The status is a foundation for the Step 6 communication engine integration.
 * Nothing in this step marks a recipient as sent or delivered: only a future
 * delivery integration may advance a recipient past `pending`.
 */
enum CampaignRecipientStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Queued => 'Queued',
            self::Sending => 'Sending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    /**
     * Monotonic ranking used so an out-of-order delivery callback can never
     * move a recipient backwards (for example delivered -> queued).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Sending => 2,
            self::Sent => 3,
            self::Delivered => 4,
            self::Read => 5,
            self::Failed, self::Skipped => 6,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Read, self::Failed, self::Skipped], true);
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
