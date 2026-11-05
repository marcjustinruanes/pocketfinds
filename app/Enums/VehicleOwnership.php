<?php

namespace App\Enums;

enum VehicleOwnership: string
{
    case Own = 'own';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) { self::Own => 'Own vehicle', self::Company => 'Company vehicle' };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
