<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierProfile extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
