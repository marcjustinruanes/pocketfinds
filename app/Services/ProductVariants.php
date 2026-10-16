<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductVariants
{
    public function read(Request $request, ?Product $product = null): ?array
    {
        if (!$request->has('variant_axes')) return null;
        $request->validate(['variant_axes' => ['nullable', 'string', 'json'], 'variant_rows' => ['nullable', 'string', 'json'],
            'matrix_images' => ['nullable', 'array'], 'matrix_images.*' => ['image', 'mimes:jpg,jpeg,png', 'max:5120']]);
        $axes = json_decode($request->input('variant_axes') ?: '[]', true);
        $rows = json_decode($request->input('variant_rows') ?: '[]', true);
        if (!$axes && $rows) throw ValidationException::withMessages(['variant_axes' => 'Add variation types before defining combinations.']);
        $data = ['variant_axes' => $axes, 'variant_rows' => $rows];
        Validator::make($data, ['variant_axes' => ['array', 'max:2'], 'variant_axes.*.id' => ['required', 'uuid', 'distinct'],
            'variant_axes.*.name' => ['required', 'string', 'max:50'], 'variant_axes.*.options' => ['required', 'array', 'min:1', 'max:20'],
            'variant_axes.*.options.*.id' => ['required', 'uuid', 'distinct'], 'variant_axes.*.options.*.value' => ['required', 'string', 'max:50'],
            'variant_rows' => ['array', Rule::when(count((array)$axes) > 0, ['min:1']), 'max:400'],
            'variant_rows.*.id' => ['required', 'uuid', 'distinct'], 'variant_rows.*.option_ids' => ['required', 'array'],
            'variant_rows.*.price' => ['required', 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
            'variant_rows.*.stock' => ['required', 'integer', 'min:0', 'max:99999'], 'variant_rows.*.sku' => ['required', 'string', 'max:100'],
        ])->validate();
        $errors = []; $axisNames = []; $skus = []; $combinations = [];
        $baseSku = $request->input('sku', $product?->sku);
        if ($baseSku) $skus[mb_strtolower(trim($baseSku))] = true;
        foreach ($axes as $i => &$axis) {
            $axis['name'] = trim($axis['name']); $key = mb_strtolower($axis['name']);
            if (isset($axisNames[$key])) $errors["variant_axes.{$i}.name"] = 'Variation names must be distinct.';
            $axisNames[$key] = true; $values = [];
            foreach ($axis['options'] as $j => &$option) {
                $option['value'] = trim($option['value']); $key = mb_strtolower($option['value']);
                if (!$option['value']) $errors["variant_axes.{$i}.options.{$j}.value"] = 'An option name is required.';
                if (isset($values[$key])) $errors["variant_axes.{$i}.options.{$j}.value"] = 'Option names must be distinct, ignoring case.';
                $values[$key] = true;
            } unset($option);
        } unset($axis);
        foreach ($rows as $i => &$row) {
            $labels = [];
            foreach ($axes as $axisIndex => $axis) {
                $option = collect($axis['options'])->firstWhere('id', $row['option_ids'][$axisIndex] ?? null);
                if (!$option || count($row['option_ids']) !== count($axes)) $errors["variant_rows.{$i}.option_ids"] = 'Choose one current option from every variation.';
                else $labels[] = $option['value'];
            }
            $key = implode('|', $row['option_ids']);
            if (isset($combinations[$key])) $errors["variant_rows.{$i}.option_ids"] = 'This combination occurs more than once.';
            $combinations[$key] = true; $row['label'] = implode('-', $labels);
            $row['price'] = number_format((float)$row['price'], 2, '.', ''); $row['stock'] = (int)$row['stock'];
            $row['sku'] = trim($row['sku']); $sku = mb_strtolower($row['sku']);
            if (!$sku || isset($skus[$sku]) || DB::table('product_skus')->where('sku', $sku)->when($product, fn ($query) => $query->where('product_id', '!=', $product->id))->exists()) {
                $errors["variant_rows.{$i}.sku"] = 'Every variant requires its own globally unique SKU.';
            }
            $skus[$sku] = true;
            $row = collect($row)->only(['id', 'option_ids', 'label', 'price', 'stock', 'sku'])->all();
        } unset($row);
        if ($errors) throw ValidationException::withMessages($errors);
        if ($product) {
            $removed = collect($product->variant_rows ?? [])->whereNotIn('id', array_column($rows, 'id'));
            foreach ($removed as $row) {
                $blocked = Order::where('seller_id', $product->seller_id)->whereNotIn('status', ['completed', 'cancelled', 'refunded'])->lockForUpdate()->get(['items'])
                    ->contains(fn ($order) => collect($order->items ?? [])->contains(fn ($item) => ($item['product_id'] ?? null) === $product->id && ($item['variation_group'] ?? null) === 'variant:'.$row['id']));
                if ($blocked) throw ValidationException::withMessages(['variant_rows' => 'Cannot delete '.$row['label'].': this variant has unfinished orders.']);
            }
            if (empty($product->variant_axes) && !empty($product->variations) && $product->hasUnfinishedOrders()) {
                throw ValidationException::withMessages(['variant_axes' => 'Fulfill existing variation orders before converting this listing to combinations.']);
            }
        }
        $oldImages = collect($product?->variant_axes ?? [])->flatMap(fn ($axis) => $axis['options'])->pluck('image')->filter()->all();
        foreach ($axes as &$axis) foreach ($axis['options'] as &$option) {
            $image = $option['image'] ?? null;
            if ($image && !in_array($image, $oldImages, true)) throw ValidationException::withMessages(['matrix_images' => 'Use an existing image owned by this product or upload a new one.']);
            if ($request->hasFile('matrix_images.'.$option['id'])) $image = app(MediaLifecycle::class)->store($request->file('matrix_images.'.$option['id']), 'product_images', 'public');
            $option = collect($option)->only(['id', 'value'])->all() + ['image' => $image];
        } unset($axis, $option);
        return ['variant_axes' => $axes ?: null, 'variant_rows' => $rows ?: null];
    }
}
