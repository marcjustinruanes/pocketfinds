<?php

namespace App\Support;

class ProductColor
{
    public const PALETTE = [
        'Black' => '#000000', 'White' => '#ffffff', 'Red' => '#ff0000',
        'Blue' => '#0000ff', 'Green' => '#008000', 'Yellow' => '#ffff00',
        'Pink' => '#ffc0cb', 'Purple' => '#800080', 'Orange' => '#ffa500',
        'Gray' => '#808080', 'Grey' => '#808080', 'Beige' => '#f5f5dc',
        'Brown' => '#a52a2a', 'Navy' => '#000080', 'Cream' => '#fffdd0',
        'Light Blue' => '#add8e6', 'Dark Blue' => '#00008b',
        'Khaki' => '#c3b091', 'Army Green' => '#4b5320',
    ];

    public static function resolve(array $option): ?string
    {
        if (is_string($option['color'] ?? null) && preg_match('/^#[0-9a-f]{6}$/i', $option['color'])) {
            return strtolower($option['color']);
        }
        $value = strtolower(trim($option['value'] ?? ''));
        foreach (self::PALETTE as $name => $hex) {
            if ($value === strtolower($name) || str_starts_with($value, strtolower($name).' with ')) {
                return $hex;
            }
        }

        return null;
    }
}
