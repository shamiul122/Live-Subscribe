<?php

namespace App\Enums;

enum SubscriptionEventType: string
{
    case Created = 'created';
    case TrialStarted = 'trial_started';
    case TrialConverted = 'trial_converted';
    case Activated = 'activated';
    case Renewed = 'renewed';
    case PaymentFailed = 'payment_failed';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Resumed = 'resumed';
    case PlanChangeScheduled = 'plan_change_scheduled';
    case PlanChangeCancelled = 'plan_change_cancelled';
    case PlanChanged = 'plan_changed';
    case Suspended = 'suspended';
    case Reinstated = 'reinstated';
    case Expired = 'expired';
    case OrganizationDeleted = 'organization_deleted';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Subscription created',
            self::TrialStarted => 'Trial started',
            self::TrialConverted => 'Trial converted',
            self::Activated => 'Activated',
            self::Renewed => 'Renewed',
            self::PaymentFailed => 'Payment failed',
            self::PastDue => 'Past due',
            self::Cancelled => 'Cancelled',
            self::Resumed => 'Resumed',
            self::PlanChangeScheduled => 'Plan change scheduled',
            self::PlanChangeCancelled => 'Plan change cancelled',
            self::PlanChanged => 'Plan changed',
            self::Suspended => 'Suspended',
            self::Reinstated => 'Reinstated',
            self::Expired => 'Expired',
            self::OrganizationDeleted => 'Organization deleted',
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
