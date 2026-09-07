<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuyerAddress extends Model
{
    protected $fillable = [
        'buyer_id', 'label', 'recipient_name', 'contact_no',
        'province', 'municipality', 'barangay', 'house_no', 'street', 'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }

    /**
     * Seeds a buyer's very first saved address from their registration address the
     * first time they ever need one — a buyer who registered before this feature
     * existed gets one exactly the same way as a brand-new one. No-ops once they
     * have at least one saved address, or if their profile has no address at all.
     */
    public static function ensureDefaultFor(User $buyer): void
    {
        if (static::where('buyer_id', $buyer->id)->exists()) return;
        if (!$buyer->province || !$buyer->municipality || !$buyer->barangay) return;

        static::create([
            'buyer_id'       => $buyer->id,
            'label'          => 'Home',
            'recipient_name' => trim($buyer->given_names . ' ' . $buyer->last_name),
            'contact_no'     => $buyer->contact_no,
            'province'       => $buyer->province,
            'municipality'   => $buyer->municipality,
            'barangay'       => $buyer->barangay,
            'house_no'       => $buyer->house_no,
            'street'         => $buyer->street,
            'is_default'     => true,
        ]);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->house_no, $this->street, $this->barangay, $this->municipality, $this->province])
            ->filter()->join(', ');
    }

    /** The shape Order::shipping_address expects — used at checkout when this address is chosen. */
    public function toShippingArray(): array
    {
        return [
            'house_no'       => $this->house_no ?: 'Not provided',
            'street'         => $this->street ?: 'Not provided',
            'barangay'       => $this->barangay,
            'municipality'   => $this->municipality,
            'province'       => $this->province,
            'recipient_name' => $this->recipient_name,
            'contact_no'     => $this->contact_no,
        ];
    }
}
