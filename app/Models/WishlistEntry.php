<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WishlistEntry extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['buyer_id', 'product_id', 'product_name', 'image', 'price'];

    protected static function booted(): void
    {
        static::creating(fn ($entry) => $entry->id ??= (string) Str::uuid());
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
