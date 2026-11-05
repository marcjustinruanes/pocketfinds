<?php

namespace App\Support;

class AuthCollage
{
    public const SLOTS = ['main' => 'Tall photo', 'back' => 'Discount photo', 'front' => 'Bottom photo'];

    public static function prefix(string $slot): string
    {
        abort_unless(isset(self::SLOTS[$slot]), 404);
        return $slot === 'main' ? 'auth_collage_image_' : 'auth_collage_'.$slot.'_image_';
    }

    public static function crop(array $crops, string $slot): array
    {
        return [
            'x' => max(0, min(100, (int) ($crops[$slot]['x'] ?? 50))),
            'y' => max(0, min(100, (int) ($crops[$slot]['y'] ?? ($slot === 'main' ? 65 : 50)))),
            'zoom' => max(100, min(200, (int) ($crops[$slot]['zoom'] ?? 100))),
        ];
    }

    public static function style(array $crops, string $slot): string
    {
        ['x' => $x, 'y' => $y, 'zoom' => $zoom] = self::crop($crops, $slot);
        $scale = $zoom / 100;
        return "object-fit: cover; object-position: {$x}% {$y}%; transform-origin: {$x}% {$y}%; transform: scale({$scale});";
    }
}
