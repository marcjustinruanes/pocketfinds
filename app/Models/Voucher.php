<?php

namespace App\Models;

use App\Services\GcashInventory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Voucher extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'seller_id', 'code', 'type', 'discount_amount', 'minimum_spend',
        'usage_limit', 'used_count', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count + GcashInventory::voucherSlots($this->id) >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function save(array $options = [])
    {
        if (! $this->exists || ! GcashInventory::installed()) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            // Deactivation affects new purchases only. Never change an allocated discount or its usage limit.
            if ($this->isDirty(['code', 'type', 'discount_amount', 'minimum_spend', 'usage_limit', 'expires_at', 'seller_id']) && GcashInventory::voucherSlots($this->id) > 0) {
                throw ValidationException::withMessages(['voucher' => 'This voucher has active GCash allocations. Its terms cannot be changed.']);
            }

            return parent::save($options);
        }, 3);
    }

    public function delete()
    {
        if (! GcashInventory::installed()) {
            return parent::delete();
        }

        return DB::transaction(function () {
            static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if (GcashInventory::voucherSlots($this->id) > 0) {
                throw ValidationException::withMessages(['voucher' => 'This voucher has active GCash allocations and cannot be deleted. Deactivation prevents new allocations.']);
            }

            return parent::delete();
        }, 3);
    }

    public function isFreeShipping(): bool
    {
        return $this->type === 'free_shipping';
    }
}
