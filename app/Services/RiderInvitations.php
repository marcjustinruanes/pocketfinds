<?php

namespace App\Services;

use App\Mail\RiderActivation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RiderInvitations
{
    public function send(User $rider): void
    {
        abort_unless($rider->account_type === 'rider' && $rider->status === 'pending_activation', 422);
        $token = Str::random(64);
        DB::table('account_verification_tokens')->updateOrInsert(['user_id' => $rider->id, 'purpose' => 'rider_activation'],
            ['token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(24), 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
        Mail::to($rider->email)->send(new RiderActivation($rider, $token));
    }

    public function check(User $user, string $token): void
    {
        $row = DB::table('account_verification_tokens')->where('user_id', $user->id)->where('purpose', 'rider_activation')->first();
        abort_unless($user->account_type === 'rider' && $user->status === 'pending_activation' && $row, 410, 'This activation link has already been used or replaced.');
        abort_unless(hash_equals($row->token_hash, hash('sha256', $token)), 403, 'Invalid activation link.');
        abort_if(now()->greaterThanOrEqualTo($row->expires_at), 410, 'This activation link has expired. Ask your logistics company for a new invitation.');
    }

    public function activate(User $user, string $token, string $password): void
    {
        DB::transaction(function () use ($user, $token, $password) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->check($user, $token);
            $user->forceFill(['password' => $password, 'email_verified_at' => now()]);
            app(HubHiring::class)->activateInvitation($user);
            DB::table('account_verification_tokens')->where('user_id', $user->id)->where('purpose', 'rider_activation')->delete();
        });
    }
}
