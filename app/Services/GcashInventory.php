<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class GcashInventory
{
    public static bool $moving = false;

    public static function installed(): bool
    {
        $tables = Schema::getTableListing(schemaQualified: false);
        if (! array_intersect($tables, array_filter(array_keys(GcashSchema::TABLES), fn ($name) => str_starts_with($name, 'gcash_')))) {
            return false;
        }
        abort_if(GcashSchema::problems() !== [], 503, 'GCash schema installation is incomplete. Forward recovery is required before commerce mutations.');

        return true;
    }

    // Call only after taking the product row lock. Locking reads avoid stale MySQL snapshots.
    public static function blocks(Product $product): bool
    {
        if (! self::installed()) {
            return false;
        }

        return DB::table('gcash_stock_allocations')->where('product_id', $product->id)->whereIn('state', ['held', 'paid', 'transferred'])->lockForUpdate()->get(['id'])->isNotEmpty();
    }

    public static function assertEditable(Product $product): void
    {
        if (self::blocks($product)) {
            throw ValidationException::withMessages(['product' => 'This listing has a GCash stock allocation. Editing, replacing stock, archiving and deletion are unavailable until it is released or fulfilled. Add Stock remains available.']);
        }
    }

    public static function move(Product $product, int $quantity, ?string $group, ?string $value): void
    {
        if ($quantity > 0 && $product->availableStock($group, $value) < $quantity) {
            throw ValidationException::withMessages(['items' => 'Insufficient stock for this purchase.']);
        }
        if (!empty($product->variant_axes)) {
            if (!$product->combinationOption($group)) throw ValidationException::withMessages(['items' => 'The allocated variant no longer exists.']);
        } elseif (! empty($product->variations)) {
            $matches = 0;
            foreach ($product->variations as $g) {
                foreach ($g['options'] ?? [] as $o) {
                    if (($g['name'] ?? null) === $group && ($o['value'] ?? null) === $value) {
                        $matches++;
                    }
                }
            }
            if ($matches !== 1) {
                throw ValidationException::withMessages(['items' => 'The allocated variation no longer exists or is ambiguous. Manual reconciliation is required.']);
            }
        } elseif ($group !== null || $value !== null) {
            throw ValidationException::withMessages(['items' => 'The variation definition changed.']);
        }
        self::$moving = true;
        try {
            $product->deductStock($quantity, $group, $value);
        } finally {
            self::$moving = false;
        }
    }

    public static function voucherSlots(string $id): int
    {
        if (! Schema::hasTable('gcash_voucher_allocations')) {
            return 0;
        }

        return DB::table('gcash_voucher_allocations')->where('voucher_id', $id)->where('state', 'held')->lockForUpdate()->get(['id'])->count();
    }
}
