<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Combination SKUs use the existing group/value inventory contract. */
class ProductVariationSet
{
    /** Package defaults for fields already supplied by sellable variations. */
    public static function packageFields(array $variations): array
    {
        $weights = $widths = $heights = $conditions = [];
        $collect = function ($name, $value) use (&$weights, &$widths, &$heights, &$conditions): void {
            if (!is_scalar($name) || !is_scalar($value)) return;
            $name = strtolower(trim((string) $name));
            $value = trim((string) $value);
            if ($value === '' || $value === 'Not applicable') return;
            if (preg_match('/^(weights?|grams?)$/', $name)) {
                if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*(?:[-–]\s*(\d+(?:\.\d+)?))?\s*(mg|g|kg|oz|lb)?\s*$/iu', $value, $match)) return;
                $factor = ['mg'=>.001, 'g'=>1, 'kg'=>1000, 'oz'=>28.349523125, 'lb'=>453.59237][strtolower($match[3] ?? 'g')];
                $min = (float) $match[1] * $factor;
                $max = (float) (($match[2] ?? '') !== '' ? $match[2] : $match[1]) * $factor;
                if ($min > 0 && $max >= $min) $weights[] = $max;
            } elseif (preg_match('/^dimensions?$/', $name)) {
                if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)(?:\s*[x×]\s*(\d+(?:\.\d+)?))?\s*(?:cm)?\s*$/iu', $value, $match)) {
                    $values = array_map('floatval', array_slice($match, 1));
                    if (min($values) > 0) {
                        $widths[] = $values[count($values)-2];
                        $heights[] = $values[count($values)-1];
                    }
                }
            } elseif (preg_match('/^(width|height)(?:\s*\(cm\))?$/', $name)) {
                $number = preg_replace('/\s*cm$/i', '', $value);
                if (is_numeric($number) && (float) $number > 0) {
                    if (str_starts_with($name, 'width')) $widths[] = (float) $number;
                    else $heights[] = (float) $number;
                }
            } elseif ($name === 'condition' && in_array(strtolower($value), ['new','used'], true)) {
                $conditions[] = strtolower($value);
            }
        };
        foreach ($variations as $group) {
            if (!is_array($group) || !is_array($group['options'] ?? null)) continue;
            foreach (($group['options'] ?? []) as $option) {
                if (!is_array($option)) continue;
                $attributes = is_array($option['attributes'] ?? null) ? $option['attributes'] : [];
                foreach ($attributes as $name => $value) $collect($name, $value);
                if (($group['type'] ?? '') === 'combinations') {
                    if (!is_array($group['axes'] ?? null)) continue;
                    foreach (($group['axes'] ?? []) as $axis) {
                        if (!is_array($axis) || !is_string($axis['name'] ?? null) || !is_array($axis['options'] ?? null)) continue;
                        foreach (($axis['options'] ?? []) as $axisOption) {
                            if (!is_array($axisOption)) continue;
                            if (($axisOption['value'] ?? null) === ($attributes[$axis['name']] ?? null) && isset($axisOption['variation_name'])) {
                                $collect($axisOption['variation_name'], $axisOption['option_value'] ?? '');
                            }
                        }
                    }
                } else $collect($option['variation_name'] ?? $group['name'] ?? '', $option['option_value'] ?? $option['value'] ?? '');
            }
        }
        $result = [];
        // Checkout reads the product weight; use the largest variant rather than underestimating it.
        if ($weights) { $result['weight_grams'] = max(1, (int) ceil(max($weights))); $result['weight_grams_max'] = null; }
        if ($widths) $result['width_cm'] = max($widths);
        if ($heights) $result['height_cm'] = max($heights);
        if ($conditions) $result['condition'] = in_array('used', $conditions, true) ? 'used' : 'new';
        return $result;
    }

    public static function hasColorAndSize(?array $variations): bool
    {
        $names = array_column($variations ?? [], 'name');

        return (bool) preg_grep('/^colou?rs?$/i', $names) && (bool) preg_grep('/^sizes?$/i', $names);
    }

    /** Old separate totals cannot determine the quantity of any particular pair. */
    public static function preparePairStock(array $variations): array
    {
        $axes = $variations;
        $rows = [['attributes' => [], 'values' => []]];
        foreach ($axes as &$axis) {
            foreach ($axis['options'] as &$option) {
                unset($option['stock'], $option['price']);
                if (preg_match('/^colou?rs?$/i', $axis['name'])) {
                    $color = ProductColor::resolve($option);
                    if ($color) $option['color'] = $color;
                }
            }
            unset($option);
            $next = [];
            foreach ($rows as $row) {
                foreach ($axis['options'] as $option) {
                    $attributes = $row['attributes'];
                    $attributes[$axis['name']] = $option['value'];
                    $next[] = ['attributes' => $attributes, 'values' => [...$row['values'], $option['value']]];
                }
            }
            $rows = $next;
        }
        unset($axis);
        $options = array_map(function ($row) use ($axes) {
            $sku = ['value' => implode(' / ', $row['values']), 'attributes' => $row['attributes'], 'stock' => 0];
            foreach ($axes as $axis) {
                if (! preg_match('/^colou?rs?$/i', $axis['name'])) continue;
                foreach ($axis['options'] as $option) {
                    if ($option['value'] === $row['attributes'][$axis['name']] && ! empty($option['image'])) $sku['image'] = $option['image'];
                }
            }

            return $sku;
        }, $rows);
        $result = [['name' => 'Options', 'type' => 'combinations', 'requires_stock_setup' => true, 'axes' => $axes, 'options' => $options]];
        self::validate($result);

        return $result;
    }

    public static function combination(?array $variations): ?array
    {
        foreach ($variations ?? [] as $group) {
            if (($group['type'] ?? null) === 'combinations') {
                return $group;
            }
        }

        return null;
    }

    public static function displayGroups(?array $variations): array
    {
        $combination = self::combination($variations);
        if (! $combination) {
            return $variations ?? [];
        }

        $axes = $combination['axes'];
        if (!empty($combination['selective'])) {
            usort($axes, fn ($a, $b) => (int) preg_match('/^colou?rs?$/i', $b['name']) <=> (int) preg_match('/^colou?rs?$/i', $a['name']));
        }
        return array_map(function ($axis) use ($combination) {
            foreach ($axis['options'] as &$option) {
                $option['stock'] = array_sum(array_map(
                    fn ($sku) => ($sku['attributes'][$axis['name']] ?? null) === $option['value'] ? (int) $sku['stock'] : 0,
                    $combination['options'],
                ));
            }

            return $axis;
        }, $axes);
    }

    public static function validate(?array $variations): void
    {
        $group = self::combination($variations);
        if (! $group) {
            if (count($variations ?? []) > 1) {
                throw ValidationException::withMessages(['variations' => 'Enter stock for each variation combination. A single variation can keep stock for each option.']);
            }
            return;
        }

        Validator::make(['variations' => $variations], [
            'variations' => 'required|array|size:1',
            'variations.0.name' => 'required|string|max:150',
            'variations.0.selective' => 'sometimes|boolean',
            'variations.0.axes' => 'required|array|min:2|max:4',
            'variations.0.axes.*.name' => 'required|string|max:50|distinct',
            'variations.0.axes.*.options' => 'required|array|min:1|max:20',
            'variations.0.axes.*.options.*.value' => 'required|string|max:100',
            'variations.0.axes.*.options.*.stock' => 'prohibited',
            'variations.0.axes.*.options.*.price' => 'prohibited',
            'variations.0.options' => 'required|array|min:1|max:500',
            'variations.0.options.*.value' => 'required|string|max:150|distinct',
            'variations.0.options.*.attributes' => 'required|array',
            'variations.0.options.*.stock' => 'required|integer|min:0',
            'variations.0.options.*.price' => 'nullable|numeric|min:0',
        ])->validate();

        $expectedCount = 1;
        $axes = [];
        foreach ($group['axes'] as $axis) {
            $values = array_column($axis['options'], 'value');
            if (count($values) !== count(array_unique($values))) {
                throw ValidationException::withMessages(['variations' => 'Each option in a variation must have a different name.']);
            }
            $axes[$axis['name']] = $values;
            $expectedCount *= count($values);
        }

        $seen = [];
        foreach ($group['options'] as $option) {
            $ordered = [];
            foreach ($axes as $name => $values) {
                $value = $option['attributes'][$name] ?? null;
                if (! is_string($value) || ! in_array($value, $values, true)) {
                    throw ValidationException::withMessages(['variations' => 'A combination contains an unknown option.']);
                }
                $ordered[] = $value;
            }
            $key = json_encode($ordered);
            if (count($option['attributes']) !== count($axes) || isset($seen[$key]) || $option['value'] !== implode(' / ', $ordered)) {
                throw ValidationException::withMessages(['variations' => 'Each variation combination must have one unique inventory entry.']);
            }
            $seen[$key] = true;
        }

        if (empty($group['selective']) && count($seen) !== $expectedCount) {
            throw ValidationException::withMessages(['variations' => 'Enter stock for every combination, using 0 for unavailable combinations.']);
        }
    }
}
