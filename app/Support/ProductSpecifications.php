<?php

namespace App\Support;

class ProductSpecifications
{
    public static function build(array $fields, ?array $custom = null): array
    {
        $details = [];
        $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        if ((float) ($fields['weight_grams'] ?? 0) > 0) {
            $weight = $number($fields['weight_grams']);
            if ((float) ($fields['weight_grams_max'] ?? 0) > (float) $fields['weight_grams']) {
                $weight .= ' - '.$number($fields['weight_grams_max']);
            }
            $details[] = ['label' => 'Weight', 'value' => $weight.' g'];
        }
        $dimensionKeys = (float) ($fields['length_cm'] ?? 0) > 0 ? ['length_cm', 'width_cm', 'height_cm'] : ['width_cm', 'height_cm'];
        $dimensions = array_map(fn ($key) => (float) ($fields[$key] ?? 0), $dimensionKeys);
        if (min($dimensions) > 0) {
            $details[] = ['label' => 'Dimensions', 'value' => implode(' × ', array_map($number, $dimensions)).' cm ('.(count($dimensions) === 3 ? 'L × W × H' : 'W × H').')'];
        }
        if (in_array($fields['condition'] ?? null, ['new', 'used'], true)) {
            $details[] = ['label' => 'Condition', 'value' => ucfirst($fields['condition'])];
        }
        foreach ($custom ?? [] as $row) {
            if (!is_array($row)) continue;
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label !== '' && $value !== '' && !in_array(strtolower($label), ['weight', 'dimensions', 'condition'], true)) {
                $details[] = ['label' => $label, 'value' => $value];
            }
        }
        return $details;
    }
}
