<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Models\User;

final class MobileMedia {
    public function __construct(private Identity $identity) {}
    public static function path(string $path): string {
        ensure($path!=='' && !str_contains($path,'\\') && !preg_match('/(^|\/)\.{1,2}(\/|$)|[\x00-\x1f:]/',$path),'Invalid media path.');
        return $path;
    }
    public function upload(array $data): array {
        ensure($this->identity->user || $this->identity->registration,'Sign in before uploading.',401);
        if ($this->identity->user) $this->identity->approved();
        validateData($data,['bucket'=>'required|in:profile-images,identity-documents,review-media,payment-proofs,messages,return-evidence,rider-documents,delivery-proofs','path'=>'required|string|max:2048','content'=>'required|string|max:28000000']);
        $bucket=$data['bucket']; $path=self::path($data['path']);
        ensure(str_starts_with($path,$this->identity->owner().'/'),'You cannot upload another account’s files.',403);
        ensure($this->identity->user || $bucket==='identity-documents','Finish registration before uploading here.',403);
        $content=base64_decode($data['content'],true);
        ensure($content!==false && strlen($content)>0 && strlen($content)<=20*1024*1024,'File must be smaller than 20 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($content);
        ensure(strlen($content)<=5*1024*1024 && in_array($mime,$bucket==='rider-documents'?['image/jpeg','image/png','application/pdf']:['image/jpeg','image/png'],true),'Use a supported file of at most 5 MB (JPG/PNG; PDF also allowed for rider documents).');
        if(in_array($bucket,['rider-documents','delivery-proofs'],true)) $this->identity->approved('rider');
        $allowed=['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp'],'image/gif'=>['gif'],'image/heic'=>['heic'],'image/heif'=>['heif']];
        if ($bucket==='review-media') $allowed+=['video/mp4'=>['mp4','m4v'],'video/quicktime'=>['mov']];
        if ($bucket==='rider-documents') $allowed+=['application/pdf'=>['pdf']];
        if ($bucket==='delivery-proofs') {
            $token=pathinfo($path,PATHINFO_FILENAME);
            $shipment=Illuminate\Support\Facades\Cache::get(RiderWebsiteDelivery::captureKey($this->identity->id(),$token));
            ensure($shipment && DB::table('shipments')->where('id',$shipment)->where('courier_id',$this->identity->id())->where('shipping_status','out_for_delivery')->exists(),'Open the delivery camera again to capture fresh proof.');
            ensure(!($data['upsert']??false),'Delivery evidence cannot be overwritten.');
        }
        $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
        ensure(isset($allowed[$mime]) && in_array($ext,$allowed[$mime],true),'Unsupported file type. Use a JPEG, PNG or WebP image.');
        [$disk,$stored]=$this->location($bucket,$path);
        if(in_array($bucket,['delivery-proofs','rider-documents','payment-proofs'],true) && Storage::disk($disk)->exists($stored)) {
            ensure(hash_equals(hash('sha256',Storage::disk($disk)->get($stored)),hash('sha256',$content)),'Delivery proof cannot be replaced.',409);
            return ['path'=>$path];
        }
        ensure(($data['upsert']??false) || !Storage::disk($disk)->exists($stored),'This file already exists.');
        ensure(Storage::disk($disk)->put($stored,$content),'Could not save this file.',500);
        return ['path'=>$path];
    }
    private function location(string $bucket,string $path): array {
        if($bucket==='rider-documents' && in_array(basename($path),['legacy-license','legacy-or-cr'],true)) {
            $uid=(int)explode('/',$path)[0];$field=basename($path)==='legacy-license'?'license_file':'or_cr_file';
            $approved=DB::table('mobile_rider_vehicle_requests')->where('rider_id',$uid)->where('status','approved')->latest('decided_at')->first();
            if($approved){$payload=json_decode($approved->payload,true);return ['local','rider-documents/'.$payload[$field]];}
            $file=User::find($uid)?->$field;ensure(is_string($file) && $file!=='','Document unavailable.',404);return ['public',$file];
        }
        return match($bucket) {
            'messages'=>['messages',$path], 'profile-images'=>['profile_images',$path],
            'products'=>['public',$path], 'payment-proofs'=>['local','payment-proofs/'.$path],
            'identity-documents'=>['public','identity-documents/'.$path],
            'review-media'=>['public',str_starts_with($path,'review-media/')?$path:'review-media/'.$path],
            'return-evidence'=>['local',str_starts_with($path,'return-evidence/')?$path:'return-evidence/'.$path],
            'rider-documents'=>['local','rider-documents/'.$path],
            'delivery-proofs'=>['sensitive_documents',str_starts_with($path,'delivery_proofs/')?$path:'delivery_proofs/pending/'.$path],
            default=>throw new ApiError('Media bucket not found.',404),
        };
    }
    public function serve(string $bucket,string $path): never {
        $path=self::path($path);
        if (in_array($bucket,['identity-documents','payment-proofs','messages','return-evidence','rider-documents','delivery-proofs'],true)) {
            $uid=$this->identity->approved();
            $owner=str_starts_with($path,$uid.'/');
            if ($bucket==='messages') $owner=$owner || DB::table('messages')->where('attachment_path',$path)->where(fn($q)=>$q->where('sender_id',$uid)->orWhere('receiver_id',$uid))->exists();
            if ($bucket==='payment-proofs') $owner=$owner || $this->identity->user->is_admin || DB::table('payment_verifications')->where('proof_path',$path)->whereIn('order_id',DB::table('orders')->select('id')->where('seller_id',$uid)->when(Illuminate\Support\Facades\Schema::hasColumn('orders','platform_payment'),fn($q)=>$q->where('platform_payment',false)))->exists();
            if ($bucket==='return-evidence') $owner=$owner || DB::table('commerce_returns')->whereJsonContains('photos',$path)->where(fn($q)=>$q->where('buyer_id',$uid)->orWhere('seller_id',$uid)->when($this->identity->user->is_admin,fn($q)=>$q->orWhereRaw('1=1')))->exists();
            if($bucket==='delivery-proofs') $owner=DB::table('shipments as s')->join('orders as o','o.id','=','s.order_id')->where('s.delivery_proof_file',$path)->where(fn($q)=>$q->where('o.buyer_id',$uid)->orWhere('o.seller_id',$uid)->orWhere('s.courier_id',$uid))->exists();
            if($bucket==='rider-documents') {
                $rider=User::find((int)explode('/',$path)[0]);
                $owner=$owner || ($this->identity->user?->is_logistics && $rider?->business_name===$this->identity->user->business_name) || $this->identity->user?->is_admin;
            }
            ensure($owner,'You cannot read this file.',403);
        }
        if ($bucket==='products') ensure(in_array(explode('/',$path)[0],['product_images','images','profiles','profile-pictures','hero_banners','logistics_logos'],true),'Public media not found.',404);
        [$disk,$stored]=$this->location($bucket,$path);
        ensure(Storage::disk($disk)->exists($stored),'File not found.',404);
        $absolute=Storage::disk($disk)->path($stored);
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($absolute);
        ensure(str_starts_with($mime,'image/') || str_starts_with($mime,'video/') || ($bucket==='rider-documents' && $mime==='application/pdf'),'Unsupported media.',404);
        header('Content-Type: '.$mime); header('X-Content-Type-Options: nosniff');
        header('Content-Length: '.filesize($absolute)); header('Cache-Control: private, max-age=300');
        readfile($absolute); exit;
    }
    public static function mediaIdentity(string $ticket): Identity {
        try { $data=json_decode(Crypt::decryptString($ticket),true); } catch (Throwable) { throw new ApiError('Media link expired.',401); }
        ensure(is_array($data) && ($data['expires']??0)>time(),'Media link expired.',401);
        $hash=$data['token_hash']??'';
        $active=DB::table('personal_access_tokens')->where('token',$hash)->where('tokenable_id',$data['user_id']??0)->where('name','pocketfinds-app')->where('expires_at','>',now())->exists()
            || DB::table('mobile_registration_tokens')->where('token_hash',$hash)->where('user_id',$data['user_id']??0)->where('expires_at','>',now())->exists();
        ensure($active,'Media session expired.',401);
        return new Identity(User::find($data['user_id']??0));
    }
}
