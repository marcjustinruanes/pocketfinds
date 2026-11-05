<?php

namespace App\Services;

use App\Mail\RiderActivationMail;
use App\Models\LogisticsHub;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RiderActivation
{
    public function invitation(User $rider): ?object
    {
        return Schema::hasTable('rider_account_activations') ? DB::table('rider_account_activations')->where('user_id', $rider->id)->first() : null;
    }

    public function pending(User $rider): bool
    {
        $invitation = $this->invitation($rider);
        return $invitation && ! $invitation->activated_at;
    }

    public function issue(User $rider, User $admin, bool $new = false): void
    {
        abort_unless($admin->account_type === 'logistics' && $admin->is_logistics && !$admin->is_admin && $admin->status === 'approved' && $admin->isLogisticsAdmin()
            && $admin->business_name === $rider->business_name && $rider->account_type === 'rider', 403);
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        DB::transaction(function () use ($rider, $admin, $new, $hash) {
            $current = User::whereKey($rider->id)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->account_type === 'logistics' && $actor->is_logistics && !$actor->is_admin && $actor->status === 'approved' && $actor->isLogisticsAdmin() && $actor->business_name === $current->business_name && $current->account_type === 'rider', 403);
            abort_unless(in_array($current->status, ['pending', 'rejected'], true), 422, 'Only riders waiting for activation or application correction can receive this link.');
            abort_unless($current->status!=='rejected' || $current->can_resubmit,422,'Resubmission is not permitted for this application.');
            $previous = DB::table('rider_account_activations')->where('user_id', $current->id)->lockForUpdate()->first();
            abort_unless($new || ($previous && !$previous->activated_at), 422, 'This rider does not have a pending activation.');
            abort_unless($current->status === 'pending' || ($previous && !$previous->activated_at && !$new), 422);
            DB::table('rider_account_activations')->updateOrInsert(['user_id' => $current->id], [
                'invited_by' => $admin->id, 'company_name' => $current->business_name, 'email' => $current->email,
                'token_hash' => $hash, 'expires_at' => now()->addHours(24), 'activated_at' => null,
                'sent_at' => null, 'delivery_status' => 'pending', 'created_at' => $previous?->created_at ?? now(), 'updated_at' => now(),
            ]);
        });
        $url = route('rider.activation.show', $token);
        DB::afterCommit(function () use ($rider, $url, $hash) {
            if (! DB::table('rider_account_activations')->where('user_id', $rider->id)->where('token_hash', $hash)->exists()) return;
            try {
                $current = $rider->fresh();
                if (!$current || !in_array($current->status,['pending','rejected'],true) || ($current->status==='rejected' && !$current->can_resubmit)) return;
                Mail::to($rider->email)->send($current->status==='rejected' ? new \App\Mail\ApplicationRejectedMail($current,$url) : new RiderActivationMail($rider->given_names, $rider->business_name, $url));
                DB::table('rider_account_activations')->where('user_id', $rider->id)->where('token_hash', $hash)->update(['sent_at' => now(), 'delivery_status' => 'sent']);
            } catch (\Throwable $error) {
                DB::table('rider_account_activations')->where('user_id', $rider->id)->where('token_hash', $hash)->update(['delivery_status' => 'failed']);
                Log::warning('Rider activation email could not be sent.', ['user_id' => $rider->id, 'exception' => $error::class]);
            }
        });
    }

    public function valid(string $token): ?object
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
        return DB::table('rider_account_activations')->where('token_hash', hash('sha256', $token))
            ->whereNull('activated_at')->where('expires_at', '>', now())->first();
    }

    public function activate(string $token, string $password): void
    {
        DB::transaction(function () use ($token, $password) {
            $peek = $this->valid($token);
            if (! $peek) throw ValidationException::withMessages(['activation' => 'This activation link has expired or has already been used. Ask your logistics admin for a new link.']);
            $rider = User::whereKey($peek->user_id)->firstOrFail();
            $hub = LogisticsHub::whereKey($rider->logistics_hub_id)->lockForUpdate()->first();
            $rider = User::whereKey($peek->user_id)->lockForUpdate()->firstOrFail();
            $invitation = DB::table('rider_account_activations')->where('user_id', $rider->id)->lockForUpdate()->first();
            if (! $invitation || $invitation->activated_at || !hash_equals($invitation->token_hash, hash('sha256', $token)) || now()->greaterThanOrEqualTo($invitation->expires_at)) {
                throw ValidationException::withMessages(['activation' => 'This activation link has expired or has already been used.']);
            }
            abort_unless($rider->account_type === 'rider' && $rider->status === 'pending' && $rider->email === $invitation->email && $rider->business_name === $invitation->company_name, 422, 'This invitation is no longer valid. Ask your logistics admin for a new one.');
            $admin = User::whereKey($invitation->invited_by)->lockForUpdate()->first();
            abort_unless($admin && $admin->account_type === 'logistics' && $admin->is_logistics && !$admin->is_admin && $admin->status === 'approved' && $admin->isLogisticsAdmin() && $admin->business_name === $rider->business_name, 422, 'The inviting logistics account is no longer active.');
            abort_unless($hub && $hub->id == $rider->logistics_hub_id && $hub->is_operational && $hub->company_name === $rider->business_name, 422, 'The assigned hub is unavailable. Contact your logistics admin.');
            app(HubHiring::class)->completeRiderActivation($rider, $password);
            DB::table('rider_account_activations')->where('user_id', $rider->id)->update(['activated_at' => now(), 'updated_at' => now()]);
        }, 3);
    }
}
