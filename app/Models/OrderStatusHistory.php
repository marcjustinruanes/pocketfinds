<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    public function order() { return $this->belongsTo(Order::class, 'order_id'); }
}
