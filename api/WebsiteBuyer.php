<?php
declare(strict_types=1);
use App\Models\{AccountUpdateRequest,BuyerPaymentAccount,User,Announcement,SupportFaq};
use Illuminate\Support\Facades\{DB,RateLimiter,Cache};

/** Buyer parity with reference ca70ed1; native website remains untouched. */
final class WebsiteBuyer {
    public function __construct(private Identity $identity) {}
    public function handle(string $action,array $data): array {
        $uid=$this->identity->approved('buyer');
        $native=new MobileBuyer($this->identity);
        if ($action==='dashboard') {
            return $native->native($data,function($controller,$request) {
                $view=$controller->dashboard($request)->getData();
                return response()->json(['status_counts'=>$view['statusCounts']]);
            });
        }
        if ($action==='account') {
            $pending=AccountUpdateRequest::where('user_id',$uid)->where('status','pending')->latest()->first();
            $last=AccountUpdateRequest::where('user_id',$uid)->latest()->first();
            return ['profile'=>cleanRow($this->identity->user->fresh()->getAttributes()),'pending_request'=>$pending?->toArray(),'last_request'=>$last?->toArray(),
                'payment_accounts'=>BuyerPaymentAccount::where('buyer_id',$uid)->latest('created_at')->get()->toArray()];
        }
        if (in_array($action,['profile','address'],true)) {
            return DB::transaction(function() use($uid,$native,$action,$data) {
                User::whereKey($uid)->lockForUpdate()->firstOrFail();
                ensure(!AccountUpdateRequest::where('user_id',$uid)->where('status','pending')->exists(),'An update request is already pending administrator review.');
                if ($action==='profile') {
                    if (isset($data['contact_no'])) $data['contact_no']=\App\Support\AccountInput::phone($data['contact_no']);
                    ensure(!isset($data['contact_no']) || $data['contact_no']===$this->identity->user->contact_no,'Verify a new mobile number from Account Settings before changing it.');
                    validateData($data,['given_names'=>['required','string','min:2','max:50',"regex:/^[\p{L} .\'-]+$/u"],'last_name'=>['required','string','min:2','max:50',"regex:/^[\p{L} .\'-]+$/u"],
                        'middle_name'=>['nullable','string','min:2','max:50',"regex:/^[\p{L} .\'-]+$/u"],'contact_no'=>['nullable','regex:/^09[0-9]{9}$/']]);
                } else validateData($data,['region'=>'required|string|max:255','postal_code'=>['required','regex:/^[0-9]{4}$/D'],'province'=>'required|string|max:255','municipality'=>'required|string|max:255','barangay'=>'required|string|max:255','house_no'=>'nullable|string|max:255','street'=>'nullable|string|max:255']);
                $keys=$action==='profile'?['given_names','last_name','middle_name','contact_no']:['region','postal_code','province','municipality','barangay','house_no','street'];
                AccountUpdateRequest::create(['user_id'=>$uid,'requested_changes'=>array_intersect_key($data,array_flip($keys)),'status'=>'pending']);
                return ['submitted'=>true,'message'=>'Your changes are pending administrator review.','status'=>'pending'];
            },3);
        }
        if ($action==='payment-remove') {
            validateData($data,['id'=>'required|uuid']); $account=BuyerPaymentAccount::where('buyer_id',$uid)->find($data['id']); ensure($account!==null,'Payment account not found.',404);
            return $native->native([],fn($c,$r)=>$c->destroyPaymentAccount($account));
        }
        if (in_array($action,['payment-send-code','payment-verify'],true)) {
            $key='mobile-payment:'.$uid;
            if ($action==='payment-send-code') {
                validateData($data,['type'=>'required|in:gcash','account_name'=>'required|string|max:150','account_number'=>['required','regex:/^09[0-9]{9}$/D'],'bank_name'=>'prohibited']);
                ensure(!RateLimiter::tooManyAttempts($key.':send',1),'Wait a minute before sending another code.',429);
                RateLimiter::hit($key.':send',60); RateLimiter::clear($key.':verify');
                $result=$native->native($data,fn($c,$r)=>$c->sendPaymentAccountCode($r));
            } else {
                ensure(!RateLimiter::tooManyAttempts($key.':verify',5),'Too many incorrect codes. Request a new code.',429); RateLimiter::hit($key.':verify',600);
                $result=$native->native($data,fn($c,$r)=>$c->verifyPaymentAccountCode($r));
            }
            ensure(($result['success']??true)!==false,$result['message']??'Verification failed.'); return $result;
        }
        if ($action==='announcements') {
            $rows=Announcement::where('is_active',true)->whereIn('audience',['all','buyer'])->latest('created_at')->get()->toArray();
            DB::table('notifications')->where('user_id',$uid)->where('notification_type','announcement')->update(['is_read'=>true]);
            return ['items'=>$rows];
        }
        if ($action==='faqs') return ['items'=>SupportFaq::publishedFor('buyer')->get()->toArray()];
        if (in_array($action,['report-product','report-message'],true)) {
            $key='mobile-reports:'.$uid; ensure(!RateLimiter::tooManyAttempts($key,5),'Too many reports. Please wait.',429); RateLimiter::hit($key,60);
            $file=null; $temp=null;
            try {
                if (!empty($data['evidence_content'])) {
                    validateData($data,['evidence_content'=>'required|string|max:28000000']);
                    $bytes=base64_decode($data['evidence_content'],true); ensure($bytes!==false && strlen($bytes)<=20*1024*1024,'Evidence must be under 20 MB.');
                    $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','video/mp4'=>'mp4','video/quicktime'=>'mov','video/x-msvideo'=>'avi'][$mime]??null;
                    ensure($ext!==null,'Use a supported image or video for evidence.');
                    $temp=tempnam(sys_get_temp_dir(),'pf-evidence-'); file_put_contents($temp,$bytes);
                    $file=new \Illuminate\Http\UploadedFile($temp,'evidence.'.$ext,$mime,null,true);
                }
                return DB::transaction(fn()=>$native->native($data,function($c,$r) use($action,$file) { if ($file) $r->files->set('evidence',$file); return $action==='report-product'?$c->reportProduct($r):$c->reportMessage($r); }));
            } finally { if ($temp && is_file($temp)) unlink($temp); }
        }
        throw new ApiError('Unknown website buyer action.',404);
    }
}
