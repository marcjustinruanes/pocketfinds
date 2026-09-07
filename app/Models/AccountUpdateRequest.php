<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A pending request to change one or more fields (requested_changes) and/or
 * re-upload one or more documents (requested_documents) on the requester's own
 * account. Nothing on the user row changes until an admin approves it — see
 * apply(). Used by every role (buyer, seller, rider, logistics); password
 * changes are never routed through this — those stay immediate/self-service.
 */
class AccountUpdateRequest extends Model
{
    protected $fillable = [
        'user_id', 'requested_changes', 'requested_documents',
        'status', 'reviewed_by', 'reviewed_at', 'note',
    ];

    protected $casts = [
        'requested_changes'   => 'array',
        'requested_documents' => 'array',
        'reviewed_at'         => 'datetime',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    /** All requested field changes and document paths, merged into one array for display/apply. */
    public function allChanges(): array
    {
        return array_merge($this->requested_changes ?? [], $this->requested_documents ?? []);
    }

    /** Writes every requested change onto the user's row. Call only after approving. */
    public function apply(): void
    {
        $data = $this->allChanges();
        if ($data) {
            $this->user->update($data);
            $this->syncBuyerDefaultAddress($data);
        }
    }

    /**
     * The buyer's default BuyerAddress can't be deleted or edited directly
     * (see BuyerController::destroyAddress()) — an approved profile/address
     * update request is the one legitimate way it changes. Keeps it in step
     * whenever the approved change touches a name/contact/address field.
     */
    private function syncBuyerDefaultAddress(array $data): void
    {
        if ($this->user->account_type !== 'buyer') {
            return;
        }

        $addressFields = ['province', 'municipality', 'barangay', 'house_no', 'street', 'given_names', 'last_name', 'contact_no'];
        if (!array_intersect(array_keys($data), $addressFields)) {
            return;
        }

        $buyer = $this->user->fresh();
        $default = BuyerAddress::where('buyer_id', $buyer->id)->where('is_default', true)->first();

        if (!$default) {
            BuyerAddress::ensureDefaultFor($buyer);
            return;
        }

        $updates = [];
        if (array_key_exists('given_names', $data) || array_key_exists('last_name', $data)) {
            $updates['recipient_name'] = trim($buyer->given_names . ' ' . $buyer->last_name);
        }
        foreach (['contact_no', 'province', 'municipality', 'barangay', 'house_no', 'street'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $buyer->$field;
            }
        }

        if ($updates) {
            $default->update($updates);
        }
    }
}
