<?php

namespace App\Enums;

enum SellerType: string
{
    case Individual = 'individual';
    case SoleProprietorship = 'sole_proprietorship';
    case Corporation = 'corporation';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::SoleProprietorship => 'Sole Proprietorship',
            self::Corporation => 'Corporation',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
