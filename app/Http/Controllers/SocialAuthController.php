<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Client;

class SocialAuthController extends Controller
{
    private function googleProvider()
    {
        // Use the exact callback registered for this OAuth client in Google Cloud.
        return Socialite::driver('google');
    }

    private function googleStartRedirect(string $route, array $parameters = [])
    {
        $callback = parse_url(config('services.google.redirect'));
        $origin = $callback['scheme'].'://'.$callback['host'].(isset($callback['port']) ? ':'.$callback['port'] : '');

        // Establish the OAuth session where Google will return its callback.
        if (request()->getSchemeAndHttpHost() !== $origin) {
            return redirect()->away($origin.route($route, $parameters, false));
        }

        return null;
    }

    public function redirectToGoogle()
    {
        $type = request('type', 'buyer');
        $verification = request()->boolean('verify_email');
        $parameters = ['type' => $type];
        if ($verification) {
            request()->validate(['nonce' => 'required|string|size:64']);
            $parameters += ['verify_email' => 1, 'nonce' => request('nonce')];
            if (request()->boolean('same_tab')) $parameters['same_tab'] = 1;
        }
        if ($redirect = $this->googleStartRedirect('google.redirect', $parameters)) {
            return $redirect;
        }
        session(['oauth_account_type' => $type, 'oauth_intent' => $verification ? 'verify-email' : 'register']);
        if ($verification) session(['oauth_email_nonce' => request('nonce')]);
        if ($verification) session(['oauth_email_same_tab' => request()->boolean('same_tab')]);
        return $verification ? $this->googleProvider()->with(['prompt'=>'select_account'])->redirect() : $this->googleProvider()->redirect();
    }

    public function redirectToGoogleLogin()
    {
        if ($redirect = $this->googleStartRedirect('google.login')) {
            return $redirect;
        }
        session()->forget('oauth_account_type');
        session(['oauth_intent' => 'login']);
        return $this->googleProvider()->redirect();
    }

    public function redirectAccountEmailVerification()
    {
        abort_unless(Auth::check() && Auth::user()->account_type === 'buyer', 403);
        if ($redirect = $this->googleStartRedirect('buyer.account.google.email')) return $redirect;
        session(['oauth_intent' => 'account-email', 'oauth_account_email_owner' => Auth::id(), 'oauth_account_email_expires' => now()->addMinutes(10)->timestamp]);
        return $this->googleProvider()->with(['prompt'=>'select_account'])->redirect();
    }

