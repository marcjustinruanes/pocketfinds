<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'province_id', 'municipality_id', 'barangay_id', 'house_number', 'street', 'postal_code', 'landmark', 'is_default'];

    public function municipality() { return $this->belongsTo(Municipality::class); }
}
