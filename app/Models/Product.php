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

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    public function seller()   { return $this->belongsTo(User::class, 'seller_id'); }
    public function category() { return $this->belongsTo(Category::class); }
}
