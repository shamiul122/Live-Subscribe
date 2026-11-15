<?php

namespace App\Enums;

/**
 * Stable reason codes returned by the central entitlement service. They are
 * safe to expose to clients and never contain tenant secrets.
 */
enum EntitlementReason: string
{
    case Allowed = 'allowed';
    case OrganizationMissing = 'organization_missing';
    case NotMember = 'not_member';
    case PermissionDenied = 'permission_denied';
    case NoSubscription = 'no_subscription';
    case SubscriptionNotEntitled = 'subscription_not_entitled';
    case FeatureNotIncluded = 'feature_not_included';
    case UsageLimitReached = 'usage_limit_reached';
    case ResourceLimitReached = 'resource_limit_reached';

    public function label(): string
    {
        return match ($this) {
            self::Allowed => 'Allowed',
            self::OrganizationMissing => 'Organization not found',
            self::NotMember => 'Not a member',
            self::PermissionDenied => 'Permission denied',
            self::NoSubscription => 'No subscription',
            self::SubscriptionNotEntitled => 'Subscription is not entitled',
            self::FeatureNotIncluded => 'Feature is not included in the plan',
            self::UsageLimitReached => 'Usage limit reached',
            self::ResourceLimitReached => 'Resource limit reached',
        };
    }

    public function isAllowed(): bool
    {
        return $this === self::Allowed;
    }

    /**
     * Suggested HTTP status for an HTTP-facing denial.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::Allowed => 200,
            self::OrganizationMissing => 404,
            self::NotMember, self::PermissionDenied, self::FeatureNotIncluded => 403,
            self::NoSubscription, self::SubscriptionNotEntitled => 402,
            self::UsageLimitReached, self::ResourceLimitReached => 422,
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
