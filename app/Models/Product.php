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
        'image', 'variations', 'details', 'stock', 'price', 'sku', 'status', 'rejection_note',
    ];

    protected $casts = [
        'variations' => 'array',
        'details'    => 'array',
    ];

    public function getTotalStockAttribute(): int
    {
        if (!empty($this->variations)) {
            return collect($this->variations)->sum(fn($v) => collect($v['options'] ?? [])->sum('stock'));
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
    public function images()   { return $this->hasMany(ProductImage::class); }
}
