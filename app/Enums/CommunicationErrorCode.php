<?php

namespace App\Enums;

/**
 * Normalized communication error codes.
 */
enum CommunicationErrorCode: string
{
    case ProviderNotConfigured = 'provider_not_configured';
    case CapabilityNotSupported = 'capability_not_supported';
    case InvalidAccountCredentials = 'invalid_account_credentials';
    case InvalidAccount = 'invalid_account';
    case AccountNotFound = 'account_not_found';
    case InvalidRecipient = 'invalid_recipient';
    case RateLimited = 'rate_limited';
    case ProviderRejected = 'provider_rejected';
    case ProviderError = 'provider_error';
    case NetworkError = 'network_error';
    case InvalidPayload = 'invalid_payload';

    public function label(): string
    {
        return match ($this) {
            self::ProviderNotConfigured => 'Provider not configured',
            self::CapabilityNotSupported => 'Capability not supported',
            self::InvalidAccountCredentials => 'Invalid account credentials',
            self::InvalidAccount => 'Invalid account',
            self::AccountNotFound => 'Account not found',
            self::InvalidRecipient => 'Invalid recipient',
            self::RateLimited => 'Rate limited',
            self::ProviderRejected => 'Provider rejected',
            self::ProviderError => 'Provider error',
            self::NetworkError => 'Network error',
            self::InvalidPayload => 'Invalid payload',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
