<?php

namespace App\Enums;

/**
 * Stable, enum-backed feature catalog.
 *
 * Feature keys are never accepted as free-form strings. Adding a feature means
 * adding a case here and a matching plan_feature row in the plan seeder.
 */
enum Feature: string
{
    case AuditLog = 'audit_log';
    case ApiRequests = 'api_requests';
    case ConnectedAccounts = 'connected_accounts';
    case Projects = 'projects';
    case Licenses = 'licenses';

    /**
     * Human readable label for the feature.
     */
    public function label(): string
    {
        return match ($this) {
            self::AuditLog => 'Audit log access',
            self::ApiRequests => 'API requests',
            self::ConnectedAccounts => 'Connected social accounts',
            self::Projects => 'Active projects',
            self::Licenses => 'Active licenses',
        };
    }

    /**
     * The measurement category for this feature.
     */
    public function type(): FeatureType
    {
        return match ($this) {
            self::AuditLog => FeatureType::Boolean,
            self::ApiRequests => FeatureType::Metered,
            self::ConnectedAccounts, self::Projects, self::Licenses => FeatureType::Resource,
        };
    }

    /**
     * A short noun used in user-facing limit descriptions.
     */
    public function unit(): string
    {
        return match ($this) {
            self::AuditLog => 'feature',
            self::ApiRequests => 'requests',
            self::ConnectedAccounts => 'accounts',
            self::Projects => 'projects',
            self::Licenses => 'licenses',
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
