<?php

namespace App\Services;

use App\Mail\RegistrationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AccountVerification
{
    public function send(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->status === 'unverified', 422, 'This account does not need email verification.');
            $old = DB::table('account_verification_tokens')->where('user_id', $user->id)->where('purpose', 'registration')->first();
            do {
                $code = sprintf('%06d', random_int(0, 999999));
            } while ($old && Hash::check($code, $old->token_hash));
            DB::table('account_verification_tokens')->updateOrInsert(['user_id' => $user->id, 'purpose' => 'registration'],
                ['token_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(5), 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
            Mail::to($user->email)->send(new RegistrationCode($code));
        });
    }

    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $token = DB::table('account_verification_tokens')->where('user_id', $user->id)->where('purpose', 'registration')->lockForUpdate()->first();
            if ($user->status !== 'unverified' || ! $token || now()->greaterThanOrEqualTo($token->expires_at)
                || $token->attempts >= 10 || ! Hash::check($code, $token->token_hash)) {
                if ($token) {
                    DB::table('account_verification_tokens')->where('id', $token->id)->increment('attempts');
                }

                return false;
            }
            $user->forceFill(['email_verified_at' => now(), 'status' => $user->account_type === 'buyer' ? 'approved' : 'pending'])->save();
            DB::table('account_verification_tokens')->where('id', $token->id)->delete();

            return true;
        });
    }
}
