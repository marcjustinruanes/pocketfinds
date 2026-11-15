<?php
declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class MobileAuth {
    public function __construct(private Identity $identity, private string $bearer) {}
    public function handle(string $action, array $data): mixed {
        $email = strtolower(trim((string)($data['email'] ?? '')));
        if (in_array($action, ['login','otp','verify-otp','reset-password','confirm-reset','confirm-email','google','registration-send-code','registration-verify-code','registration-username'], true)) {
            $key = 'mobile-auth:'.hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'local').':'.$email.':'.$action);
            ensure(!RateLimiter::tooManyAttempts($key, 8), 'Too many attempts. Please wait a minute.', 429);
            RateLimiter::hit($key, 60);
        }
        switch ($action) {
            case 'phone-code':
                $uid=$this->identity->approved('buyer');
                $phone=\App\Support\AccountInput::phone($data['phone']??'');
                validateData(['phone'=>$phone],['phone'=>['required','regex:/^09[0-9]{9}$/D','unique:users,contact_no,'.$uid]]);
                ensure($phone!==$this->identity->user->contact_no,'This is already your mobile number.');
                $this->sendCode($this->identity->user->email,'phone-change-'.$uid);
                return ['ticket'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['user'=>$uid,'phone'=>$phone,'expires'=>time()+300])),
                    'message'=>'Verification code sent to your current verified email to authorize the mobile-number change.'];
            case 'confirm-phone':
                $uid=$this->identity->approved('buyer');
                validateData($data,['ticket'=>'required|string|max:4000','code'=>'required|digits:6']);
                try {$ticket=json_decode(\Illuminate\Support\Facades\Crypt::decryptString($data['ticket']),true);}catch(Throwable){throw new ApiError('Request a new mobile-number verification code.');}
                ensure(($ticket['user']??null)===$uid && ($ticket['expires']??0)>time(),'Request a new mobile-number verification code.');
                validateData(['phone'=>$ticket['phone']],['phone'=>'unique:users,contact_no,'.$uid]);
                $this->verifyCode($this->identity->user->email,'phone-change-'.$uid,$data['code']);
                $this->identity->user->forceFill(['contact_no'=>$ticket['phone']])->save();return ['ok'=>true];
            case 'config':
                return ['google_web_client_id'=>GoogleIdentity::clientId(),
                    'google_ios_client_id'=>(string)config('services.google.ios_client_id',''),
                    'website_url'=>rtrim((string)config('app.url'),'/')];
            case 'register-buyer':
                return MobileRegistration::register($data);
            case 'registration-send-code':
                validateData($data,['email'=>['required','email','max:255','regex:/@gmail\.com$/i']]);
                ensure(!User::whereRaw('LOWER(email) = ?',[$email])->where('status','!=','rejected')->exists(),'This email is already registered.');
                $this->sendCode($email,'signup'); return ['sent'=>true,'expires_at'=>now()->addMinutes(5)->toIso8601String()];
            case 'registration-verify-code':
                validateData($data,['email'=>'required|email|max:255','code'=>'required|digits:6']);
                $this->verifyCode($email,'signup',$data['code']);
                return ['email_token'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['purpose'=>'signup-email','email'=>$email,'expires'=>time()+1800]))];
            case 'registration-terms':
                validateData($data,['account_type'=>'sometimes|in:buyer,rider','company'=>'nullable|string|max:255']);
                $terms=!empty($data['company'])
                    ? \App\Models\LogisticsCompany::policyFor($data['company'])
                    : \App\Models\Policy::where('type','terms_and_conditions')->where('account_type',$data['account_type']??'buyer')->whereNull('company_name')->first();
                return ['title'=>$terms?->title??'Terms & Conditions','content'=>$terms?->content??'Terms & Conditions are not available right now. Please contact support.'];
            case 'registration-options':
                return WebsiteRegistration::options();
            case 'registration-postal-code':
                validateData($data,['municipality_code'=>['required','regex:/^\d{9,10}$/D'],'municipality'=>'required|string|max:255']);
                $postal=app(\App\Services\RegistrationPostalCode::class)->lookup($data['municipality_code'],$data['municipality']);
                return ['postal_code'=>$postal,'status'=>$postal ? 'found' : 'unavailable'];
            case 'registration-username':
                validateData($data,['username'=>'required|string|min:8|max:30|alpha_dash']);
                return ['available'=>!User::where('username',$data['username'])->where('status','!=','rejected')->exists()];
            case 'activate-rider':
                return MobileRegistration::activateRider($data);
            case 'verify-registration': case 'resend-registration':
                $key='mobile-verify:'.hash('sha256',(string)($data['registration_token']??''));
                ensure(!RateLimiter::tooManyAttempts($key,20),'Too many attempts. Please wait a minute.',429);
                RateLimiter::hit($key,60);
                return MobileRegistration::verification($action,$data);
            case 'login':
                validateData($data, ['email'=>'required|string|max:255', 'password'=>'required|string']);
                $user = User::where(fn($q)=>$q->whereRaw('LOWER(email) = ?', [$email])->orWhere('username',trim($data['email'])))->first();
                ensure($user && $user->password && Hash::check($data['password'], $user->password), 'Incorrect email, username or password.', 401);
                return $this->loginResult($user);
            case 'otp': case 'verify-otp': case 'register':
                throw new ApiError('Use the registration form, then verify the emailed code.');
            case 'refresh':
                ensure($this->identity->user || $this->identity->registration, 'Sign in to continue.', 401);
                return $this->identity->session($this->bearer);
            case 'logout':
                if ($this->identity->user?->account_type==='rider' && MobileRider::installed()) MobileRider::offline($this->identity->user->id);
                if ($this->identity->token) DB::table('mobile_push_devices')->where('session_hash',$this->identity->token->token)->delete();
                if ($this->identity->token) DB::table('personal_access_tokens')->where('id',$this->identity->token->id)->delete();
                if ($this->identity->registration) DB::table('mobile_registration_tokens')->where('id',$this->identity->registration->id)->delete();
                return ['ok'=>true];
            case 'update':
                ensure($this->identity->user!==null,'Use the registration form to create an account.',403);
                $user = $this->identity->user;
                $this->identity->approved();
                if (isset($data['password'])) {
                    validateData($data, ['current_password'=>'required|string','password'=>\App\Support\AccountInput::password()]);
                    ensure(Hash::check($data['current_password'],$user->password??''),'Current password is incorrect.');
                    ensure(!Hash::check($data['password'],$user->password??''),'Choose a password different from your current password.');
                    $user->password = $data['password'];
                    DB::table('personal_access_tokens')->where('tokenable_type',User::class)->where('tokenable_id',$user->id)->when($this->bearer!=='',fn($q)=>$q->where('token','!=',hash('sha256',$this->bearer)))->delete();
                }
                if (isset($data['email'])) {
                    validateData($data, ['email'=>'required|email|max:255|unique:users,email,'.$user->id]);
                    // A new address must prove ownership before replacing the login email.
                    $this->sendCode($email, 'email-change-'.$user->id);
                    return ['verification_required'=>true];
                }
                $user->save();
                return ['ok'=>true];
            case 'confirm-email':
                $this->identity->approved();
                validateData($data, ['email'=>'required|email|max:255|unique:users,email,'.$this->identity->id(), 'code'=>'required|digits:6']);
                $this->verifyCode($email, 'email-change-'.$this->identity->id(), $data['code']);
                $this->identity->user->forceFill(['email'=>$email,'email_verified_at'=>now()])->save();
                return ['ok'=>true];
            case 'reset-password':
                validateData($data, ['email'=>'required|email']);
                if (User::where('email',$email)->exists()) $this->sendCode($email,'password-reset');
                return ['sent'=>true];
            case 'confirm-reset':
                validateData($data,['email'=>'required|email','code'=>'required|digits:6','password'=>'required|string|min:8|max:128']);
                $this->verifyCode($email,'password-reset',$data['code']);
                $user=User::where('email',$email)->first();
                ensure($user !== null,'Account unavailable.');
                $user->forceFill(['password'=>$data['password']])->save();
                DB::table('personal_access_tokens')->where('tokenable_type',User::class)->where('tokenable_id',$user->id)->delete();
                return ['ok'=>true];
            case 'google':
                validateData($data,['id_token'=>'required|string|max:8000','intent'=>'nullable|in:login,register']);
                $claims=GoogleIdentity::verify($data['id_token']);
                if (($data['intent']??'login')==='register') return MobileRegistration::googleRegistration($claims);
                $user=User::where('google_id',$claims['sub'])->first()
                    ?? User::whereRaw('LOWER(email) = ?',[mb_strtolower($claims['email'])])->first();
                ensure($user!==null,'No account found with this Google email. Please register first.',403);
                return $this->loginResult($user);
        }
        throw new ApiError('Unknown authentication action.',404);
    }

    public function sendCode(string $email,string $purpose): void {
        $old=DB::table('mobile_email_challenges')->where(compact('email','purpose'))->first();
        ensure(!$old || strtotime($old->created_at)<time()-60,'Please wait a minute before requesting another code.',429);
        $code=sprintf('%06d',random_int(0,999999));
        DB::table('mobile_email_challenges')->updateOrInsert(compact('email','purpose'),[
            'code_hash'=>Hash::make($code),'attempts'=>0,'expires_at'=>now()->addMinutes(5),'created_at'=>now(),
        ]);
        try {
            $base=rtrim(getenv('API_PUBLIC_URL')?:'http://127.0.0.1:8081','/');
            $link='';
            if ($purpose==='password-reset') $link="\nReset your password: $base/account/reset?email=".rawurlencode($email);
            if (str_starts_with($purpose,'email-change-')) {
                $ticket=\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['user_id'=>(int)substr($purpose,13),'email'=>$email,'expires'=>time()+600]));
                $link="\nConfirm your email: $base/account/email?ticket=".rawurlencode($ticket);
            }
            Mail::raw("Your PocketFinds verification code is $code. It expires in 5 minutes.$link",fn ($m)=>$m->to($email)->subject('PocketFinds verification code'));
        } catch (Throwable $e) {
            DB::table('mobile_email_challenges')->where(compact('email','purpose'))->delete();
            // Never log SMTP credentials, verification codes or recipient addresses.
            try { logger()->warning('Mobile verification email delivery failed.', [
                'exception_type'=>get_class($e),
                'mailer'=>config('mail.default'),
                'smtp_host'=>config('mail.mailers.smtp.host'),
                'smtp_port'=>config('mail.mailers.smtp.port'),
            ]); } catch (Throwable) {
                error_log('Mobile verification email delivery failed; logging unavailable.');
            }
            throw new ApiError('Email could not be sent. Check the website mail configuration.',503);
        }
    }
    public function verifyCode(string $email,string $purpose,string $code): void {
        $valid=DB::transaction(function () use ($email,$purpose,$code) {
            $q=DB::table('mobile_email_challenges')->where(compact('email','purpose'));
            $row=(clone $q)->lockForUpdate()->first();
            if (!$row || strtotime($row->expires_at)<=time() || $row->attempts>=8) return false;
            if (!Hash::check($code,$row->code_hash)) { $q->increment('attempts'); return false; }
            $q->delete(); return true;
        });
        ensure($valid,'The code is incorrect or expired.');
    }
    private function loginResult(User $user): array {
        if ($user->status==='unverified') {
            $old=DB::table('account_verification_tokens')->where('user_id',$user->id)->where('purpose','registration')->first();
            if (!$old || strtotime($old->created_at)<=time()-60) {
                try { app(\App\Services\AccountVerification::class)->send($user); }
                catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface) { throw new ApiError('The verification email could not be sent. Please try again shortly.',503); }
            }
            return MobileRegistration::challenge($user);
        }
        $message=match($user->status) {
            'pending_activation'=>'Activate your rider account using the email invitation before signing in.',
            'pending'=>$user->account_type==='rider'?'Your rider application is pending logistics-center approval. The decision will be sent to your email.':'Your account is still pending administrator approval. The decision will be sent to your email.',
            'interview'=>'Check your email for your interview details. Your account is not enabled yet.',
            'rejected'=>'Your account application was rejected.',
            'suspended'=>'Your account has been suspended.',
            default=>'This account is not active.',
        };
        ensure($user->status==='approved',$message,403);
        return (new Identity($user))->session();
    }
}
