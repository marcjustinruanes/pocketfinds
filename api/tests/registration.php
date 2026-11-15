<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/bootstrap.php';
foreach (['Support','Auth','GoogleIdentity','Registration'] as $file) require dirname(__DIR__)."/$file.php";
use App\Mail\RegistrationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;

$checks=0;
function check(bool $condition,string $message): void { global $checks; if(!$condition) throw new RuntimeException($message); $checks++; echo "PASS $message\n"; }
function reject(Closure $fn,string $message): void {
    try { $fn(); } catch(ApiError|Illuminate\Validation\ValidationException $e) { check(true,$message); return; }
    throw new RuntimeException('Expected rejection: '.$message);
}
function fields(string $email): array {
    do { $phone='09'.random_int(100000000,999999999); } while(User::where('contact_no',$phone)->exists());
    return ['account_type'=>'buyer','given_names'=>'Mobile Auth','last_name'=>'Fixture','email'=>$email,'contact_no'=>$phone,
        'birthday'=>'1994-03-04','password'=>'Testing-password-123!','password_confirmation'=>'Testing-password-123!','terms'=>true,
        'middle_name'=>'A','sex'=>'female','region'=>'CALABARZON','province'=>'Laguna','municipality'=>'Famy','barangay'=>'Salangbato','street'=>'Test street','house_no'=>'12',
        'documents'=>['id'=>'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=']];
}
config(['cache.default'=>'array']);
$auditStorage=sys_get_temp_dir().'/pocketfinds-auth-docs-'.bin2hex(random_bytes(8));
config(['filesystems.disks.public.root'=>$auditStorage]); \Illuminate\Support\Facades\Storage::forgetDisk('public');
DB::beginTransaction();
try {
    Mail::fake();
    $auth=new MobileAuth(new Identity(null),'');
    $tag=Str::lower(Str::random(16));
    $email="mobile-$tag@example.invalid";
    $data=fields($email);
    $data['birthday']=now()->subYears(16)->toDateString();
    reject(fn()=>MobileRegistration::register(array_replace($data,['birthday'=>now()->subYears(16)->addDay()->toDateString()])),'buyer one day below 16 is rejected by mobile age rule');
    reject(fn()=>MobileRegistration::register(array_replace($data,['birthday'=>now()->subYears(10)->toDateString()])),'underage registration follows website validation');
    reject(fn()=>MobileRegistration::register(array_replace($data,['terms'=>false])),'registration requires accepted buyer terms');
    reject(fn()=>MobileRegistration::register(array_replace($data,['account_type'=>'rider'])),'rider applications require center vehicle and license documents');
    reject(fn()=>MobileRegistration::register(array_replace($data,['documents'=>[]])),'buyer ID is required');
    $result=MobileRegistration::register($data);
    $user=User::where('email',$email)->firstOrFail();
    check($user->age===16 && $user->birthday->toDateString()===$data['birthday'],'buyer turning 16 today registers with the real birthday preserved');
    check($user->status==='unverified' && $user->email_verified_at===null,'account is saved before email verification');
    check($user->sex==='female' && $user->middle_name==='A' && $user->street==='Test street' && $user->house_no==='12' && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->id_file),'required identity address and ID document are persisted');
    check(!isset($result['access_token']) && strlen($result['registration_token'])===64,'registration cannot create an authenticated shopping session');
    Mail::assertSent(RegistrationCode::class,fn($mail)=>$mail->hasTo($email));
    $firstCode=Mail::sent(RegistrationCode::class)->last()->code;
    check(DB::table('account_verification_tokens')->where('user_id',$user->id)->where('purpose','registration')->exists(),'website verification service stores the challenge');
    reject(fn()=>MobileRegistration::verification('verify-registration',['registration_token'=>$result['registration_token'],'code'=>'xxxxxx']),'malformed code is rejected');
    reject(fn()=>MobileRegistration::verification('resend-registration',['registration_token'=>$result['registration_token']]),'resend has a server-side cooldown');
    DB::table('account_verification_tokens')->where('user_id',$user->id)->update(['created_at'=>now()->subSeconds(61)]);
    MobileRegistration::verification('resend-registration',['registration_token'=>$result['registration_token']]);
    $code=Mail::sent(RegistrationCode::class)->last()->code;
    check($code!==$firstCode,'resending issues a fresh code');
    reject(fn()=>MobileRegistration::verification('verify-registration',['registration_token'=>$result['registration_token'],'code'=>$firstCode]),'replaced code cannot verify an account');
    MobileRegistration::verification('verify-registration',['registration_token'=>$result['registration_token'],'code'=>$code]);
    $user->refresh();
    check($user->status==='pending' && $user->email_verified_at!==null,'verified buyer waits for administrator approval');
    reject(fn()=>$auth->handle('login',['email'=>$email,'password'=>$data['password']]),'verified pending buyer cannot sign in before admin approval');
    $user->update(['status'=>'approved']);
    reject(fn()=>MobileRegistration::verification('verify-registration',['registration_token'=>$result['registration_token'],'code'=>$code]),'verification token cannot be replayed');
    $login=$auth->handle('login',['email'=>$user->username,'password'=>$data['password']]);
    check(Identity::fromBearer($login['access_token'])->id()===$user->id,'username and existing password sign in');
    reject(fn()=>$auth->handle('login',['email'=>$email,'password'=>'wrong']),'wrong password does not create a session');

    $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA,'config'=>__DIR__.'/openssl.cnf']);
    if ($key===false) throw new RuntimeException('OpenSSL could not generate the temporary test key.');
    $details=openssl_pkey_get_details($key);
    $b64=fn($s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');
    Cache::forget('pocketfinds-mobile-google-keys');
    Http::fake(['https://www.googleapis.com/oauth2/v3/certs'=>Http::response(['keys'=>[['kty'=>'RSA','alg'=>'RS256','kid'=>'test-key','use'=>'sig','n'=>$b64($details['rsa']['n']),'e'=>$b64($details['rsa']['e'])]]])]);
    $claims=['iss'=>'https://accounts.google.com','aud'=>GoogleIdentity::clientId(),'sub'=>'mobile-google-'.$tag,'iat'=>time()-10,'exp'=>time()+300,'email'=>$email,'email_verified'=>true,'given_name'=>'Mobile','family_name'=>'Google'];
    $token=JWT::encode($claims,$key,'RS256','test-key');
    $googleLogin=$auth->handle('google',['id_token'=>$token,'intent'=>'login']);
    check(Identity::fromBearer($googleLogin['access_token'])->id()===$user->id,'Google signs in an existing email account like the website');
    reject(fn()=>$auth->handle('google',['id_token'=>JWT::encode(array_replace($claims,['aud'=>'wrong-client']),$key,'RS256','test-key')]),'Google token from another app is rejected');
    reject(fn()=>$auth->handle('google',['id_token'=>JWT::encode(array_replace($claims,['exp'=>time()-1]),$key,'RS256','test-key')]),'expired Google token is rejected');
    reject(fn()=>$auth->handle('google',['id_token'=>JWT::encode(array_replace($claims,['email_verified'=>false]),$key,'RS256','test-key')]),'unverified Google email is rejected');
    $googleEmail="google-$tag@example.invalid";
    $claims['email']=$googleEmail; $claims['sub']='new-google-'.$tag;
    $googleSignup=$auth->handle('google',['id_token'=>JWT::encode($claims,$key,'RS256','test-key'),'intent'=>'register']);
    check(!isset($googleSignup['access_token']) && isset($googleSignup['google_registration_token']),'new Google account completes registration before login');
    $googleFields=fields($googleEmail)+['google_registration_token'=>$googleSignup['google_registration_token']];
    $registered=MobileRegistration::register($googleFields);
    $googleUser=User::where('email',$googleEmail)->firstOrFail();
    check($googleUser->auth_method==='google' && $googleUser->google_id===$claims['sub'] && $googleUser->status==='unverified','Google signup links the verified identity and still sends website email verification');
    $resumed=$auth->handle('login',['email'=>$googleEmail,'password'=>$googleFields['password']]);
    check(isset($resumed['registration_token']) && !isset($resumed['access_token']),'unverified login resumes the code screen without granting access');
    check(count(DB::table('personal_access_tokens')->where('tokenable_id',$googleUser->id)->get())===0,'unverified accounts have no shopping session');
    \Illuminate\Support\Facades\RateLimiter::clear('mobile-application:local');
    $applicationHub=\App\Models\LogisticsHub::create(['company_name'=>'ERP application audit','municipality'=>'Famy','province'=>'Laguna','is_operational'=>true,'is_hiring'=>true,'rider_hiring_open'=>true]);
    $application=fields("applicant-$tag@example.invalid");
    $application=array_replace($application,['account_type'=>'rider','logistics_hub_id'=>$applicationHub->id,'vehicle_type'=>'two_wheels','vehicle_brand'=>'Test brand','vehicle_model'=>'Test model','id_type_id'=>DB::table('id_types')->value('id'),
        'plate_number'=>'AUD'.Str::upper(Str::random(10)),'license_number'=>'A12-34-'.random_int(100000,999999),'license_expiry'=>now()->addYear()->toDateString()]);
    $application['documents']+=['or_cr'=>$application['documents']['id'],'license'=>$application['documents']['id'],'selfie'=>$application['documents']['id']];
    // Retain internal legacy ERP validation coverage. Public registration now
    // rejects rider applications; invitation activation is the supported flow.
    $submitted=MobileApplications::register($application);
    $applicant=User::where('email',$application['email'])->firstOrFail();
    check($applicant->status==='unverified' && $applicant->logistics_hub_id===$applicationHub->id && $applicant->or_cr_file && $applicant->license_file && $applicant->selfie_file,'rider application stores center vehicle OR CR license and identity requirements');
    $applicantCode=Mail::sent(RegistrationCode::class)->last()->code;
    MobileRegistration::verification('verify-registration',['registration_token'=>$submitted['registration_token'],'code'=>$applicantCode]);
    check($applicant->fresh()->status==='pending','verified rider waits for logistics-center approval');
    reject(fn()=>$auth->handle('login',['email'=>$application['email'],'password'=>$application['password']]),'unapproved rider cannot sign in');
    $reviewer=$user->replicate(); $reviewer->forceFill(['email'=>"reviewer-$tag@example.invalid",'username'=>'reviewer_'.$tag,'contact_no'=>fields("reviewer-$tag@example.invalid")['contact_no'],'account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>$applicationHub->company_name,'status'=>'approved']); $reviewer->save();
    \Illuminate\Support\Facades\Auth::setUser($reviewer);
    $applicant->refresh(); $applicant->update(['status'=>'approved']);
    check($applicant->fresh()->status==='approved','submitted rider documents satisfy native logistics approval validation');
    \Illuminate\Support\Facades\Auth::forgetUser();
    $applicationHub->update(['rider_hiring_open'=>false,'is_hiring'=>false]);
    $closed=fields("closed-$tag@example.invalid");
    $closed=array_replace($application,['email'=>$closed['email'],'contact_no'=>$closed['contact_no'],'plate_number'=>'CLOSED'.Str::upper(Str::random(8)),'license_number'=>'B12-34-'.random_int(100000,999999)]);
    reject(fn()=>MobileRegistration::register($closed),'closed logistics hub rejects rider application');
    $hub=\App\Models\LogisticsHub::create(['company_name'=>'Mobile activation audit','municipality'=>'Famy','province'=>'Laguna','is_operational'=>true]);
    $rider=$user->replicate();
    $rider->forceFill(['email'=>"rider-$tag@example.invalid",'username'=>'rider_'.$tag,'contact_no'=>fields("rider-$tag@example.invalid")['contact_no'],
        'account_type'=>'rider','status'=>'pending_activation','email_verified_at'=>null,'business_name'=>$hub->company_name,'logistics_hub_id'=>$hub->id]);
    $rider->save(); $invitation=Str::random(64);
    DB::table('account_verification_tokens')->insert(['user_id'=>$rider->id,'purpose'=>'rider_activation','token_hash'=>hash('sha256',$invitation),'expires_at'=>now()->addHour(),'attempts'=>0,'created_at'=>now(),'updated_at'=>now()]);
    $activation=['invitation'=>"https://pocketfinds.example/rider/activate/{$rider->id}/$invitation",'password'=>'Testing-password-123!','password_confirmation'=>'Testing-password-123!'];
    reject(fn()=>$auth->handle('activate-rider',array_replace($activation,['password_confirmation'=>'different'])),'rider activation requires matching passwords');
    try { $auth->handle('activate-rider',array_replace($activation,['invitation'=>"https://pocketfinds.example/rider/activate/{$rider->id}/".str_repeat('x',64)])); throw new RuntimeException('Expected invalid invitation rejection.'); }
    catch(\Symfony\Component\HttpKernel\Exception\HttpException $error) { check($error->getStatusCode()===403,'rider activation rejects invalid invitation token'); }
    $auth->handle('activate-rider',$activation);
    check($rider->fresh()->status==='approved' && $rider->fresh()->email_verified_at && \Illuminate\Support\Facades\Hash::check($activation['password'],$rider->fresh()->password),'mobile activation uses website invitation service and saves hashed password');
    check(!DB::table('account_verification_tokens')->where('user_id',$rider->id)->where('purpose','rider_activation')->exists(),'rider invitation is consumed after activation');
    try { $auth->handle('activate-rider',$activation); throw new RuntimeException('Expected used invitation rejection.'); }
    catch(\Symfony\Component\HttpKernel\Exception\HttpException $error) { check($error->getStatusCode()===410,'used invitation cannot activate another time'); }
    $googleUser->update(['status'=>'rejected']);
    $again=MobileRegistration::register($googleFields);
    check(User::where('email',$googleEmail)->count()===1 && $googleUser->fresh()->status==='unverified' && isset($again['registration_token']),'rejected applicant can resubmit without duplicating the existing account');
    $invalid=fields("bad-document-$tag@example.invalid"); $invalid['documents']['id']=base64_encode('<html>not an ID image</html>');
    reject(fn()=>MobileRegistration::register($invalid),'unsupported document content is rejected');
    check(!User::where('email',$invalid['email'])->exists(),'invalid document never creates an application');
    \Illuminate\Support\Facades\RateLimiter::clear('mobile-application:local');
    $website=fields("website-$tag@gmail.com");$website+=['flow'=>'website','username'=>'website_'.$tag,'id_type_id'=>DB::table('id_types')->value('id'),'postal_code'=>'4021','suffix'=>'Jr.'];
    $website['given_names']='Website';$website['documents']['selfie']=$website['documents']['id'];
    reject(fn()=>MobileRegistration::register($website),'website signup requires verified contact-step email');
    $website['email_token']=\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['purpose'=>'signup-email','email'=>$website['email'],'expires'=>time()+600]));
    $registered=MobileRegistration::register($website);
    $websiteUser=User::where('email',$website['email'])->firstOrFail();
    check(($registered['submitted']??false) && $websiteUser->status==='pending' && $websiteUser->email_verified_at!==null,'website signup submits verified application directly to admin review');
    check($websiteUser->username===$website['username'] && $websiteUser->postal_code==='4021' && $websiteUser->selfie_file && $websiteUser->id_type_id,'website signup retains chosen username postal code ID type and selfie');
    check(!isset($registered['access_token']) && !isset($registered['registration_token']),'website signup does not create a session or send a duplicate post-registration challenge');
    echo "$checks authentication checks passed. Fixture rows rolled back; no real emails sent by tests.\n";
} finally {
    DB::rollBack();
    if (str_starts_with($auditStorage,sys_get_temp_dir().'/pocketfinds-auth-docs-')) {
        foreach (\Illuminate\Support\Facades\Storage::disk('public')->allFiles() as $path) \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
    }
}
