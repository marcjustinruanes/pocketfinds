<?php

namespace App\Support;

final class PhilippineMobile
{
    public static function normalize(string $number): string
    {
        $number = trim($number);

        if (preg_match('/^9\d{9}$/D', $number)) {
            return '0'.$number;
        }

        return preg_match('/^\+?639\d{9}$/', $number)
            ? '0'.substr(ltrim($number, '+'), 2)
            : $number;
    }

    public static function variants(string $number): array
    {
        $local = self::normalize($number);

        return [$local, '63'.substr($local, 1), '+63'.substr($local, 1)];
    }
}
