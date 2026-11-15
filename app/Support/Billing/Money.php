<?php

namespace App\Support\Billing;

use InvalidArgumentException;

/**
 * Currency-aware money formatting and parsing.
 *
 * Money is always stored and calculated as integer minor units. This class
 * never performs arithmetic on floats; it only renders and parses decimal
 * strings using integer/string math.
 */
final class Money
{
    /**
     * ISO 4217 currencies that do not use two decimal places.
     *
     * @var array<string, int>
     */
    private const EXPONENTS = [
        'BIF' => 0,
        'CLP' => 0,
        'DJF' => 0,
        'GNF' => 0,
        'ISK' => 0,
        'JPY' => 0,
        'KMF' => 0,
        'KRW' => 0,
        'PYG' => 0,
        'RWF' => 0,
        'UGX' => 0,
        'UYI' => 0,
        'VND' => 0,
        'VUV' => 0,
        'XAF' => 0,
        'XOF' => 0,
        'XPF' => 0,
        'BHD' => 3,
        'IQD' => 3,
        'JOD' => 3,
        'KWD' => 3,
        'LYD' => 3,
        'OMR' => 3,
        'TND' => 3,
    ];

    /**
     * Display symbols. Currencies without an entry fall back to the ISO code.
     *
     * @var array<string, string>
     */
    private const SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CNY' => '¥',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'NZD' => 'NZ$',
        'CHF' => 'CHF',
        'SEK' => 'kr',
        'NOK' => 'kr',
        'DKK' => 'kr',
        'INR' => '₹',
        'BRL' => 'R$',
        'ZAR' => 'R',
        'NGN' => '₦',
        'KES' => 'KSh',
    ];

    /**
     * The number of decimal places used by a currency.
     */
    public static function exponent(string $currency): int
    {
        return self::EXPONENTS[strtoupper($currency)] ?? 2;
    }

    /**
     * Render an integer minor-unit amount using the currency's conventions.
     */
    public static function format(int $minor, string $currency): string
    {
        $currency = strtoupper($currency);
        $exponent = self::exponent($currency);
        $negative = $minor < 0;
        $absolute = abs($minor);

        $divisor = 10 ** $exponent;
        $whole = intdiv($absolute, $divisor);
        $fraction = $absolute % $divisor;

        $number = self::group((string) $whole);

        if ($exponent > 0) {
            $number .= '.'.str_pad((string) $fraction, $exponent, '0', STR_PAD_LEFT);
        }

        if ($negative) {
            $number = '-'.$number;
        }

        if (! isset(self::SYMBOLS[$currency])) {
            return $currency.' '.$number;
        }

        $symbol = self::SYMBOLS[$currency];

        // Currencies with a trailing symbol (kr) read better as "1 234,00 kr"
        // but Step 3 keeps a simple, deterministic layout.
        return $symbol.$number;
    }

    /**
     * Parse a decimal string into integer minor units. Rejects floats and
     * malformed input so callers cannot smuggle floating point money in.
     */
    public static function toMinor(string $amount, string $currency): int
    {
        $exponent = self::exponent($currency);
        $normalized = str_replace([',', ' ', '_'], '', trim($amount));

        if ($normalized === '' || preg_match('/^-?\d+(\.\d+)?$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Invalid money value.');
        }

        $negative = str_starts_with($normalized, '-');

        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        $parts = explode('.', $normalized, 2);
        $whole = $parts[0];
        $fraction = str_pad($parts[1] ?? '', $exponent, '0');
        $fraction = substr($fraction, 0, $exponent);

        $minor = ((int) $whole) * (10 ** $exponent);

        if ($exponent > 0) {
            $minor += (int) $fraction;
        }

        return $negative ? -$minor : $minor;
    }

    /**
     * Insert thousands separators without touching floating point.
     */
    private static function group(string $digits): string
    {
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits) ?? $digits;
    }
}
