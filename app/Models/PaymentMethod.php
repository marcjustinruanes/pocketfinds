<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    private const LEGACY_COD_NAME = 'cash on delivery';

    public function scopeForBuyerCheckout(Builder $query): Builder
    {
        return $query->where('is_active', true)->where(function (Builder $allowed) {
            $allowed->where(fn (Builder $cod) => $cod->where('type', 'cod')->whereRaw('LOWER(TRIM(name)) <> ?', ['gcash']))
                ->orWhere(fn (Builder $legacy) => $legacy->where('type', 'other')->whereRaw('LOWER(TRIM(name)) = ?', [self::LEGACY_COD_NAME]))
                ->orWhere(fn (Builder $gcash) => $gcash->where('type', 'ewallet')->whereRaw('LOWER(TRIM(name)) = ?', ['gcash']));
        });
    }

    public function buyerCheckoutKind(): ?string
    {
        if (! $this->is_active) {
            return null;
        }
        $name = strtolower(trim($this->name, ' '));
        if ($name === 'gcash') {
            return $this->type === 'ewallet' ? 'gcash' : null;
        }
        if ($this->type === 'cod' || ($this->type === 'other' && $name === self::LEGACY_COD_NAME)) {
            return 'cod';
        }

        return null;
    }

    protected $fillable = ['name', 'type', 'description', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
