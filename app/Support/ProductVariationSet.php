<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Combination SKUs use the existing group/value inventory contract. */
class ProductVariationSet
{
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

        return array_map(function ($axis) use ($combination) {
            foreach ($axis['options'] as &$option) {
                $option['stock'] = array_sum(array_map(
                    fn ($sku) => ($sku['attributes'][$axis['name']] ?? null) === $option['value'] ? (int) $sku['stock'] : 0,
                    $combination['options'],
                ));
            }

            return $axis;
        }, $combination['axes']);
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
            'variations.0.axes' => 'required|array|min:2|max:4',
            'variations.0.axes.*.name' => 'required|string|max:50|distinct',
            'variations.0.axes.*.options' => 'required|array|min:1|max:100',
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

        if (count($seen) !== $expectedCount) {
            throw ValidationException::withMessages(['variations' => 'Enter stock for every combination, using 0 for unavailable combinations.']);
        }
    }
}
