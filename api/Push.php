<?php
declare(strict_types=1);
use Illuminate\Support\Facades\{DB,Http};

final class MobilePush {
    /** Bridge web-created offers into the existing notification/outbox system. */
    public static function assignmentNotifications(): void {
        foreach(DB::table('delivery_assignments')->where('status','offered')->pluck('id') as $id) DB::transaction(function()use($id){
            $a=DB::table('delivery_assignments')->where('id',$id)->lockForUpdate()->first();
            if(!$a || $a->status!=='offered')return;
            $order=DB::table('shipments')->where('id',$a->shipment_id)->value('order_id');
            if(!$order || DB::table('notifications')->where('user_id',$a->courier_id)->where('notification_type','delivery_assigned')->where('reference_id',$order)->where('created_at','>=',$a->requested_at)->exists())return;
            App\Models\Order::notifyUser($a->courier_id,'New '.ucfirst($a->leg).' Offer','Accept the '.$a->leg.' offer when you are ready.','delivery_assigned',$order);
        });
    }
    private static function offer(int $rider,string $reference,string $type): ?object {
        return DB::table('delivery_assignments as a')->join('shipments as s','s.id','=','a.shipment_id')
            ->where('a.courier_id',$rider)->where('a.status','offered')
            ->where($type==='delivery_assigned'?'s.order_id':'a.shipment_id',$reference)->first(['a.*']);
    }
    public static function device(Identity $identity,array $data): array {
        $uid=$identity->approved();
        validateData($data,['token'=>'required|string|min:20|max:255','platform'=>'required|in:android,ios','enabled'=>'required|boolean']);
        if (!$data['enabled']) DB::table('mobile_push_devices')->where('token',$data['token'])->where('user_id',$uid)->delete();
        else {
            $existing=DB::table('mobile_push_devices')->where('token',$data['token'])->first();
            $values=['user_id'=>$uid,'platform'=>$data['platform'],'session_hash'=>$identity->token?->token,'updated_at'=>now()];
            if ($existing && (int)$existing->user_id===$uid && $existing->session_hash===$values['session_hash']) unset($values['updated_at']);
            DB::table('mobile_push_devices')->updateOrInsert(['token'=>$data['token']],$values);
        }
        return ['saved'=>true];
    }
    public static function queue(): int {
        self::assignmentNotifications();
        $queued=0;
        foreach (DB::table('mobile_push_devices as d')->join('users as u','u.id','=','d.user_id')->where('u.status','approved')->where('u.notify_enabled',true)->get(['d.*']) as $device) {
            foreach (DB::table('notifications')->where('user_id',$device->user_id)->where('created_at','>=',$device->updated_at)->where('created_at','>=',now()->subDay())->get() as $n) {
                $payload=['title'=>$n->title,'body'=>$n->message,'type'=>$n->notification_type,'reference_id'=>$n->reference_id??'','notification_id'=>$n->id];
                if(in_array($n->notification_type,['delivery_assigned','rider_offer'],true)) {
                    $offer=self::offer((int)$device->user_id,(string)$n->reference_id,$n->notification_type);
                    if(!$offer)continue;$payload['expires_at']=$offer->expires_at;
                }
                $queued+=DB::table('mobile_push_outbox')->insertOrIgnore(['notification_id'=>$n->id,'device_token'=>$device->token,'user_id'=>$device->user_id,'payload'=>json_encode($payload),'created_at'=>now()]);
            }
        }
        return $queued;
    }
    public static function chat(object $message): void {
        DB::transaction(function() use($message) {
            $inserted=DB::table('mobile_message_alerts')->insertOrIgnore(['message_id'=>$message->id,'created_at'=>now()]);
            if (!$inserted) return;
            $sender=\App\Models\User::find($message->sender_id);
            $name=$sender?->business_name?:trim($sender?->given_names.' '.$sender?->last_name);
            \App\Models\Order::notifyUser($message->receiver_id,$name?:'New message',mb_substr(trim($message->body??'')?:'Photo or product message',0,120),'chat',(string)$message->sender_id);
        });
    }
    public static function scanChats(): void {
        $users=DB::table('mobile_push_devices')->pluck('user_id');
        foreach (DB::table('messages')->whereIn('receiver_id',$users)->where('created_at','>=',now()->subDay())->whereNotIn('id',DB::table('mobile_message_alerts')->select('message_id'))->orderBy('id')->limit(500)->get() as $message) self::chat($message);
    }
    public static function dispatch(): array {
        $path=getenv('FIREBASE_SERVICE_ACCOUNT');
        if (!$path || !is_file($path)) return ['configured'=>false,'sent'=>0];
        $credentials=json_decode(file_get_contents($path),true);
        ensure(!empty($credentials['project_id']) && !empty($credentials['private_key']) && !empty($credentials['client_email']),'Firebase service account configuration is incomplete.',503);
        $encode=fn($data)=>rtrim(strtr(base64_encode(json_encode($data)),'+/','-_'),'=');
        $now=time();$input=$encode(['alg'=>'RS256','typ'=>'JWT']).'.'.$encode(['iss'=>$credentials['client_email'],'scope'=>'https://www.googleapis.com/auth/firebase.messaging','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600]);
        ensure(openssl_sign($input,$signature,$credentials['private_key'],OPENSSL_ALGO_SHA256),'Firebase signing could not be initialized.',503);
        $assertion=$input.'.'.rtrim(strtr(base64_encode($signature),'+/','-_'),'=');
        $token=Http::timeout(15)->asForm()->post('https://oauth2.googleapis.com/token',['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$assertion]);
        ensure($token->successful() && $token->json('access_token'),'Firebase authentication failed. Check server credentials.',503);
        $sent=0;
        foreach (DB::table('mobile_push_outbox')->whereNull('sent_at')->where('attempts','<',5)->orderBy('created_at')->limit(100)->get() as $pending) {
            DB::transaction(function() use($pending,$credentials,$token,&$sent) {
                $q=DB::table('mobile_push_outbox')->where('notification_id',$pending->notification_id)->where('device_token',$pending->device_token);
                $row=(clone $q)->lockForUpdate()->first();if ($row->sent_at) return;
                $device=DB::table('mobile_push_devices')->where('token',$row->device_token)->where('user_id',$row->user_id)->first();
                if (!DB::table('users')->where('id',$row->user_id)->where('status','approved')->where('notify_enabled',true)->exists()) {
                    $q->update(['sent_at'=>now(),'last_error'=>'Notifications disabled.']);return;
                }
                $session=$device && DB::table('personal_access_tokens')->where('token',$device->session_hash)->where('tokenable_id',$row->user_id)->where('name','pocketfinds-app')->where('expires_at','>',now())->exists();
                if (!$device || !$session) { $q->update(['sent_at'=>now(),'last_error'=>'Device removed or signed out.']);return; }
                $data=json_decode($row->payload,true);$message=['token'=>$row->device_token,'notification'=>['title'=>$data['title'],'body'=>$data['body']],
                    'data'=>array_map(fn($v)=>(string)$v,$data),'android'=>['priority'=>'high','notification'=>['tag'=>$row->notification_id,'sound'=>'default']]];
                if(in_array($data['type']??'',['delivery_assigned','rider_offer'],true)) {
                    if(!self::offer((int)$row->user_id,(string)$data['reference_id'],$data['type'])){$q->update(['sent_at'=>now(),'last_error'=>'Offer is no longer pending.']);return;}

                }
                $q->increment('attempts');
                try {
                    $response=Http::timeout(15)->withToken($token->json('access_token'))->post('https://fcm.googleapis.com/v1/projects/'.rawurlencode($credentials['project_id']).'/messages:send',['message'=>$message]);
                    if ($response->successful()) { $q->update(['sent_at'=>now(),'last_error'=>null]);$sent++; }
                    else {$q->update(['last_error'=>'FCM HTTP '.$response->status()]);if(in_array($response->status(),[404,410],true))DB::table('mobile_push_devices')->where('token',$row->device_token)->delete();}
                } catch(Throwable) { $q->update(['last_error'=>'Connection failed; delivery acknowledgement unavailable.']); }
            });
        }
        return ['configured'=>true,'sent'=>$sent];
    }
}
