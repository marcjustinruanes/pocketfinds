<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $keyType   = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'id', 'seller_id', 'category_id', 'name', 'description',
        'image', 'images', 'video', 'variations', 'details', 'stock', 'price', 'discount_price', 'sku', 'status', 'rejection_note',
        'weight_grams', 'weight_grams_max', 'length_cm', 'width_cm', 'height_cm', 'condition', 'restock_date',
    ];

    protected $casts = [
        'images'     => 'array',
        'variations' => 'array',
        'details'    => 'array',
        'restock_date' => 'date',
    ];

    public function getTotalStockAttribute(): int
    {
        if (!empty($this->variations)) {
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
            if (($variation['name'] ?? null) !== $group) continue;
            foreach ($variation['options'] ?? [] as $option) {
                if (($option['value'] ?? null) === $value) return (int) ($option['stock'] ?? 0);
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
        if (empty($this->variations)) {
            $this->update(['stock' => max(0, (int) $this->stock - $qty)]);
            return;
        }
        $variations = $this->variations;
        foreach ($variations as &$variation) {
            if (($variation['name'] ?? null) !== $group) continue;
            foreach ($variation['options'] as &$option) {
                if (($option['value'] ?? null) === $value) {
                    $option['stock'] = max(0, (int) ($option['stock'] ?? 0) - $qty);
                }
            }
            unset($option);
        }
        unset($variation);
        $this->update(['variations' => $variations]);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    public function seller()   { return $this->belongsTo(User::class, 'seller_id'); }
    public function category() { return $this->belongsTo(Category::class); }

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
