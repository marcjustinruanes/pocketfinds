<?php
declare(strict_types=1);

use App\Http\Controllers\RegisterController;
use App\Models\{LogisticsCompany,LogisticsHub,CompanyVehicle};
use Illuminate\Support\Facades\{Crypt,DB};
use Illuminate\Http\{Request,UploadedFile};

/** Mobile transport for the website's registration validator and application queue. */
final class WebsiteRegistration {
    public static function options(): array {
        $companies=LogisticsCompany::approved()->pluck('business_name')->all();
        $hubs=LogisticsHub::whereIn('company_name',$companies)->where('is_operational',true)->orderBy('company_name')->get();
        return ['vehicle_types'=>DB::table('vehicle_types')->get(['slug','name'])->map(fn($v)=>(array)$v)->all(),
            'hubs'=>$hubs->map(function($hub){
                $state=app(\App\Services\HubHiring::class)->availability($hub,'rider');
                return ['id'=>$hub->id,'company'=>$hub->company_name,'province'=>$hub->province,'location'=>trim($hub->municipality.', '.$hub->province,', '),
                    'eligible'=>$state['eligible'],'status'=>$state['status'],'remaining'=>$state['remaining'],
                    'available_vehicle_types'=>CompanyVehicle::where('logistics_hub_id',$hub->id)->riderVisible()->pluck('vehicle_type')->unique()->values()->all()];
            })->all()];
    }
    public static function register(array $data): array {
        validateData($data,['account_type'=>'required|in:buyer,rider','terms'=>'accepted','documents'=>'required|array','birthday'=>'required|date']);
        $email=mb_strtolower(trim((string)($data['email']??'')));
        $session=new \Illuminate\Session\Store('mobile-signup',new \Illuminate\Session\ArraySessionHandler(120));
        $session->start();
        if (!empty($data['google_registration_token'])) {
            try {$proof=json_decode(Crypt::decryptString($data['google_registration_token']),true);}catch(Throwable){throw new ApiError('Select your Google account again.');}
            ensure(($proof['purpose']??'')==='mobile-google-registration' && ($proof['expires']??0)>time() && ($proof['email']??'')===$email,'Select your Google account again.');
            $session->put(['google_email'=>$email,'google_id'=>$proof['google_id']]);
            $data['auth_method']='google';
        } else {
            try {$proof=json_decode(Crypt::decryptString($data['email_token']??''),true);}catch(Throwable){throw new ApiError('Verify your email on the Contact step.');}
            ensure(($proof['purpose']??'')==='signup-email' && ($proof['expires']??0)>time() && ($proof['email']??'')===$email,'Email verification expired. Verify it again.');
            $data['auth_method']='manual';
        }
        $session->put('registration_verified_email',['email'=>$email,'expires_at'=>time()+60]);
        $data['email']=$email;
        $data['municipality_psgc_code']=$data['municipality_code']??null;
        $data['age']=isset($data['birthday'])?\Carbon\Carbon::parse($data['birthday'])->age:null;
        if ($data['account_type']==='rider') {
            $hub=LogisticsHub::find($data['logistics_hub_id']??0);
            ensure($hub!==null,'Choose a logistics company and hub.');
            $data['business_name']=$hub->company_name;
            validateData($data,['company_terms'=>'accepted']);
        }
        $files=[];
        try {
            foreach (['id'=>'id_file','selfie'=>'selfie_file','license'=>'license_file','or'=>'or_file','cr'=>'cr_file','nbi'=>'nbi_clearance_file','resume'=>'resume_file'] as $kind=>$field) {
                if (empty($data['documents'][$kind])) continue;
                $bytes=is_string($data['documents'][$kind])?base64_decode($data['documents'][$kind],true):false;
                ensure($bytes!==false && strlen($bytes)>0 && strlen($bytes)<=5*1024*1024,'Each document must be at most 5 MB.');
                $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                $ext=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'][$mime]??null;
                ensure($ext!==null,'Use JPEG, PNG or PDF documents.');
                $path=tempnam(sys_get_temp_dir(),'pf-signup-');
                ensure($path!==false,'Could not prepare document.',503);
                file_put_contents($path,$bytes);
                $files[$field]=new UploadedFile($path,$kind.'.'.$ext,$mime,null,true);
            }
            unset($data['documents'],$data['google_id']);
            $request=Request::create('/register','POST',$data,[], $files,['HTTP_ACCEPT'=>'application/json']);
            $request->setLaravelSession($session);
            $oldRequest=app('request'); $oldSession=app('session'); $oldDriver=config('session.driver');
            $manager=new class(app(),$session) extends \Illuminate\Session\SessionManager {
                public function __construct($app,private \Illuminate\Session\Store $registrationSession){parent::__construct($app);}
                public function driver($driver=null){return $this->registrationSession;}
            };
            try {
                config(['session.driver'=>'mobile-registration']);
                app()->instance('request',$request); app()->instance('session',$manager);
                $response=app(RegisterController::class)->store($request);
                $result=$response->getData(true);
                ensure(($result['success']??false)===true,$result['message']??'Registration could not be completed.',$response->getStatusCode()>=400?$response->getStatusCode():422);
                return ['submitted'=>true,'status'=>'pending','email'=>$email,'approval_required'=>true];
            } finally {app()->instance('request',$oldRequest);app()->instance('session',$oldSession);config(['session.driver'=>$oldDriver]);}
        } finally {foreach($files as $file)if(is_file($file->getPathname()))unlink($file->getPathname());}
    }
}
