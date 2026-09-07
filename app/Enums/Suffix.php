<?php

namespace App\Enums;

/**
 * Name suffix, collected at registration alongside given/last/middle name.
 * The single source of truth for the users.suffix column's allowed values —
 * the DB column constraint and the registration dropdown are both generated
 * from this list, so there is nowhere else that hardcodes the option set.
 */
enum Suffix: string
{
    case Jr = 'Jr.';
    case Sr = 'Sr.';
    case II = 'II';
    case III = 'III';
    case IV = 'IV';
    case V = 'V';

    /** Plain values, e.g. for the migration's enum() column definition. */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
