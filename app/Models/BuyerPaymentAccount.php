<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BuyerPaymentAccount extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'buyer_id', 'type', 'account_name', 'account_number', 'bank_name',
        'verified', 'verified_at',
    ];

    protected $casts = [
        'verified'    => 'boolean',
        'verified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
}
