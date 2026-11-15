<?php
declare(strict_types=1);

use App\Models\{User,LogisticsHub};
use App\Services\{AccountVerification,HubHiring,MediaLifecycle};
use App\Support\AccountInput;
use Illuminate\Support\Facades\{DB,Crypt,RateLimiter,Hash};
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/** Required ERP applications; native users, review queues and verification mail. */
final class MobileApplications {
    public static function register(array $data): array {
        $rate='mobile-application:'.($_SERVER['REMOTE_ADDR']??'local');
        ensure(!RateLimiter::tooManyAttempts($rate,8),'Too many applications. Please wait a minute.',429); RateLimiter::hit($rate,60);
        $role=$data['account_type']??'buyer'; $data['account_type']=$role;
        $data['email']=mb_strtolower(trim((string)($data['email']??'')));
        $phone=trim((string)($data['contact_no']??''));
        $data['contact_no']=AccountInput::phone($phone);
        $existing=User::whereRaw('LOWER(email) = ?',[$data['email']])->first();
        $returning=$existing && $existing->status==='rejected' && $existing->account_type===$role && is_string($data['password']??null) && Hash::check($data['password'],$existing->password??'');
        $ignore=$returning?$existing->id:null;
        $websiteFlow=$role==='buyer' && ($data['flow']??'')==='website';
        $rules=['account_type'=>'required|in:buyer,rider','given_names'=>AccountInput::name(),'last_name'=>AccountInput::name(),
            'middle_name'=>'nullable|string|max:50','sex'=>'required|in:male,female',
            'email'=>['required','email','max:255',Rule::unique('users','email')->ignore($ignore)],
            'contact_no'=>['required','regex:/\A09[0-9]{9}\z/',Rule::unique('users','contact_no')->ignore($ignore)],
            'birthday'=>'required|date|before_or_equal:'.now()->subYears($role==='rider'?18:16)->toDateString(),
            'province'=>'required|string|max:255','municipality'=>'required|string|max:255','barangay'=>'required|string|max:255',
            'region'=>'nullable|string|max:255','street'=>'required|string|max:255','house_no'=>'nullable|string|max:100',
            'password'=>AccountInput::password(),'terms'=>'accepted','documents'=>'required|array','documents.id'=>'required|string|max:7100000'];
        if ($role==='rider') $rules += ['logistics_hub_id'=>'required|integer|exists:logistics_hubs,id','vehicle_type'=>'required|in:two_wheels,four_wheels',
            'plate_number'=>['required','string','max:20',Rule::unique('users','plate_number')->ignore($ignore)],
            'license_number'=>['required','regex:/\A[A-Z][0-9]{2}-[0-9]{2}-[0-9]{6}\z/',Rule::unique('users','license_number')->ignore($ignore)],
            'license_expiry'=>'required|date|after_or_equal:today','vehicle_brand'=>'required|string|max:100','vehicle_model'=>'required|string|max:100',
            'id_type_id'=>'required|integer|exists:id_types,id','documents.selfie'=>'required|string|max:7100000',
            'documents.or_cr'=>'required|string|max:7100000','documents.license'=>'required|string|max:7100000'];
        if ($websiteFlow) $rules += ['username'=>['required','string','min:8','max:30','alpha_dash',Rule::unique('users','username')->ignore($ignore)],
            'suffix'=>['nullable',Rule::enum(\App\Enums\Suffix::class)],'postal_code'=>['required','regex:/^[0-9]{4}$/D'],
            'id_type_id'=>'required|integer|exists:id_types,id','documents.selfie'=>'required|string|max:7100000'];
        $valid=validateData($data,$rules);
        $google=null;
        if (!empty($data['google_registration_token'])) {
            try { $google=json_decode(Crypt::decryptString($data['google_registration_token']),true); } catch(Throwable) { throw new ApiError('Select your Google account again.'); }
            ensure(($google['purpose']??'')==='mobile-google-registration' && ($google['expires']??0)>time() && strcasecmp($google['email']??'',$data['email'])===0,'Select your Google account again.');
        }
        if ($websiteFlow && !$google) {
            try { $proof=json_decode(Crypt::decryptString($data['email_token']??''),true); } catch(Throwable) { throw new ApiError('Verify your email on the Contact step.'); }
            ensure(($proof['purpose']??'')==='signup-email' && ($proof['expires']??0)>time() && ($proof['email']??'')===$data['email'],'Email verification expired or the address changed. Verify it again.');
        }
        ensure(config('mail.default')==='smtp','Email delivery is not configured.',503);
        $files=[];
        try {
            foreach (['id',...($role==='rider'?['or_cr','license','selfie']:($websiteFlow?['selfie']:[]))] as $kind) {
                $bytes=base64_decode($valid['documents'][$kind],true);
                ensure($bytes!==false && strlen($bytes)>0 && strlen($bytes)<=5*1024*1024,'Each document must be at most 5 MB.');
                $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                $ext=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'][$mime]??null;
                ensure($ext!==null,'Documents must be JPEG, PNG or PDF.');
                $path=tempnam(sys_get_temp_dir(),'pf-app-'); ensure($path!==false,'Could not prepare document.',503);
                file_put_contents($path,$bytes);
                $files[$kind]=new UploadedFile($path,$kind.'.'.$ext,$mime,null,true);
            }
            return DB::transaction(function() use($valid,$role,$google,$files,$existing,$returning,$websiteFlow) {
                $hub=null;
                if ($role==='rider') {
                    $hub=LogisticsHub::whereKey($valid['logistics_hub_id'])->lockForUpdate()->firstOrFail();
                    ensure(app(HubHiring::class)->availability($hub,'rider')['eligible'],'This center is not accepting rider applications.');
                }
                $values=array_intersect_key($valid,array_flip(['given_names','last_name','middle_name','suffix','sex','email','contact_no','birthday','region','postal_code','province','municipality','barangay','street','house_no','password','vehicle_type','vehicle_brand','vehicle_model','id_type_id','plate_number','license_number','license_expiry']));
                $media=app(MediaLifecycle::class);
                $values += ['account_type'=>$role,'status'=>'unverified','status_reason'=>null,'email_verified_at'=>null,'age'=>\Carbon\Carbon::parse($valid['birthday'])->age,
                    'username'=>$returning?$existing->username:'pf_'.Str::lower(Str::random(20)), 'id_file'=>$media->store($files['id'],'registration','public')];
                if ($hub) $values += ['logistics_hub_id'=>$hub->id,'business_name'=>$hub->company_name,'vehicle_ownership'=>'own',
                    'or_cr_file'=>$media->store($files['or_cr'],'rider-documents','public'),'license_file'=>$media->store($files['license'],'rider-documents','public'),
                    'selfie_file'=>$media->store($files['selfie'],'rider-documents','public')];
                if ($hub) { $values['or_file']=$values['or_cr_file']; $values['cr_file']=$values['or_cr_file']; }
                if ($google) $values += ['auth_method'=>'google','google_id'=>$google['google_id']];
                if ($websiteFlow) {
                    $values['username']=$valid['username']; $values['status']='pending'; $values['email_verified_at']=now(); $values['business_name']=null;
                    $values['selfie_file']=$media->store($files['selfie'],'selfie_files','public');
                    $user=app(HubHiring::class)->register($values,null);
                    return ['submitted'=>true,'status'=>'pending','email'=>$user->email,'approval_required'=>true];
                }
                $user=$returning?User::whereKey($existing->id)->lockForUpdate()->firstOrFail():new User;
                ensure(!$returning || $user->status==='rejected','This application changed. Refresh before resubmitting.');
                $user->fill($values)->save();
                app(AccountVerification::class)->send($user);
                return MobileRegistration::challenge($user);
            });
        } finally { foreach ($files as $file) if (is_file($file->getPathname())) unlink($file->getPathname()); }
    }
}
