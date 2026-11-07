<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Support\Str;

/** Append-only conversations. This service never changes financial state or allocations. */
class CommissionConversations
{
    public function installed(): bool {return Schema::hasTable('commission_payment_messages')&&Schema::hasColumn('commission_payments','review_decision')&&Schema::hasColumn('commission_recoveries','review_decision');}

    public function optionalMessage(array $input): ?string
    {
        $d=Validator::make($input,['message'=>'nullable|string|max:4000'])->validate();
        $message=trim($d['message']??'');
        abort_if($message!==''&&!$this->installed(),503,'Payment messaging installation is required.');
        return $message!==''?$message:null;
    }

    private function table(string $kind): string
    {
        abort_unless(in_array($kind,['payment','recovery'],true),404);
        return $kind==='payment'?'commission_payments':'commission_recoveries';
    }

    public function authorize(User $user,string $kind,string $id,bool $lock=false): array
    {
        $query=DB::table($this->table($kind))->where('id',$id);
        $record=($lock?$query->lockForUpdate():$query)->firstOrFail();
        $actor=app(CommissionPayments::class)->actor($user);
        if(!$actor->is_admin){
            $seller=$kind==='recovery'||$record->method==='gcash';
            abort_unless($seller
                ? $actor->account_type==='seller'&&(int)$record->seller_id===(int)$actor->id
                : $actor->account_type==='logistics'&&$actor->business_name===$record->company_name,403);
        }
        return [$actor,$record];
    }

    public function messages(string $kind,string $id)
    {
        return DB::table('commission_payment_messages')->where($kind.'_id',$id);
    }

    private function insert(User $actor,string $kind,object $record,string $body,string $type,?string $key,?string $submission,array $proof=[]): int
    {
        $name=trim(($actor->given_names??'').' '.($actor->last_name??''));
        $message=DB::table('commission_payment_messages')->insertGetId($proof+[
            $kind.'_id'=>$record->id,'sender_id'=>$actor->id,
            'sender_label'=>mb_substr($name?:($actor->username?:'Account #'.$actor->id),0,190),
            'sender_role'=>$actor->is_admin?'Platform Admin':($actor->account_type==='seller'?'Seller':'Logistics Admin'),
            'kind'=>$type,'body'=>$body,'submission_key'=>$submission,'event_key'=>$key,'created_at'=>now(),
        ]);
        $recipients=User::query()->where('status','approved')->where('id','!=',$actor->id);
        if(!$actor->is_admin)$recipients->where('is_admin',true);
        elseif($kind==='recovery'||$record->method==='gcash')$recipients->where('id',$record->seller_id)->where('account_type','seller')->where('is_admin',false)->where('is_logistics',false);
        else $recipients->where('account_type','logistics')->where('is_logistics',true)->where('is_admin',false)->where('logistics_role','admin')->where('business_name',$record->company_name);
        foreach($recipients->get() as $recipient){
            // Notification bodies deliberately omit payment evidence and conversation contents.
            $title=match($type){'submitted'=>'Manual commission payment submitted','corrections_requested'=>'Commission payment corrections requested','verified'=>'Commission receipt verified','rejected'=>'Commission proof rejected',default=>'New commission payment message'};
            \App\Models\Order::notifyUser($recipient->id,$title,'Open the linked payment Messages section for this update. Messages alone never verify a payment.','commission_'.$kind,$record->id);
        }
        return $message;
    }

    /** Called inside the existing financial transaction, after the underlying action succeeds. */
    public function event(User $user,string $kind,string $id,string $type,string $body,string $key): void
    {
        if(!$this->installed())return;
        DB::transaction(function()use($user,$kind,$id,$type,$body,$key){
            [$actor,$record]=$this->authorize($user,$kind,$id,true);
            if(DB::table('commission_payment_messages')->where('event_key',$key)->exists())return;
            $this->insert($actor,$kind,$record,$body,$type,$key,null);
        },3);
    }

    public function send(User $user,string $kind,string $id,array $input,?UploadedFile $file=null): void
    {
        abort_unless($this->installed(),503,'Payment messaging installation is required.');
        $d=Validator::make(array_replace($input,['proof'=>$file]),['submission_key'=>'required|uuid','message'=>'required|string|max:4000','proof'=>'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])->validate();
        DB::transaction(function()use($user,$kind,$id,$d,$file){
            [$actor,$record]=$this->authorize($user,$kind,$id,true);
            $body=trim($d['message']);
            if($body==='')throw \Illuminate\Validation\ValidationException::withMessages(['message'=>'Enter a message.']);
            $old=DB::table('commission_payment_messages')->where('submission_key',$d['submission_key'])->first();
            $hash=$file?hash_file('sha256',$file->getRealPath()):null;
            if($old){abort_unless((int)$old->sender_id===(int)$actor->id&&($old->{$kind.'_id'}??null)===$id&&$old->body===$body&&$old->proof_hash===$hash,409,'Message retry details differ.');return;}
            $proof=$file?['proof_path'=>app(MediaLifecycle::class)->store($file,'commission-proofs','local'),'proof_mime'=>$file->getMimeType(),'proof_hash'=>$hash]:[];
            $this->insert($actor,$kind,$record,$body,'message',null,$d['submission_key'],$proof);
        },3);
    }

    public function counts(array $payments,array $recoveries)
    {
        if(!$this->installed())return collect();
        return DB::table('commission_payment_messages')->where(fn($q)=>$q->whereIn('payment_id',$payments)->orWhereIn('recovery_id',$recoveries))
            ->select('payment_id','recovery_id')->selectRaw('COUNT(*) AS total')->groupBy('payment_id','recovery_id')->get()
            ->mapWithKeys(fn($r)=>[($r->payment_id?'payment:'.$r->payment_id:'recovery:'.$r->recovery_id)=>(int)$r->total]);
    }
}
