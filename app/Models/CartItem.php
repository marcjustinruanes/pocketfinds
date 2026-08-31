<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CartItem extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'buyer_id', 'product_id', 'seller_slug', 'seller', 'name', 'img',
        'price', 'qty', 'variation_value', 'variation_group',
    ];

    protected $casts = [
        'price' => 'float',
        'qty'   => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
