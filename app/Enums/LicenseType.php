<?php

namespace App\Enums;

enum LicenseType: string
{
    case Professional = 'professional';
    case NonProfessional = 'non_professional';

    public function label(): string
    {
        return match ($this) { self::Professional => 'Professional', self::NonProfessional => 'Non-professional' };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
