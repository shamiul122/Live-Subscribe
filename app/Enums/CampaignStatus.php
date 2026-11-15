<?php

namespace App\Enums;

/**
 * Lifecycle status of a marketing campaign.
 *
 * The status is only ever moved through the explicit transitions implemented by
 * CampaignService. Terminal statuses are never editable or restartable.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Scheduled => 'Scheduled',
            self::Running => 'Running',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    /**
     * A terminal campaign can no longer be edited, resumed, or restarted.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Failed], true);
    }

    /**
     * Only pre-execution campaigns may have their definition edited.
     */
    public function canEdit(): bool
    {
        return in_array($this, [self::Draft, self::Ready, self::Scheduled], true);
    }

    public function canMarkReady(): bool
    {
        return $this === self::Draft;
    }

    public function canPause(): bool
    {
        return $this === self::Running;
    }

    public function canResume(): bool
    {
        return $this === self::Paused;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Ready, self::Scheduled, self::Running, self::Paused], true);
    }

    /**
     * Recipients may only be materialised before delivery begins.
     */
    public function canPrepareRecipients(): bool
    {
        return in_array($this, [self::Draft, self::Ready], true);
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
