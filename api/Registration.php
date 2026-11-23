<?php
declare(strict_types=1);

use App\Models\User;
use App\Services\AccountVerification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
require_once __DIR__.'/Applications.php';
require_once __DIR__.'/WebsiteRegistration.php';

final class MobileRegistration {
    public static function activateRider(array $data): array {
        $key='mobile-rider-activation:'.($_SERVER['REMOTE_ADDR']??'local');
        ensure(!RateLimiter::tooManyAttempts($key,10),'Too many activation attempts. Please wait a minute.',429);
        RateLimiter::hit($key,60);
        validateData($data,['invitation'=>'required|url|max:2048','password'=>['required','string','max:128',\Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols(),'confirmed']]);
        $path=parse_url($data['invitation'],PHP_URL_PATH)?:'';
        ensure((bool)preg_match('#/rider/activate/([a-f0-9]{64})/?$#D',$path,$match),'Paste the full rider activation link from your invitation email.');
        // Never fetch the submitted URL. Only the website's existing one-use
        // token and invitation service can authorize this activation.
        app(\App\Services\RiderActivation::class)->activate($match[1],$data['password']);
        return ['success'=>true];
    }
    public static function challenge(User $user): array {
        ensure($user->status==='unverified','This account does not need verification.');
        $raw=bin2hex(random_bytes(32));
        DB::table('mobile_registration_tokens')->insert(['id'=>(string)Str::uuid(),'email'=>$user->email,
            'token_hash'=>hash('sha256',$raw),'user_id'=>$user->id,'expires_at'=>now()->addDay()]);
        return ['verification_required'=>true,'registration_token'=>$raw,'email'=>$user->email];
    }
    public static function googleRegistration(array $claims): array {
        $email=mb_strtolower($claims['email']);
        ensure(!User::whereRaw('LOWER(email) = ?',[$email])->where('status','!=','rejected')->exists(),
            'An account with this Google email already exists. Please sign in instead.');
        return ['google_registration_token'=>Crypt::encryptString(json_encode([
            'purpose'=>'mobile-google-registration','email'=>$email,'google_id'=>$claims['sub'],'expires'=>time()+1200,
        ])), 'email'=>$email,'given_names'=>$claims['given_name']??'','last_name'=>$claims['family_name']??''];
    }
    public static function register(array $data): array {
        $key='website-registration:'.hash('sha256',($_SERVER['REMOTE_ADDR']??'local').':'.mb_strtolower((string)($data['email']??'')));
        ensure(!RateLimiter::tooManyAttempts($key,8),'Too many applications. Please wait a minute.',429);
        RateLimiter::hit($key,60);
        validateData($data,['request_id'=>'required|uuid']);
        return MobileRequests::run('website-registration',$data['request_id'],$data,fn()=>WebsiteRegistration::register($data));
    }
    public static function verification(string $action,array $data): array {
        validateData($data,['registration_token'=>'required|string|size:64']);
        $token=DB::table('mobile_registration_tokens')->where('token_hash',hash('sha256',$data['registration_token']))->where('expires_at','>',now())->first();
        ensure($token && $token->user_id,'Verification session expired. Sign in to request a new code.',401);
        $user=User::find($token->user_id); ensure($user!==null,'Account unavailable.',404);
        if ($action==='resend-registration') {
            ensure($user->status==='unverified','This account is already verified.');
            $old=DB::table('account_verification_tokens')->where('user_id',$user->id)->where('purpose','registration')->first();
            ensure(!$old || strtotime($old->created_at)<=time()-60,'Please wait a minute before requesting another code.',429);
            try { app(AccountVerification::class)->send($user); }
            catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface) { throw new ApiError('The email could not be sent. Please try again shortly.',503); }
            return ['sent'=>true];
        }
        validateData($data,['code'=>'required|digits:6']);
        $verified=DB::transaction(function() use($user,$data) {
            // Keep failed-attempt commits from AccountVerification intact.
            $ok=app(AccountVerification::class)->verify($user,$data['code']);
            if (!$ok) return false;
            $user->refresh(); $user->forceFill(['status'=>'pending'])->save();
            DB::table('mobile_registration_tokens')->where('user_id',$user->id)->delete();
            return true;
        });
        ensure($verified,'The code is incorrect or expired.');
        return ['verified'=>true,'status'=>'pending','approval_required'=>true];
    }
}
