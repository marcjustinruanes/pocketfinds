<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationCode;
use App\Models\User;
use App\Support\AccountInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class BuyerContactController extends Controller
{
    public function send(Request $request, string $field)
    {
        abort_unless(in_array($field, ['email', 'contact_no'], true), 404);
        $value = $field === 'email' ? mb_strtolower(trim((string) $request->input('value'))) : AccountInput::phone($request->input('value'));
        $request->merge(['value' => $value]);
        $request->validate(['value' => $field === 'email' ? ['required', 'string', 'email', 'max:255'] : ['required', 'string', 'regex:/\A09[0-9]{9}\z/']]);
        DB::transaction(function () use ($request, $field, $value) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->assertAvailable($field, $value, $user);
            if ($value === $user->$field) {
                throw ValidationException::withMessages(['value' => 'Enter a different '.($field === 'email' ? 'email address.' : 'phone number.')]);
            }
            $old = DB::table('account_verification_tokens')->where('user_id', $user->id)->where('purpose', 'change_'.$field)->first();
            do {
                $code = sprintf('%06d', random_int(0, 999999));
            } while ($old && Hash::check($code, $old->token_hash));
            DB::table('account_verification_tokens')->updateOrInsert(['user_id' => $user->id, 'purpose' => 'change_'.$field], [
                'pending_value' => $value, 'token_hash' => Hash::make($code), 'attempts' => 0,
                'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
            ]);
            // Email changes prove ownership of the new mailbox. Phone changes are
            // confirmed through the already registered email; no SMS delivery is claimed.
            Mail::to($field === 'email' ? $value : $user->email)->send(new RegistrationCode($code));
        });

        return back()->with('contact_success', 'A verification code was sent to '.($field === 'email' ? 'your new email address.' : 'your registered email address.'));
    }

    public function verify(Request $request, string $field)
    {
        abort_unless(in_array($field, ['email', 'contact_no'], true), 404);
        $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        // Commit failed attempts too: throwing inside the transaction would roll them back.
        $valid = DB::transaction(function () use ($request, $field) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $token = DB::table('account_verification_tokens')->where('user_id', $user->id)->where('purpose', 'change_'.$field)->lockForUpdate()->first();
            if (! $token || now()->greaterThanOrEqualTo($token->expires_at) || $token->attempts >= 10 || ! Hash::check($request->code, $token->token_hash)) {
                if ($token) {
                    DB::table('account_verification_tokens')->where('id', $token->id)->increment('attempts');
                }

                return false;
            }
            $this->assertAvailable($field, $token->pending_value, $user);
            $user->forceFill([$field => $token->pending_value])->save();
            DB::table('account_verification_tokens')->where('id', $token->id)->delete();

            return true;
        });
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired verification code. Request a new code if needed.']);
        }

        return back()->with('contact_success', 'Your '.($field === 'email' ? 'email address' : 'phone number').' has been updated.');
    }

    private function assertAvailable(string $field, string $value, User $user): void
    {
        $duplicate = User::whereKeyNot($user->id)->whereRaw('LOWER('.$field.') = ?', [mb_strtolower($value)])->exists();
        if ($field === 'contact_no') {
            $duplicate = $duplicate || User::whereKeyNot($user->id)->where('contact_no', '+63'.substr($value, 1))->exists();
        }
        if ($duplicate) {
            throw ValidationException::withMessages(['value' => $field === 'email' ? 'Email already in use.' : 'Phone number already in use.']);
        }
    }
}
