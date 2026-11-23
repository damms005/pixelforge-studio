<?php

namespace App\Billing;

use InvalidArgumentException;

/**
 * All money is kept as integer nanodollars (1 USD = 1,000,000,000). Pixelforge reports costs
 * as exact decimal strings with nine decimal places, so integers hold every cost exactly and
 * no float is ever involved.
 */
final class Nanodollars
{
    public const PER_DOLLAR = 1_000_000_000;

    public static function fromDecimal(string $amount): int
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,9}))?$/', trim($amount), $matches)) {
            throw new InvalidArgumentException("Not an exact dollar amount with at most 9 decimals: [{$amount}].");
        }

        $nanodollars = (int) $matches[2] * self::PER_DOLLAR + (int) str_pad($matches[3] ?? '', 9, '0');

        return $matches[1] === '-' ? -$nanodollars : $nanodollars;
    }

    /**
     * Exact decimal string with 9 decimal places, the same format Pixelforge uses.
     */
    public static function toDecimal(int $nanodollars): string
    {
        $sign = $nanodollars < 0 ? '-' : '';
        $absolute = abs($nanodollars);

        return sprintf('%s%d.%09d', $sign, intdiv($absolute, self::PER_DOLLAR), $absolute % self::PER_DOLLAR);
    }
}
