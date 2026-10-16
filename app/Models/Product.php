<?php

namespace App\Models;

use App\Services\GcashInventory;
use App\Services\GcashLocks;
use App\Services\GcashWorkflow;
use App\Services\ProductSkuRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'seller_id', 'category_id', 'name', 'description',
        'image', 'images', 'video', 'variations', 'details', 'stock', 'price', 'discount_price', 'sku', 'status', 'rejection_note',
        'weight_grams', 'weight_grams_max', 'length_cm', 'width_cm', 'height_cm', 'condition', 'restock_date',
        'creation_key', 'variant_axes', 'variant_rows',
    ];

    protected $casts = [
        'images' => 'array',
        'variations' => 'array',
        'details' => 'array',
        'restock_date' => 'date',
        'variant_axes' => 'array', 'variant_rows' => 'array',
    ];

    public function getTotalStockAttribute(): int
    {
        if (!empty($this->variant_axes)) return (int) collect($this->variant_rows ?? [])->sum('stock');
        if (! empty($this->variations)) {
            return collect($this->variations)->sum(fn ($v) => collect($v['options'] ?? [])->sum('stock'));
        }

        return (int) $this->stock;
    }

    /** Current available quantity for one specific variation option, or the whole product if it has none. */
    public function availableStock(?string $group = null, ?string $value = null): int
    {
        if (!empty($this->variant_axes)) return (int) ($this->combinationOption($group)['stock'] ?? 0);
        if (empty($this->variations)) {
            return (int) $this->stock;
        }
        foreach ($this->variations as $variation) {
            if (($variation['name'] ?? null) !== $group) {
                continue;
            }
            foreach ($variation['options'] ?? [] as $option) {
                if (($option['value'] ?? null) === $value) {
                    return (int) ($option['stock'] ?? 0);
                }
            }
        }

        return 0;
    }

    public function combinationOption(?string $group): ?array
    {
        if (empty($this->variant_axes) || !str_starts_with($group ?? '', 'variant:')) return null;
        $row = collect($this->variant_rows ?? [])->firstWhere('id', substr($group, 8));
        if (!$row) return null;
        $first = collect($this->variant_axes[0]['options'])->firstWhere('id', $row['option_ids'][0] ?? null);
        return $row + ['value' => $row['label'], 'image' => $first['image'] ?? null];
    }

    /**
     * Moves stock by $qty (positive to deduct, e.g. at checkout; negative to
     * restore, e.g. when an order is cancelled) for one variation option, or
     * the whole product if it has none. Call this on a row locked with
     * lockForUpdate() inside a transaction — it's the only place stock ever
     * changes, and it must never go below zero even under a race.
     */
    public function deductStock(int $qty, ?string $group = null, ?string $value = null): void
    {
        $previous = GcashInventory::$moving;
        GcashInventory::$moving = true;
        try {
            if (!empty($this->variant_axes)) {
                $option = $this->combinationOption($group);
                if (!$option || ($qty > 0 && $option['stock'] < $qty)) throw ValidationException::withMessages(['stock' => 'Select an available variant with sufficient stock.']);
                $before = $this->total_stock; $rows = $this->variant_rows;
                foreach ($rows as &$row) if ($row['id'] === $option['id']) $row['stock'] = (int)$row['stock'] - $qty;
                unset($row);
                $this->update(['variant_rows' => $rows, 'stock' => collect($rows)->sum('stock')]);
                $this->recordStockMovement($before, -$qty);
                return;
            }
            if (GcashInventory::installed()) {
                if ($qty > 0 && $this->availableStock($group, $value) < $qty) {
                    throw ValidationException::withMessages(['stock' => 'Insufficient unallocated stock.']);
                }
                if (! empty($this->variations)) {
                    $matches = 0;
                    foreach ($this->variations as $g) {
                        foreach ($g['options'] ?? [] as $o) {
                            if (($g['name'] ?? null) === $group && ($o['value'] ?? null) === $value) {
                                $matches++;
                            }
                        }
                    }
                    if ($matches !== 1) {
                        throw ValidationException::withMessages(['stock' => 'Select one existing variation option.']);
                    }
                }
            }
            $before = $this->total_stock;
            if (empty($this->variations)) {
                $this->update(['stock' => max(0, (int) $this->stock - $qty)]);
                $this->recordStockMovement($before, -$qty);

                return;
            }
            $variations = $this->variations;
            foreach ($variations as &$variation) {
                if (($variation['name'] ?? null) !== $group) {
                    continue;
                }
                foreach ($variation['options'] as &$option) {
                    if (($option['value'] ?? null) === $value) {
                        $option['stock'] = max(0, (int) ($option['stock'] ?? 0) - $qty);
                    }
                }
                unset($option);
            }
            unset($variation);
            $this->update(['variations' => $variations]);
            $this->recordStockMovement($before, -$qty);

        } finally {
            GcashInventory::$moving = $previous;
        }
    }

    private function recordStockMovement(int $before, int $requestedDelta): void
    {
        // Old installations keep working until they apply the additive migration.
        if (Schema::hasTable('inventory_movements')) {
            DB::table('inventory_movements')->insert([
                'product_id' => $this->id, 'stock_before' => $before, 'stock_after' => $this->total_stock,
                'delta' => $this->total_stock - $before, 'requested_delta' => $requestedDelta,
                'actor_id' => auth()->id(), 'reason' => request()->route()?->getName() ?? 'Application stock operation',
                'created_at' => now(),
            ]);
        }
        if ($this->seller_id && $before > 5 && $this->total_stock <= 5 && Schema::hasTable('notifications')) {
            Order::notifyUser($this->seller_id, 'Low stock', $this->name.' has only '.$this->total_stock.' unit(s) left.', 'low_stock', $this->id);
        }
    }

    public function save(array $options = [])
    {
        if (! $this->exists || ! GcashInventory::installed() || GcashInventory::$moving) {
            if (($this->isDirty(['sku', 'variations', 'variant_rows']) || (!$this->exists && $this->sku)) && Schema::hasTable('product_skus')) {
                return DB::transaction(fn () => parent::save($options), 3);
            }
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            if ($this->isDirty('status') && $this->status === 'rejected') {
                GcashLocks::rejection($this->id);
            }
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if ($this->isDirty() && $this->getDirty() !== []) {
                if ($this->isDirty('status') && $this->status === 'rejected' && ! array_diff(array_keys($this->getDirty()), ['status', 'rejection_note', 'updated_at'])) {
                    app(GcashWorkflow::class)->productRejected($locked);
                } elseif ($this->isDirty(array_keys($this->getDirty()))) {
                    GcashInventory::assertEditable($locked);
                }
            }

            return parent::save($options);
        }, 3);
    }

    public function delete()
    {
        return DB::transaction(function () {
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if ($locked->hasUnfinishedOrders()) {
                throw ValidationException::withMessages(['product' => 'This product has unfinished orders and cannot be deleted.']);
            }
            if (GcashInventory::installed()) {
                GcashInventory::assertEditable($locked);
            }
            $locked->storeCategories()->detach();
            if (Schema::hasTable('product_skus')) DB::table('product_skus')->where('product_id', $this->id)->delete();

            return parent::delete();
        }, 3);
    }

    public function hasUnfinishedOrders(): bool
    {
        return Order::where('seller_id', $this->seller_id)->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
            ->lockForUpdate()->get(['items'])->contains(fn ($order) => collect($order->items ?? [])->contains(fn ($item) => ($item['product_id'] ?? $item['id'] ?? null) === $this->id));
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
        static::saved(fn (Product $product) => ProductSkuRegistry::sync($product));
        static::updated(function (Product $product) {
            if (GcashInventory::$moving || ! $product->wasChanged(['stock', 'variations', 'variant_rows'])) {
                return;
            }
            $previous = new Product(['stock' => $product->getOriginal('stock'), 'variations' => $product->getOriginal('variations')]);
            if ($previous->total_stock !== $product->total_stock) {
                $product->recordStockMovement($previous->total_stock, $product->total_stock - $previous->total_stock);
            }
        });
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function storeCategories()
    {
        return $this->belongsToMany(StoreCategory::class, 'store_category_products');
    }

    /**
     * Buyer/guest-facing listings only — a suspended (or rejected/pending) seller's
     * products disappear from browsing automatically, with no need to touch the
     * products themselves. They reappear on their own the moment the seller is
     * reactivated, since this just checks the seller's live status on every query.
     */
    public function scopeSellerApproved($query)
    {
        return $query->whereHas('seller', fn ($q) => $q->where('status', 'approved'));
    }
}
