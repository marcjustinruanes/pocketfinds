<?php

namespace App\Enums;

enum DriverLicenseCode: string
{
    case A = 'A';
    case A1 = 'A1';
    case B = 'B';
    case B1 = 'B1';
    case B2 = 'B2';
    case C = 'C';
    case D = 'D';
    case BE = 'BE';
    case CE = 'CE';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Motorcycle', self::A1 => 'Tricycle / three-wheel vehicle',
            self::B => 'Passenger car', self::B1 => 'Passenger van',
            self::B2 => 'Light goods vehicle / cargo van', self::C => 'Heavy goods vehicle',
            self::D => 'Bus', self::BE => 'Light vehicle with trailer', self::CE => 'Heavy vehicle with trailer',
        };
    }

    public function requiresProfessionalLicense(): bool
    {
        return in_array($this, [self::C, self::D, self::BE, self::CE], true);
    }

    /** Vehicle categories supported by the existing rider vehicle types. */
    public function vehicleTypes(): array
    {
        return match ($this) { self::A => ['two_wheels'], self::B, self::B1, self::B2 => ['four_wheels'], default => [] };
    }

    public static function vehicleCategories(): array
    {
        return array_values(array_filter(self::cases(), fn (self $code) => $code->vehicleTypes() !== []));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
