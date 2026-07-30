<?php

declare(strict_types=1);

namespace Capex\Domain;

/**
 * Currency is integer cents everywhere. No floats for stored/compared amounts.
 * The only float in the system is the FX rate, and it is applied here once.
 */
final class Money
{
    /**
     * Convert a local amount (in cents) to SGD cents at the envelope's FX rate.
     * Rounds half-up to the nearest cent.
     */
    public static function toSGD(int $localCents, float $rateToSgd): int
    {
        return (int) round($localCents * $rateToSgd, 0, PHP_ROUND_HALF_UP);
    }

    /** Parse a decimal string like "1234.56" into integer cents. */
    public static function toCents(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Parse a raw Bitrix money field into integer cents. Bitrix money userfields
     * arrive as a decimal, sometimes suffixed with a currency code ("1234.56|SGD").
     * Null/empty reads as 0.
     */
    public static function fieldToCents(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return 0;
        }

        $decimal = explode('|', (string) $raw)[0];

        return self::toCents($decimal);
    }

    /** Format integer cents back to a decimal string, e.g. 123456 -> "1234.56". */
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
