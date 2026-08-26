<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['name', 'description', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}