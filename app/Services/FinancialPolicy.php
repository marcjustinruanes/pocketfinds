<?php

namespace App\Services;

/** Official product and parcel amounts. Money is expressed in centavos. */
final class FinancialPolicy
{
    public const VERSION = 'fixed-parcel-v1';
    public const DELIVERY_CENTS = 5000;
    public const RIDER_CENTS = 1000;

    public static function commission(int $subtotal): int
    {
        return intdiv($subtotal + 5, 10);
    }
}
