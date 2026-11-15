<?php
declare(strict_types=1);

use App\Models\User;
use App\Models\Product;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MobileMessages {
    private int $id;
    public function __construct(private Identity $identity) { $this->id=$identity->approved(); }
    private function thread(int $counterpart) {
        return DB::table('messages')->where(fn($q)=>$q->where(fn($m)=>$m->where('sender_id',$this->id)->where('receiver_id',$counterpart))
            ->orWhere(fn($m)=>$m->where('sender_id',$counterpart)->where('receiver_id',$this->id)));
    }
    private function blocked(int $other): bool {
        return DB::table('blocked_users')->where(fn($q)=>$q->where('blocker_id',$this->id)->where('blocked_id',$other))
            ->orWhere(fn($q)=>$q->where('blocker_id',$other)->where('blocked_id',$this->id))->exists();
    }
    public function handle(string $name,array $p): mixed {
        switch ($name) {
            case 'touch_my_presence': User::whereKey($this->id)->update(['last_active_at'=>now()]); return null;
            case 'fetch_my_conversations':
                $ids=DB::table('messages')->where('sender_id',$this->id)->pluck('receiver_id')->merge(DB::table('messages')->where('receiver_id',$this->id)->pluck('sender_id'))->unique();
                $rows=[];
                foreach ($ids as $other) {
                    $user=User::find($other); if (!$user) continue;
                    $last=$this->thread((int)$other)->orderByDesc('created_at')->orderByDesc('id')->first();
                    $product=$last->product_id?Product::find($last->product_id):null;
                    $rows[]=['counterpart_id'=>(int)$other,'counterpart_name'=>$user->business_name?:trim($user->given_names.' '.$user->last_name),'counterpart_role'=>$user->account_type,
                        'last_message_body'=>$last->body,'last_message_at'=>$last->created_at,'last_message_attachment_type'=>$last->attachment_type,'last_message_product_name'=>$product?->name,
                        'unread_count'=>$this->thread((int)$other)->where('receiver_id',$this->id)->where('read',false)->count(),'is_active'=>$user->status==='approved'];
                }
                usort($rows,fn($a,$b)=>strcmp($b['last_message_at'],$a['last_message_at'])); return $rows;
            case 'fetch_conversation_messages':
                $other=(int)($p['p_counterpart_id']??0);
                $rows=cleanRows($this->thread($other)->orderByDesc('id')->limit(300)->get());
                $rows=array_reverse($rows);
                foreach ($rows as &$r) {
                    $reply=$r['reply_to_id']?$this->thread($other)->where('id',$r['reply_to_id'])->first():null;
                    $r['reply_to_sender_id']=$reply?->sender_id; $r['reply_to_body']=$reply?->body; $r['reply_to_has_attachment']=(bool)$reply?->attachment_path;
                    $product=$r['product_id']?Product::find($r['product_id']):null;
                    $r['product_name']=$product?->name; $r['product_price']=$product?->price; $r['product_image']=$product?->image;
                    // Website stores reactions as JSON; preserve that representation for both clients.
                    $reactions=[];
                    foreach ($r['reactions']??[] as $key=>$value) {
                        if (is_array($value) && isset($value['user_id'],$value['emoji'])) $reactions[]=$value;
                        elseif (is_array($value)) foreach ($value as $userId) $reactions[]=['user_id'=>(int)$userId,'emoji'=>(string)$key];
                    }
                    $r['reactions']=$reactions;
                }
                return $rows;
            case 'mark_messages_read':
                $this->thread((int)($p['p_counterpart_id']??0))->where('receiver_id',$this->id)->where('read',false)->update(['read'=>true,'updated_at'=>now()]); return null;
            case 'get_conversation_presence':
                $other=(int)($p['p_counterpart_id']??0);
                if (!$this->thread($other)->exists() || $this->blocked($other)) return [];
                return [['last_active_at'=>User::find($other)?->last_active_at]];
            case 'send_message':
                if (!empty($p['p_request_id'])) return MobileRequests::run('message:'.$this->id,$p['p_request_id'],$p,fn()=>$this->send($p));
                return $this->send($p);
            case 'react_to_message':
                validateData($p,['p_message_id'=>'required|integer','p_emoji'=>'required|string|max:16']);
                return DB::transaction(function () use ($p) {
                    $m=Message::whereKey($p['p_message_id'])->where(fn($q)=>$q->where('sender_id',$this->id)->orWhere('receiver_id',$this->id))->lockForUpdate()->first();
                    ensure($m!==null,'Message access denied.',403);
                    $other=(int)($m->sender_id===$this->id?$m->receiver_id:$m->sender_id);
                    ensure(!$this->blocked($other),'This conversation is blocked.');
                    $reactions=$m->reactions??[];
                    // Same emoji => toggle off; each account may have one reaction.
                    $had=in_array($this->id,$reactions[$p['p_emoji']]??[],false);
                    foreach ($reactions as $emoji=>&$users) $users=array_values(array_filter((array)$users,fn($u)=>(int)$u!==$this->id)); unset($users);
                    if (!$had) $reactions[$p['p_emoji']][]=$this->id;
                    $m->reactions=array_filter($reactions,fn($users)=>count($users)>0); $m->save(); return null;
                });
            case 'block_user':
                $other=(int)($p['p_user_id']??0); ensure($other!==$this->id && User::whereKey($other)->exists(),'Invalid account.');
                DB::table('blocked_users')->insertOrIgnore(['id'=>(string)Str::uuid(),'blocker_id'=>$this->id,'blocked_id'=>$other,'created_at'=>now()]); return null;
            case 'unblock_user': DB::table('blocked_users')->where('blocker_id',$this->id)->where('blocked_id',$p['p_user_id']??0)->delete(); return null;
            case 'fetch_blocked_users': return cleanRows(DB::table('blocked_users')->where('blocker_id',$this->id)->selectRaw('blocked_id AS user_id')->get());
        }
        throw new ApiError('Unknown message action.',404);
    }
    private function send(array $p): mixed {
        validateData($p,['p_receiver_id'=>'required|integer','p_body'=>'nullable|string|max:1000','p_product_id'=>'nullable|uuid','p_reply_to_id'=>'nullable|integer',
            'p_attachment_path'=>'nullable|string|max:2048','p_attachment_name'=>'nullable|string|max:255']);
        $other=(int)$p['p_receiver_id']; $receiver=User::find($other);
        ensure($receiver && $receiver->status==='approved' && $other!==$this->id,'Recipient is unavailable.');
        ensure(!$this->blocked($other),'This conversation is blocked.');
        $allowed=$receiver->is_admin || $this->thread($other)->exists();
        if ($this->identity->user->account_type==='buyer') {
            $allowed=$allowed || $receiver->account_type==='seller' || DB::table('shipments')->where('courier_id',$other)->whereIn('order_id',DB::table('orders')->select('id')->where('buyer_id',$this->id))->exists();
        } elseif ($this->identity->user->account_type==='rider') {
            $allowed=$allowed || DB::table('shipments')->where('courier_id',$this->id)->whereIn('order_id',DB::table('orders')->select('id')->where('buyer_id',$other))->exists()
                || DB::table('shipments')->where('pickup_rider_id',$this->id)->whereIn('order_id',DB::table('orders')->select('id')->where('seller_id',$other))->exists()
                || ($receiver->is_logistics && $receiver->business_name===$this->identity->user->business_name);
        }
        ensure($allowed,'You cannot message this account.',403);
        ensure(trim($p['p_body']??'')!=='' || !empty($p['p_attachment_path']) || !empty($p['p_product_id']),'Write a message or attach an image or product.');
        $data=['sender_id'=>$this->id,'receiver_id'=>$other,'body'=>$p['p_body']??'','read'=>false];
        if (!empty($p['p_reply_to_id'])) { ensure($this->thread($other)->where('id',$p['p_reply_to_id'])->exists(),'Reply is outside this conversation.'); $data['reply_to_id']=$p['p_reply_to_id']; }
        if (!empty($p['p_product_id'])) { ensure(Product::whereKey($p['p_product_id'])->where('status','active')->exists(),'Product unavailable.'); $data['product_id']=$p['p_product_id']; }
        if (!empty($p['p_attachment_path'])) {
            $path=$p['p_attachment_path']; ensure(str_starts_with($path,$this->id.'/') && Storage::disk('messages')->exists($path),'Upload your own attachment first.');
            $mime=Storage::disk('messages')->mimeType($path); ensure(str_starts_with($mime,'image/'),'Only image attachments are supported.');
            $data+=['attachment_path'=>$path,'attachment_type'=>'image','attachment_name'=>basename($p['p_attachment_name']??$path),'attachment_mime'=>$mime,'attachment_size'=>Storage::disk('messages')->size($path)];
        }
        $message=Message::create($data);
        MobilePush::chat($message);
        return cleanRow($message->getAttributes());
    }
}
