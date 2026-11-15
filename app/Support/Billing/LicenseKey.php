<?php

namespace App\Support\Billing;

use Illuminate\Support\Str;

final class LicenseKey
{
    public const PREFIX = 'ls_live_';

    private const LENGTH = 40;

    /**
     * Generate a high-entropy raw license key. The raw key is only ever
     * returned to the caller and shown once; only its hash is persisted.
     */
    public static function generate(): string
    {
        return self::PREFIX.Str::random(self::LENGTH);
    }

    /**
     * Hash a raw license key for storage and lookup.
     */
    public static function hash(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }

    /**
     * Non-secret identifier used for support and display.
     */
    public static function prefix(string $rawKey): string
    {
        return substr($rawKey, 0, 12);
    }

    public static function lastFour(string $rawKey): string
    {
        return substr($rawKey, -4);
    }

    /**
     * Masked display form used after the one-time reveal.
     */
    public static function mask(string $prefix, string $lastFour): string
    {
        return $prefix.'••••'.$lastFour;
    }
}
