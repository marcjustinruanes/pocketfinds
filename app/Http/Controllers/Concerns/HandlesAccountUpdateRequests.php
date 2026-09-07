<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AccountUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared submit/pending-check logic for the "Update Request" system: every role's
 * profile/address/shop/document edits are submitted here instead of being applied
 * immediately — the change only lands on the user's row once an admin approves it
 * (AccountUpdateRequest::apply()). A user has at most one pending request at a
 * time; submitting again while one is pending merges into it rather than
 * replacing it, so several forms (profile, address, shop…) can each add their own
 * piece before admin review. Password changes never go through this — those stay
 * immediate/self-service in each controller.
 */
trait HandlesAccountUpdateRequests
{
    private function pendingAccountUpdateRequest(): ?AccountUpdateRequest
    {
        return AccountUpdateRequest::where('user_id', auth()->id())->where('status', 'pending')->latest()->first();
    }

    private function lastAccountUpdateRequest(): ?AccountUpdateRequest
    {
        return AccountUpdateRequest::where('user_id', auth()->id())->latest()->first();
    }

    /**
     * Submits (or merges into the existing pending) a request covering the given
     * plain fields (from $request input) and files (key => storage disk).
     */
    private function submitAccountUpdateRequest(Request $request, array $fieldKeys, array $fileKeys = [], string $redirectKey = 'update_request_success'): RedirectResponse
    {
        $changes = [];
        foreach ($fieldKeys as $key) {
            if ($request->has($key)) {
                $changes[$key] = $request->input($key);
            }
        }

        $documents = [];
        foreach ($fileKeys as $key => $disk) {
            if ($request->hasFile($key)) {
                $documents[$key] = $request->file($key)->store($key . 's', $disk);
            }
        }

        if (!$changes && !$documents) {
            return back()->with('update_request_error', 'Nothing to update.');
        }

        $existing = $this->pendingAccountUpdateRequest();
        if ($existing) {
            $existing->update([
                'requested_changes'   => array_merge($existing->requested_changes ?? [], $changes),
                'requested_documents' => array_merge($existing->requested_documents ?? [], $documents),
            ]);
        } else {
            AccountUpdateRequest::create([
                'user_id'              => auth()->id(),
                'requested_changes'    => $changes ?: null,
                'requested_documents'  => $documents ?: null,
                'status'               => 'pending',
            ]);
        }

        return back()->with($redirectKey, 'Your changes were submitted and are pending admin approval.');
    }
}
