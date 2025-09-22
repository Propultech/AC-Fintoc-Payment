<?php
declare(strict_types=1);

namespace Fintoc\Payment\Utils;

/**
 * Utility methods for amount handling.
 */
class AmountUtils
{
    /**
     * Round a decimal amount to an integer using PHP_ROUND_HALF_UP strategy.
     *
     * @param float|int $amount
     * @return int
     */
    public static function roundToIntHalfUp($amount): int
    {
        return (int)\round((float)$amount, 0, PHP_ROUND_HALF_UP);
    }
}
