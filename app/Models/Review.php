<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Review extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = [
        'id', 'order_id', 'buyer_id', 'seller_id', 'product_id',
        'rating', 'comment', 'seller_reply',
        'item_key', 'photos', 'edited_at',
    ];

    protected $casts = ['photos'=>'array', 'edited_at'=>'datetime'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function($m){$m->id=$m->id?:(string)Str::uuid();$m->created_at=$m->created_at?:now();});
    }

    public function order()   { return $this->belongsTo(Order::class); }
    public function buyer()   { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller()  { return $this->belongsTo(User::class, 'seller_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
