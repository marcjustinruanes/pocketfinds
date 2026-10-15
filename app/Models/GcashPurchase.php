<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class GcashPurchase extends Model
{
    public function productLabel(): string
    {
        $items = $this->snapshot['items'] ?? [];

        return ($items[0]['name'] ?? 'GCash purchase').(count($items) > 1 ? ' + '.(count($items) - 1).' more' : '');
    }

    protected static function booted(): void
    {
        static::updating(function ($p) {
            if ($p->isDirty('payment_reference') && $p->getOriginal('payment_reference') !== null) {
                throw ValidationException::withMessages(['gcash' => 'The recorded payment reference is immutable.']);
            }
            if ($p->isDirty(['buyer_id', 'seller_id', 'checkout_id', 'request_hash', 'payment_method_id', 'snapshot', 'amount_due'])) {
                throw ValidationException::withMessages(['gcash' => 'Purchase snapshots and ownership are immutable.']);
            }
        });
    }

    protected $attributes = ['generation' => 1, 'revision' => 0];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['revision' => 'integer', 'generation' => 'integer', 'snapshot' => 'array', 'instructions' => 'array', 'amount_due' => 'decimal:2', 'verified_amount' => 'decimal:2', 'activated_at' => 'datetime', 'expires_at' => 'datetime', 'verified_at' => 'datetime'];
}
