<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductSkuRegistry
{
    private static function entries(?string $base, array $legacy, array $matrix): array
    {
        $entries = [];
        if ($base !== null && trim($base) !== '') $entries[] = ['sku' => mb_strtolower(trim($base)), 'option_reference' => null, 'field' => 'sku'];
        foreach ($legacy as $group) foreach ($group['options'] ?? [] as $option) {
            if (!empty($option['sku'])) $entries[] = ['sku' => mb_strtolower(trim($option['sku'])), 'option_reference' => 'legacy:'.hash('sha256', ($group['name'] ?? '').':'.($option['value'] ?? '')), 'field' => 'variations'];
        }
        foreach ($matrix as $index => $row) {
            if (!empty($row['sku'])) $entries[] = ['sku' => mb_strtolower(trim($row['sku'])), 'option_reference' => $row['id'], 'field' => "variant_rows.{$index}.sku"];
        }
        return $entries;
    }

    public static function sync(Product $product): void
    {
        if (!Schema::hasTable('product_skus')) return;
        $entries = self::entries($product->sku, $product->variations ?? [], $product->variant_rows ?? []);
        $before = self::entries($product->getRawOriginal('sku'), json_decode($product->getRawOriginal('variations') ?? '[]', true) ?: [], json_decode($product->getRawOriginal('variant_rows') ?? '[]', true) ?: []);
        if (!$product->wasRecentlyCreated && $entries === $before) return;
        DB::table('product_skus')->where('product_id', $product->id)->delete();
        foreach ($entries as $entry) {
            $field = $entry['field']; unset($entry['field']);
            try { DB::table('product_skus')->insert($entry + ['product_id' => $product->id]); }
            catch (UniqueConstraintViolationException) { throw ValidationException::withMessages([$field => 'This SKU is already used by another product or variant.']); }
        }
    }
}
