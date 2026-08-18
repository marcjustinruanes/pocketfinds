<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $casts = ['selected_variations' => 'array'];

    public function order()  { return $this->belongsTo(Order::class, 'order_id'); }
    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
}
