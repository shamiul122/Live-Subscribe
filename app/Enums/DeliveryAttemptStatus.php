<?php

namespace App\Enums;

/**
 * State of one provider delivery attempt.
 */
enum DeliveryAttemptStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Accepted = 'accepted';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Accepted => 'Accepted',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
            self::Failed => 'Failed',
        };
    }

    public static function fromMessageStatus(MessageDeliveryStatus $status): self
    {
        return match ($status) {
            MessageDeliveryStatus::Queued => self::Pending,
            MessageDeliveryStatus::Pending => self::Pending,
            MessageDeliveryStatus::Processing => self::Processing,
            MessageDeliveryStatus::Sending => self::Processing,
            MessageDeliveryStatus::Accepted => self::Accepted,
            MessageDeliveryStatus::Sent => self::Sent,
            MessageDeliveryStatus::Delivered => self::Delivered,
            MessageDeliveryStatus::Read => self::Read,
            MessageDeliveryStatus::Failed => self::Failed,
        };
    }
}
