<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$_SERVER['REQUEST_URI']='/auth/register-buyer';
require dirname(__DIR__).'/bootstrap.php';
foreach(['Support','Registration'] as $file)require dirname(__DIR__)."/$file.php";
use Illuminate\Support\Facades\{DB,Mail,Storage,Crypt};
use Illuminate\Support\Str;
use App\Models\User;
$checks=0;
function check(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function denied(Closure $fn,string $message):void{try{$fn();}catch(ApiError|Illuminate\Validation\ValidationException $e){check(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
config(['cache.default'=>'array']);
Mail::fake();Storage::fake('sensitive_documents');Storage::fake('public');
DB::beginTransaction();
try {
    $tag=Str::lower(Str::random(12));$email='signup-'.$tag.'@gmail.com';
    $imageFile=\Illuminate\Http\UploadedFile::fake()->image('identity.jpg',100,100);
    $image=base64_encode(file_get_contents($imageFile->getPathname()));
    $data=['request_id'=>(string)Str::uuid(),'flow'=>'website','account_type'=>'buyer','username'=>'signup_'.$tag,'given_names'=>'Registration','last_name'=>'Fixture','middle_name'=>null,'no_middle_name'=>true,
        'email'=>$email,'contact_no'=>'09'.random_int(100000000,999999999),'birthday'=>now()->subYears(16)->toDateString(),'sex'=>'female','region'=>'CALABARZON','province'=>'Laguna','municipality'=>'Famy','barangay'=>'Salangbato','house_no'=>'12','street'=>'Test Street','postal_code'=>'4020',
        'password'=>'Testing-password-123!','password_confirmation'=>'Testing-password-123!','terms'=>true,'documents'=>['id'=>$image,'selfie'=>$image],
        'id_type_id'=>DB::table('id_types')->value('id'),'email_token'=>Crypt::encryptString(json_encode(['purpose'=>'signup-email','email'=>$email,'expires'=>time()+600]))];
    denied(fn()=>MobileRegistration::register(array_replace($data,['birthday'=>now()->subYears(16)->addDay()->toDateString()])),'website rejects buyers below 16');
    denied(fn()=>MobileRegistration::register(array_replace($data,['email_token'=>'invalid'])),'email proof cannot be forged');
    denied(fn()=>MobileRegistration::register(array_replace($data,['documents'=>['id'=>$image]])),'website requires a selfie');
    $result=MobileRegistration::register($data);
    $buyer=User::where('email',$email)->firstOrFail();
    check($result['submitted'] && $buyer->status==='pending','buyer enters website pending review queue');
    check($buyer->username===$data['username'] && $buyer->postal_code==='4020','website preserves username and address');
    check(Storage::disk('sensitive_documents')->exists($buyer->id_file),'identity evidence uses website private storage');
    check(MobileRegistration::register($data)==$result,'submission replay does not create a second account');
    $options=WebsiteRegistration::options();
    check(count($options['vehicle_types'])>0,'vehicle types come from website records');
    $hub=collect($options['hubs'])->firstWhere('eligible',true);
    if($hub){
        $email='rider-'.$tag.'@gmail.com';
        $rider=array_replace($data,['request_id'=>(string)Str::uuid(),'account_type'=>'rider','username'=>'rider_'.$tag,'given_names'=>'Courier','birthday'=>'1990-03-04','email'=>$email,'contact_no'=>'09'.random_int(100000000,999999999),
            'logistics_hub_id'=>$hub['id'],'company_terms'=>true,'vehicle_type'=>$options['vehicle_types'][0]['slug'],'vehicle_ownership'=>'own','vehicle_brand'=>'Test','vehicle_model'=>'Test model','plate_number'=>'T'.strtoupper(substr($tag,0,10)),
            'license_number'=>'Z'.random_int(10,99).'-'.random_int(10,99).'-'.random_int(100000,999999),'license_expiry'=>now()->addYear()->toDateString(),'documents'=>['selfie'=>$image,'license'=>$image,'nbi'=>$image,'or'=>$image,'cr'=>$image],
            'email_token'=>Crypt::encryptString(json_encode(['purpose'=>'signup-email','email'=>$email,'expires'=>time()+600]))]);
        denied(fn()=>MobileRegistration::register(array_replace($rider,['company_terms'=>false])),'rider must accept company terms');
        denied(fn()=>MobileRegistration::register(array_replace($rider,['documents'=>['selfie'=>$image,'license'=>$image,'or'=>$image,'cr'=>$image]])),'website requires NBI clearance');
        denied(fn()=>MobileRegistration::register(array_replace($rider,['license_expiry'=>now()->addWeek()->toDateString()])),'website enforces one month license validity');
        $result=MobileRegistration::register($rider);$user=User::where('email',$email)->firstOrFail();
        check($result['submitted'] && $user->status==='pending' && $user->business_name===$hub['company'] && $user->logistics_hub_id===$hub['id'],'rider application shares website company and hub review');
        check($user->id_file===$user->license_file && $user->nbi_clearance_file && $user->or_file && $user->cr_file,'license serves as rider identity and required documents are shared');
        check(DB::table('hiring_application_history')->where('user_id',$user->id)->exists(),'rider is recorded in native hiring application history');
        $admin=User::where('business_name',$hub['company'])->where('account_type','logistics')->where('is_logistics',true)->where('is_admin',false)->where('status','approved')->where(fn($q)=>$q->whereNull('logistics_role')->orWhere('logistics_role','admin'))->first();
        if($admin){
            $token=bin2hex(random_bytes(32));
            DB::table('rider_account_activations')->insert(['user_id'=>$user->id,'invited_by'=>$admin->id,'company_name'=>$user->business_name,'email'=>$user->email,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addDay(),'delivery_status'=>'sent','created_at'=>now(),'updated_at'=>now()]);
            $activation=['invitation'=>'https://pocketfindsph.shop/rider/activate/'.$token,'password'=>'Changed-password-123!','password_confirmation'=>'Changed-password-123!'];
            denied(fn()=>MobileRegistration::activateRider(array_replace($activation,['password_confirmation'=>'wrong'])),'activation requires matching passwords');
            check(MobileRegistration::activateRider($activation)['success'] && $user->fresh()->status==='approved','website token activates the shared rider account');
            denied(fn()=>MobileRegistration::activateRider($activation),'website activation token can only be used once');
        }
    }
    echo "$checks website registration checks passed. No real mail sent.\n";
} finally {DB::rollBack();echo "Fixtures rolled back.\n";}
