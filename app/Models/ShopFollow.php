<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShopFollow extends Model
{
    // Generate UUIDs in the application so inserts also work on MySQL.
    protected $keyType      = 'string';
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = ['buyer_id', 'seller_id'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($model) => $model->id = $model->id ?: (string) Str::uuid());
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