    public function handleGoogleCallback()
    {
        $intent = session('oauth_intent', 'login');

        // Google redirects here without a usable `code` if the user cancels
        // consent, the code was already used (e.g. a duplicate/back-button
        // hit), or the request otherwise didn't come from a fresh OAuth
        // handshake — bail out to a friendly page instead of a raw 500.
        if (request()->filled('error') || !request()->filled('code')) {
            return $this->googleFailureRedirect($intent, 'Google sign-in was cancelled or could not be completed. Please try again.');
        }

        try {
            $googleUser = $this->googleProvider()
                ->setHttpClient(new Client(['verify' => false]))
                ->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            return $this->googleFailureRedirect($intent, 'Your Google sign-in session expired. Please try again.');
        } catch (\Throwable $e) {
            return $this->googleFailureRedirect($intent, 'Google sign-in failed. Please try again.');
        }

        if ($intent === 'account-email') {
            $owner = session()->pull('oauth_account_email_owner');
            $expires = session()->pull('oauth_account_email_expires', 0);
            session()->forget('oauth_intent');
            if (!Auth::check() || (string)Auth::id() !== (string)$owner || $expires < now()->timestamp || Auth::user()->account_type !== 'buyer') {
                return $this->googleFailureRedirect($intent, 'Email verification expired. Please try again.');
            }
            $email = strtolower((string)$googleUser->getEmail());
            if (!($googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? false) || !preg_match('/^[^@]+@gmail\.com$/i', $email)) {
                return $this->googleFailureRedirect($intent, 'Choose a Google account with a verified Gmail address.');
            }
            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($owner, $email) {
                    $account = User::whereKey($owner)->lockForUpdate()->firstOrFail();
                    if (User::where('email',$email)->where('id','!=',$owner)->exists()) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['email'=>'This email is already registered. Choose another email.']);
                    }
                    $account->forceFill(['email'=>$email,'email_verified_at'=>now()])->save();
                });
            } catch (\Illuminate\Validation\ValidationException $error) {
                return $this->googleFailureRedirect($intent, $error->validator->errors()->first());
            } catch (\Illuminate\Database\QueryException $error) {
                report($error);
                return $this->googleFailureRedirect($intent, 'This email could not be updated. Choose another email or try again.');
            }
            \Illuminate\Support\Facades\Cache::forget('buyer-email-change:'.$owner);
            request()->session()->forget(['buyer_password_change_pending','buyer_password_change_token']);
            return redirect()->route('buyer.account')->withFragment('account-security')->with('account_google_email', $email)->with('success','Email verified with Google and updated.');
        }

        if ($intent === 'verify-email') {
            $email = strtolower((string) $googleUser->getEmail());
            if (!($googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? false) || !preg_match('/@gmail\.com$/i', $email)) {
                return $this->googleFailureRedirect($intent, 'Choose a Google account with a verified Gmail address.');
            }
            $proof = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
                'purpose' => 'registration-email', 'email' => $email,
                'nonce' => session('oauth_email_nonce'), 'expires_at' => now()->addMinutes(5)->timestamp,
            ]));
            return $this->emailVerificationResult($proof);
        }

        $existing = User::where('email', $googleUser->getEmail())
                        ->orWhere('google_id', $googleUser->getId())
                        ->first();
        if ($existing && $existing->status==='rejected') {
            session()->forget(['seller_application_correction','application_correction']);
            if (!$existing->can_resubmit || $existing->is_admin) return redirect()->route('login')->withErrors(['email'=>'Your application was rejected. Editing and resubmission are not permitted.'])->with('accountStatus','rejected');
            Auth::logout(); request()->session()->regenerate();
            if ($existing->account_type==='seller') {
                session()->put('seller_application_correction',['id'=>$existing->id,'expires_at'=>now()->addMinutes(30)->timestamp]);
                return redirect()->route('seller.application.edit');
            }
            session()->put('application_correction',\App\Support\ApplicationCorrection::grant($existing));
            return redirect()->route('application.correction.edit');
        }

        // --- SIGN IN via Google ---
        if ($intent === 'login') {
            if (!$existing) {
                return redirect()->route('login')->withErrors([
                    'email' => 'No account found with this Google email. Please register first.',
                ]);
            }

            if ($existing->status === 'pending') {
                return redirect()->route('login')->withErrors([
                    'email' => 'Your account is still pending admin approval. Please wait for confirmation.',
                ]);
            }

            if ($existing->status === 'rejected') {
                return redirect()->route('login')->withErrors([
                    'email' => 'Your account application was rejected.'.($existing->status_reason ? ' Reason: '.$existing->status_reason : ''),
                ])->with('accountStatus', 'rejected');
            }

            Auth::login($existing, true);
            request()->session()->regenerate();

            if ($existing->is_admin) return redirect()->route('admin.dashboard');
            if ($existing->is_logistics) return redirect()->route('logistics.dashboard');
            if ($existing->account_type === 'buyer') return redirect()->route('buyer.dashboard');
            if ($existing->account_type === 'seller') return redirect()->route('seller.dashboard');
            if ($existing->account_type === 'rider') return redirect()->route('rider.dashboard');
            return redirect()->intended('/');
        }

        // --- REGISTER via Google ---
        if ($existing && $existing->status !== 'rejected') {
            return redirect()->route('login')->withErrors([
                'email' => 'An account with this Google email already exists. Please sign in instead.',
            ]);
        }

        if (!($googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? false)) {
            return $this->googleFailureRedirect($intent, 'Choose a Google account with a verified email address.');
        }
        session(['registration_verified_email'=>['email'=>strtolower((string)$googleUser->getEmail()), 'expires_at'=>now()->addHour()->timestamp]]);
        session([
            'google_id'     => $googleUser->getId(),
            'google_name'   => $googleUser->getName(),
            'google_email'  => $googleUser->getEmail(),
            'google_avatar' => $googleUser->getAvatar(),
        ]);

        $type = session('oauth_account_type', 'buyer');

        // Every role has its own dedicated registration page. 'logistics-staff' (hub staff
        // joining an existing company) was missing here — it silently fell through to the
        // buyer page, since a bare match() has no idea that type even exists.
        return match ($type) {
            'seller'          => redirect()->route('register.seller', ['google' => 1]),
            'rider'           => redirect()->route('register.rider', ['google' => 1]),
            'logistics'       => redirect()->route('register.logistics', ['google' => 1]),
            'logistics-staff' => redirect()->route('register.logistics-staff', ['google' => 1]),
            default           => redirect()->route('register.buyer', ['google' => 1]),
        };
    }

    public function resetEmailVerification(\Illuminate\Http\Request $request)
    {
        $request->session()->forget(['registration_verified_email', 'registration_google_nonce']);
        return response()->json(['success'=>true]);
    }

    public function startEmailVerification(\Illuminate\Http\Request $request)
    {
        $request->validate(['type' => 'required|in:buyer,seller,rider,logistics,logistics-staff', 'same_tab' => 'sometimes|boolean','purpose'=>'sometimes|in:representative']);
        if($request->input('purpose')==='representative'&&$request->type!=='logistics')abort(422);
        $nonce = bin2hex(random_bytes(32));
        $request->session()->put('registration_google_nonce', $nonce);
        $request->session()->put('registration_google_type', $request->type);
        $target=$request->input('purpose')==='representative'?'representative':'company';
        $request->session()->put('registration_google_email_target',$target);
        $request->session()->forget($target==='representative'?'registration_verified_representative_email':'registration_verified_email');
        $parameters = ['type'=>$request->type, 'verify_email'=>1, 'nonce'=>$nonce];
        if ($request->boolean('same_tab')) {
            $parameters['same_tab'] = 1;
            $returnUrl = $request->getSchemeAndHttpHost().route('register.google.return', [], false);
            \Illuminate\Support\Facades\Cache::put('registration_google_return_'.hash('sha256', $nonce), $returnUrl, now()->addMinutes(10));
        }
        $callback = parse_url(config('services.google.redirect'));
        $origin = $callback['scheme'].'://'.$callback['host'].(isset($callback['port']) ? ':'.$callback['port'] : '');
        return response()->json(['url' => $request->getSchemeAndHttpHost().route('google.redirect', $parameters, false), 'origin'=>$origin]);
    }

    public function completeEmailVerification(\Illuminate\Http\Request $request)
    {
        $request->validate(['proof' => 'required|string|max:4096']);
        try {
            $proof = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($request->proof), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return response()->json(['message'=>'Email verification failed. Please try again.'], 422);
        }
        if (($proof['purpose'] ?? '') !== 'registration-email' || ($proof['expires_at'] ?? 0) < now()->timestamp ||
            !is_string($proof['nonce'] ?? null) || !hash_equals((string)$request->session()->get('registration_google_nonce', ''), $proof['nonce']) ||
            !filter_var($proof['email'] ?? '', FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $proof['email'])) {
            return response()->json(['message'=>'Email verification expired. Please try again.'], 422);
        }
        if (User::where('email', $proof['email'])->where('status', '!=', 'rejected')->exists()) {
            return response()->json(['message'=>'This email is already registered. Try signing in instead.'], 422);
        }
        $request->session()->forget('registration_google_nonce');
        $target=$request->session()->get('registration_google_email_target','company');
        $request->session()->put($target==='representative'?'registration_verified_representative_email':'registration_verified_email', ['email'=>$proof['email'], 'expires_at'=>now()->addHour()->timestamp, 'method'=>'google']);
        return response()->json(['email'=>$proof['email']]);
    }

    public function returnEmailVerification(\Illuminate\Http\Request $request)
    {
        $type = $request->session()->get('registration_google_type', 'buyer');
        $returnParameters=['google_return'=>1];
        if($request->session()->get('registration_google_email_target')==='representative')$returnParameters['email_target']='representative';
        if (!in_array($type, ['buyer','seller','rider','logistics','logistics-staff'], true)) $type = 'buyer';
        if ($request->filled('error') || !$request->filled('proof')) {
            return redirect()->route('register.'.$type, $returnParameters)
                ->withErrors(['email'=>'Google verification was cancelled or could not be completed. Please try again.'])
                ->with('google_email_error', 'Google verification was cancelled or could not be completed. Please try again.')
                ->header('Referrer-Policy', 'no-referrer');
        }
        $result = $this->completeEmailVerification($request);
        if ($result->getStatusCode() !== 200) {
            return redirect()->route('register.'.$type, $returnParameters)->withErrors(['email'=>$result->getData(true)['message']])
                ->with('google_email_error', $result->getData(true)['message'])->header('Referrer-Policy', 'no-referrer');
        }
        return redirect()->route('register.'.$type, $returnParameters)
            ->with('google_verified_email', $result->getData(true)['email'])
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    private function emailVerificationResult(?string $proof, ?string $error = null)
    {
        $nonce = session()->pull('oauth_email_nonce');
        if (session()->pull('oauth_email_same_tab', false)) {
            $returnUrl = \Illuminate\Support\Facades\Cache::pull('registration_google_return_'.hash('sha256', (string)$nonce));
            if ($returnUrl) {
                return redirect()->away($returnUrl.'?'.http_build_query($proof ? ['proof'=>$proof] : ['error'=>'verification_failed']))
                    ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
            }
            return redirect()->route('register.method', ['type'=>session('oauth_account_type', 'buyer')])->withErrors(['email'=>'Google verification expired. Please try again.']);
        }
        return response()->view('auth.google-email-result', ['proof'=>$proof, 'error'=>$error])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    private function googleFailureRedirect(string $intent, string $message)
    {
        if ($intent === 'account-email') {
            session()->forget(['oauth_account_email_owner','oauth_account_email_expires','oauth_intent']);
            return redirect()->route('buyer.account')->withFragment('account-security')->withErrors(['email'=>$message]);
        }
        if ($intent === 'verify-email') {
            return $this->emailVerificationResult(null, $message);
        }

        if ($intent === 'login') {
            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        $type = session('oauth_account_type', 'buyer');
        return redirect()->route('register.method', ['type' => $type])->withErrors(['email' => $message]);
    }
}
