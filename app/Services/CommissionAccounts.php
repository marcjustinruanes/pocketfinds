<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\{Crypt, DB, Schema, Validator};
use Illuminate\Support\Str;

class CommissionAccounts
{
    public function installed(): bool
    {
        return Schema::hasTable('commission_account_settings');
    }

    public function details(object $account): object
    {
        $account = clone $account;
        if (!empty($account->encrypted_details)) {
            $details = json_decode(Crypt::decryptString($account->encrypted_details), true, flags: JSON_THROW_ON_ERROR);
            $account->account_name = $details['account_name'];
            $account->account_number = $details['account_number'];
        }
        unset($account->encrypted_details);
        return $account;
    }

    public function settings(bool $lock = false): ?object
    {
        if (!$this->installed()) return null;
        $query = DB::table('commission_account_settings')->where('id', 1);
        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    public function requireActive(string $id): void
    {
        $settings = $this->settings(true);
        if (!$settings || !$settings->active_account_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'receiving_account_id' => 'Commission payments are unavailable: Platform Admin has not activated a GCash receiving account. Do not send money.',
            ]);
        }
        if ($settings->active_account_id !== $id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'receiving_account_id' => 'The GCash receiving account changed. Refresh the page before sending money. For money already sent, use payment recovery.',
            ]);
        }
    }

    public function manage(User $user, array $input): void
    {
        $actor = app(CommissionPayments::class)->admin($user);
        abort_unless($this->installed(), 503, 'Commission account management installation is required.');
        // Retain the existing internal publication API used by current integrations.
        $legacy = !isset($input['action']);
        $input['action'] ??= 'publish';
        $input['reason'] ??= $legacy ? 'Receiving instructions published' : null;
        $d = Validator::make($input, [
            'action' => 'required|in:publish,create,edit,activate,deactivate',
            'revision' => ($legacy ? 'nullable' : 'required').'|integer|min:1',
            'account_id' => 'required_if:action,edit,activate,deactivate|nullable|uuid',
            'account_name' => 'required_if:action,publish,create,edit|nullable|string|max:150',
            'account_number' => ['required_if:action,publish,create,edit', 'nullable', 'regex:/^09[0-9]{9}$/D'],
            'reason' => 'required|string|min:3|max:1000',
        ])->validate();
        DB::transaction(function () use ($actor, $d) {
            $settings = $this->settings(true);
            abort_unless($settings, 503);
            if (isset($d['revision'])) abort_unless((int) $settings->revision === (int) $d['revision'], 409, 'Receiving settings changed. Refresh before retrying.');
            $previous = $settings->active_account_id;
            $id = $d['account_id'] ?? null;
            if ($id) DB::table('commission_accounts')->where('id', $id)->firstOrFail();
            $active = $previous;
            if (in_array($d['action'], ['publish', 'create', 'edit'])) {
                $id = (string) Str::uuid();
                DB::table('commission_accounts')->insert([
                    'id' => $id, 'account_name' => 'Encrypted account', 'account_number' => '***********',
                    'encrypted_details' => Crypt::encryptString(json_encode([
                        'account_name' => trim($d['account_name']), 'account_number' => $d['account_number'],
                    ], JSON_THROW_ON_ERROR)), 'created_by' => $actor->id, 'created_at' => now(),
                ]);
                if ($d['action'] === 'publish' || ($d['action'] === 'edit' && $previous === $d['account_id'])) $active = $id;
            } elseif ($d['action'] === 'activate') {
                $active = $id;
            } else {
                abort_unless($previous === $id, 409, 'This account is not active. Refresh before retrying.');
                $active = null;
            }
            $revision = (int) $settings->revision + 1;
            DB::table('commission_account_settings')->where('id', 1)->update(['active_account_id' => $active, 'revision' => $revision]);
            DB::table('commission_account_events')->insert([
                'account_id' => $id, 'previous_account_id' => $d['account_id'] ?? $previous,
                'actor_id' => $actor->id, 'action' => $d['action'], 'revision' => $revision,
                'reason' => $d['reason'], 'created_at' => now(),
            ]);
        }, 3);
    }
}
