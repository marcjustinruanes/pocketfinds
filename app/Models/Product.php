<?php

namespace App\Models;

use App\Services\GcashInventory;
use App\Services\GcashLocks;
use App\Services\GcashWorkflow;
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
    ];

    protected $casts = [
        'images' => 'array',
        'variations' => 'array',
        'details' => 'array',
        'restock_date' => 'date',
    ];

    public function getTotalStockAttribute(): int
    {
        if (! empty($this->variations)) {
            return collect($this->variations)->sum(fn ($v) => collect($v['options'] ?? [])->sum('stock'));
        }

        return (int) $this->stock;
    }

    /** Current available quantity for one specific variation option, or the whole product if it has none. */
    public function availableStock(?string $group = null, ?string $value = null): int
    {
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
        if (! Schema::hasTable('inventory_movements')) {
            return;
        }
        DB::table('inventory_movements')->insert([
            'product_id' => $this->id, 'stock_before' => $before, 'stock_after' => $this->total_stock,
            'delta' => $this->total_stock - $before, 'requested_delta' => $requestedDelta,
            'actor_id' => auth()->id(), 'reason' => request()->route()?->getName() ?? 'Application stock operation',
            'created_at' => now(),
        ]);
    }

    public function save(array $options = [])
    {
        if (! $this->exists || ! GcashInventory::installed() || GcashInventory::$moving) {
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
        if (! GcashInventory::installed()) {
            return parent::delete();
        }

        return DB::transaction(function () {
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            GcashInventory::assertEditable($locked);

            return parent::delete();
        }, 3);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
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
