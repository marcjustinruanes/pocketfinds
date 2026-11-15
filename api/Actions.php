<?php
declare(strict_types=1);

use App\Models\User;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\DeliveryAssignment;
use App\Models\Product;
use App\Services\LogisticsAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MobileActions {
    public function __construct(private Identity $identity) {}
    public function handle(string $name,array $p): mixed {
        if ($name==='check_email_available') {
            validateData($p,['p_email'=>'required|email']);
            return !User::where('email',$p['p_email'])->exists();
        }
        if ($name==='check_username_available') {
            validateData($p,['p_username'=>'required|alpha_dash|max:255']);
            return !User::where('username',$p['p_username'])->when($this->identity->user,fn($q)=>$q->where('id','!=',$this->identity->user->id))->exists();
        }
        if (in_array($name,['list_shops','get_shop_stats','fetch_product_reviews','get_product_rating_summary','find_visually_similar_products'],true)) return $this->publicAction($name,$p);
        $id=$this->identity->id();
        if (in_array($name,['get_my_profile','complete_registration'],true)) return cleanRow($this->identity->user->getAttributes());
        $this->identity->approved();
        if (str_contains($name,'conversation') || in_array($name,['send_message','react_to_message','touch_my_presence','block_user','unblock_user','fetch_blocked_users','mark_messages_read'],true)) return (new MobileMessages($this->identity))->handle($name,$p);
        switch ($name) {
            case 'my_legacy_user_id': return $id;
            case 'get_my_rider_profile':
                $this->identity->approved('rider');
                return ['user_id'=>$id]+cleanRow($this->identity->user->getAttributes());
            case 'set_online_status':
                $this->identity->approved('rider');
                if (MobileRider::installed()) return (new MobileRider($this->identity))->handle('presence',['online'=>$p['p_online']??false,'latitude'=>$p['p_latitude']??null,'longitude'=>$p['p_longitude']??null]);
                validateData($p,['p_online'=>'required|boolean']);
                $this->identity->user->forceFill(['is_available'=>(bool)$p['p_online'],'availability_updated_at'=>now()])->save(); return null;
            case 'update_my_profile':
            case 'update_my_profile_details':
            case 'update_my_username':
                return $this->profile($name,$p);
            case 'deactivate_my_account':
                $this->identity->user->forceFill(['status'=>'suspended'])->save();
                DB::table('personal_access_tokens')->where('tokenable_type',User::class)->where('tokenable_id',$id)->delete(); return null;
            case 'fetch_admin_contact':
                return cleanRows(DB::table('users')->where('is_admin',true)->where('status','approved')->selectRaw("id AS admin_id, CONCAT(given_names, ' ', last_name) AS admin_name")->limit(1)->get());
            case 'submit_complaint':
                validateData($p,['p_type'=>'required|string|max:50','p_subject'=>'required|string|max:255','p_description'=>'nullable|string|max:5000']);
                DB::table('complaints')->insert(['id'=>(string)Str::uuid(),'complainant_id'=>$id,'complaint_type'=>$p['p_type'],'subject'=>$p['p_subject'],'description'=>$p['p_description']??null,'status'=>'pending','created_at'=>now()]); return null;
            case 'fetch_my_notifications': return cleanRows(DB::table('notifications')->where('user_id',$id)->orderByDesc('created_at')->limit(200)->get());
            case 'unread_notification_count': return DB::table('notifications')->where('user_id',$id)->where('is_read',false)->count();
            case 'mark_notification_read': DB::table('notifications')->where('user_id',$id)->where('id',$p['p_id']??null)->update(['is_read'=>true]); return null;
            case 'mark_all_notifications_read': DB::table('notifications')->where('user_id',$id)->update(['is_read'=>true]); return null;
            case 'fetch_available_platform_vouchers':
                $rows=cleanRows(DB::table('platform_vouchers')->where('is_active',true)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))
                    ->where(fn($q)=>$q->whereNull('usage_limit')->orWhereColumn('used_count','<','usage_limit'))->get());
                foreach ($rows as &$row) $row['eligible']=(float)($p['p_subtotal']??0)>=(float)$row['minimum_spend'];
                return $rows;
            case 'fetch_my_reviews': return MobileAftercare::decorateReviews(cleanRows(DB::table('reviews')->where('buyer_id',$id)->orderByDesc('created_at')->get()),true);
            case 'reviewable_product_order':
                $this->identity->approved('buyer');
                validateData($p,['p_product_id'=>'required|uuid']);
                foreach(Order::where('buyer_id',$id)->where('status','completed')->whereJsonContains('items',[['product_id'=>$p['p_product_id']]])->latest()->get() as $order) {
                    if(!MobileAftercare::eventDate($order,'completed')->gte(now()->subDays(30)))continue;
                    $reviewed=DB::table('reviews')->where('buyer_id',$id)->where('product_id',$p['p_product_id'])->where(function($q)use($order){
                        $q->where('order_id',$order->id)->orWhereIn('id',DB::table('mobile_buyer_reviews')->select('review_id')->where('order_id',$order->id));
                    })->exists();
                    if(!$reviewed)return ['order_id'=>$order->id];
                }
                return ['order_id'=>null];
            case 'submit_review': return $this->review($p);
            case 'fetch_order_status_history':
                $this->order($p['p_order_id']??'');
                return cleanRows(DB::table('order_status_history')->where('order_id',$p['p_order_id'])->orderBy('created_at')->get());
            case 'claim_shipment': case 'update_delivery_status': case 'confirm_order_received': case 'get_delivery_contact_info': case 'get_my_delivery_rider':
                return $this->delivery($name,$p);
            case 'fetch_payment_verification': case 'submit_payment_verification': return $this->payment($name,$p);
            case 'apply_voucher':
                $this->identity->approved('buyer');
                $v=DB::table('vouchers')->where('seller_id',$p['p_seller_id']??0)->where('code',strtoupper($p['p_code']??''))->first();
                (new MobileCommerce($this->identity))->validateVoucher($v,(float)($p['p_subtotal']??0));
                return [['discount_amount'=>$v->type==='free_shipping'?0:min((float)$v->discount_amount,(float)$p['p_subtotal']),'free_shipping'=>$v->type==='free_shipping']];
            case 'apply_platform_voucher':
                $v=DB::table('platform_vouchers')->where('id',$p['p_voucher_id']??'')->first();
                $commerce=new MobileCommerce($this->identity); $commerce->validateVoucher($v,(float)($p['p_subtotal']??0));
                return [['discount_amount'=>$v->type==='discount'?$commerce->discount($v,(float)$p['p_subtotal']):0,'free_shipping'=>$v->type==='free_shipping']];
            case 'record_order_status_history': throw new ApiError('Order history is recorded by the server.',403);
        }
        throw new ApiError('Unknown action: '.$name,404);
    }
    public function order(string $id): Order {
        $order=Order::find($id);
        ensure($order!==null,'Order not found.',404);
        $uid=$this->identity->approved();
        ensure((int)$order->buyer_id===$uid || ($this->identity->user->account_type==='rider' && Shipment::where('order_id',$id)->where('courier_id',$uid)->exists()),'Order access denied.',403);
        return $order;
    }
    private function profile(string $name,array $p): array {
        $rules=match($name) {
            'update_my_username'=>['username'=>'required|alpha_dash|max:255|unique:users,username,'.$this->identity->id()],
            'update_my_profile'=>['contact_no'=>'required|regex:/^09[0-9]{9}$/','house_no'=>'nullable|string|max:255','street'=>'nullable|string|max:255','barangay'=>'required|string|max:255','municipality'=>'required|string|max:255','province'=>'required|string|max:255','region'=>'nullable|string|max:255'],
            default=>['given_names'=>'required|string|max:255','last_name'=>'required|string|max:255','middle_name'=>'nullable|string|max:50','facebook_url'=>'nullable|url|max:2048','instagram_url'=>'nullable|url|max:2048','twitter_url'=>'nullable|url|max:2048','tiktok_url'=>'nullable|url|max:2048','notify_enabled'=>'nullable|boolean','profile_picture'=>'nullable|string|max:2048'],
        };
        $data=[]; foreach ($p as $key=>$value) $data[preg_replace('/^p_/','',$key)]=$value;
        $identityFields=$name==='update_my_profile'?['contact_no','house_no','street','barangay','municipality','province','region']:['given_names','last_name','middle_name'];
        $identityChanged=false; foreach ($identityFields as $field) if (array_key_exists($field,$data) && (string)$data[$field] !== (string)$this->identity->user->getAttribute($field)) $identityChanged=true;
        if ($this->identity->user->account_type==='buyer' && in_array($name,['update_my_profile','update_my_profile_details'],true) && $identityChanged) {
            require_once __DIR__.'/Buyer.php'; require_once __DIR__.'/WebsiteBuyer.php';
            $action=$name==='update_my_profile'?'address':'profile';
            if ($action==='profile') $data['contact_no']=$this->identity->user->contact_no;
            $extraRules=array_intersect_key($rules,array_flip(['facebook_url','instagram_url','twitter_url','tiktok_url','notify_enabled']));
            $extras=validateData($data,$extraRules);
            ensure(empty($data['profile_picture']),'Update your profile photo separately from identity changes.');
            DB::transaction(function() use($action,$data,$extras) {
                (new WebsiteBuyer($this->identity))->handle($action,$data);
                if ($extras) $this->identity->user->forceFill($extras)->save();
            });
            return cleanRow($this->identity->user->fresh()->getAttributes());
        }
        $data=validateData($data,$rules);
        if (!empty($data['profile_picture'])) {
            $prefix='/media/profile-images/'.$this->identity->id().'/';
            ensure(str_contains($data['profile_picture'],$prefix),'Upload your own profile picture.');
            $data['profile_picture']=substr($data['profile_picture'],strpos($data['profile_picture'],$prefix)+strlen('/media/profile-images/'));
        }
        $this->identity->user->forceFill($data)->save();
        return cleanRow($this->identity->user->fresh()->getAttributes());
    }
    private function publicAction(string $name,array $p): array {
        if ($name==='get_product_rating_summary') {
            $q=DB::table('reviews')->where('product_id',$p['p_product_id']??'');
            return [['average'=>(float)((clone $q)->avg('rating')??0),'review_count'=>$q->count()]];
        }
        if ($name==='fetch_product_reviews') {
            $rows=cleanRows(DB::table('reviews')->where('product_id',$p['p_product_id']??'')->orderByDesc('created_at')->limit(200)->get());
            foreach ($rows as &$r) {
                $u=User::find($r['buyer_id']);
                $r['reviewer_name']=$r['is_anonymous']?'Anonymous buyer':($u?->given_names??'Buyer').' '.mb_substr($u?->last_name??'',0,1).'.';
                unset($r['buyer_id']);
            }
            return MobileAftercare::decorateReviews($rows);
        }
        if ($name==='find_visually_similar_products') {
            $signature=$p['p_signature']??[];
            ensure(is_array($signature) && count($signature)===48 && count(array_filter($signature,'is_numeric'))===48,'Invalid image signature.');
            $products=DB::table('products')->where('status','active')->whereNotNull('image_signature')->whereIn('seller_id',DB::table('users')->select('id')->where('status','approved')->where('account_type','seller'))->get();
            $scored=[];
            foreach ($products as $product) {
                $vector=json_decode($product->image_signature,true); if (!is_array($vector) || count($vector)!==48) continue;
                $distance=0; foreach ($vector as $i=>$v) $distance+=((float)$v-(float)$signature[$i])**2;
                $scored[]=['id'=>$product->id,'distance'=>sqrt($distance)];
            }
            usort($scored,fn($a,$b)=>$a['distance']<=>$b['distance']);
            return array_slice($scored,0,max(1,min(50,(int)($p['p_limit']??20))));
        }
        if ($name==='get_shop_stats') return [$this->shopStats((int)($p['p_seller_id']??0))];
        $shops=DB::table('users')->where('account_type','seller')->where('status','approved')->get(['id','business_name','given_names','last_name','municipality','province']);
        $rows=[];
        foreach ($shops as $s) $rows[]=['seller_id'=>$s->id,'name'=>$s->business_name?:trim($s->given_names.' '.$s->last_name),'location'=>trim($s->municipality.', '.$s->province,', ')]+$this->shopStats((int)$s->id);
        usort($rows,fn($a,$b)=>($b['average_rating']<=>$a['average_rating'])?:($b['product_count']<=>$a['product_count']));
        return array_slice($rows,0,max(1,min(100,(int)($p['p_limit']??10))));
    }
    private function shopStats(int $id): array {
        $reviews=DB::table('reviews')->where('seller_id',$id);
        return ['follower_count'=>DB::table('shop_follows')->where('seller_id',$id)->count(),'product_count'=>DB::table('products')->where('seller_id',$id)->where('status','active')->count(),
            'average_rating'=>(float)((clone $reviews)->avg('rating')??0),'review_count'=>$reviews->count()];
    }
    private function review(array $p): array {
        $id=$this->identity->approved('buyer');
        validateData($p,['p_order_id'=>'required|uuid','p_product_id'=>'required|uuid','p_rating'=>'required|integer|between:1,5','p_comment'=>'nullable|string|max:500','p_is_anonymous'=>'nullable|boolean','p_media_url'=>'nullable|string|max:2048']);
        return DB::transaction(function () use ($p,$id) {
            User::whereKey($id)->lockForUpdate()->firstOrFail();
            $order=$this->order($p['p_order_id']);
            $order=Order::lockFresh($order->id);
            ensure($order->status==='completed','Confirm receipt before reviewing this order.');
            ensure(MobileAftercare::eventDate($order,'completed')->gte(now()->subDays(30)),'Reviews are available only within 30 days of completion.');
            MobileAftercare::linkLegacyReviews($order);
            ensure(!DB::table('mobile_buyer_reviews as m')->join('reviews as r','r.id','=','m.review_id')->where('m.order_id',$order->id)->where('r.product_id',$p['p_product_id'])->exists(),'You already reviewed this product.');
            ensure(collect($order->items)->contains(fn($i)=>($i['product_id']??'')===$p['p_product_id']),'This product was not in your order.');
            ensure(!DB::table('reviews')->where('buyer_id',$id)->where('order_id',$order->id)->where('product_id',$p['p_product_id'])->exists(),'You already reviewed this product.');
            $media=$p['p_media_url']??null;
            if ($media) ensure(str_starts_with($media,$id.'/') && Storage::disk('public')->exists('review-media/'.$media),'Upload your review media first.');
            $reviewId=(string)Str::uuid();
            DB::table('reviews')->insert(['id'=>$reviewId,'buyer_id'=>$id,'seller_id'=>$order->seller_id,'order_id'=>$order->id,'product_id'=>$p['p_product_id'],
                'rating'=>$p['p_rating'],'comment'=>$p['p_comment']??'','media_url'=>$media,'is_anonymous'=>(bool)($p['p_is_anonymous']??false),'created_at'=>now()]);
            MobileAftercare::linkLegacyReviews($order);
            return [['saved'=>true]];
        });
    }
    private function delivery(string $name,array $p): mixed {
        $uid=$this->identity->approved();
        $shipment=Shipment::find($p['p_shipment_id']??''); ensure($shipment!==null,'Shipment not found.',404);
        if ($name==='get_my_delivery_rider') {
            $order=$this->order($shipment->order_id); ensure((int)$order->buyer_id===$uid,'Buyer access required.',403);
            $rider=$shipment->courier_id?User::find($shipment->courier_id):null;
            return $rider?[['rider_id'=>$rider->id,'rider_name'=>trim($rider->given_names.' '.$rider->last_name),
                'rider_photo'=>$rider->profile_picture,
                'rider_contact'=>in_array($shipment->shipping_status,['assigned_to_rider','picked_up','out_for_delivery','delivery_failed','rescheduled'],true)?$rider->contact_no:null]]:[];
        }
        if ($name==='get_delivery_contact_info') {
            $this->identity->approved('rider'); ensure((int)$shipment->courier_id===$uid,'You are not assigned to this delivery.',403);
            $buyer=$shipment->order?->buyer;
            $address=$shipment->order?->shipping_address??[];
            return $buyer?[['buyer_name'=>$address['recipient_name']??trim($buyer->given_names.' '.$buyer->last_name),'buyer_contact'=>$address['contact_no']??$address['phone']??$buyer->contact_no]]:[];
        }
        if($name==='claim_shipment') {
            $this->identity->approved('rider');
            app(App\Services\RiderDeliveryOffers::class)->accept($this->identity->user,$shipment->id,'delivery');
            return null;
        }
        return DB::transaction(function () use ($name,$p,$uid,$shipment) {
            \App\Services\GcashLocks::shipment($shipment->id);
            $s=Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $order=Order::lockFresh($s->order_id);
            if ($name==='confirm_order_received') {
                $this->identity->approved('buyer'); ensure((int)$order->buyer_id===$uid,'Order access denied.',403);
                ensure(!WebsiteAftercare::open($order->id),'Resolve the return/refund request before confirming receipt.');
                if (in_array($s->shipping_status,['delivered','completed'],true) && $order->status==='completed') return null;
                ensure(!\App\Models\LogisticsIssue::where('shipment_id',$s->id)->where('reported_by',$uid)->where('status','!=','resolved')->exists(),'Your delivery issue must be resolved before confirming receipt.');
                ensure($s->shipping_status==='delivered','Only delivered orders can be confirmed.');
                $order->status='completed'; $order->save(); $order->logStatus('completed',$uid); return null;
            }
            $this->identity->approved('rider');
            throw new ApiError('Use parcel scan and delivery OTP actions to update this delivery.');
        },3);
    }
    private function payment(string $name,array $p): ?array {
        $id=$this->identity->approved('buyer'); $order=$this->order($p['p_order_id']??'');
        ensure((int)$order->buyer_id===$id,'Order access denied.',403);
        if ($name==='submit_payment_verification') {
            ensure(!$order->platform_payment, 'Use the order payment submission form. Payment can only be verified by PocketFinds admin.', 409);
            validateData($p,['p_reference_number'=>'required|string|max:255','p_proof_path'=>'required|string|max:2048']);
            ensure(str_starts_with($p['p_proof_path'],$id.'/') && Storage::disk('local')->exists('payment-proofs/'.$p['p_proof_path']),'Upload your own payment proof.');
            DB::table('payment_verifications')->updateOrInsert(['order_id'=>$order->id,'buyer_id'=>$id],['id'=>(string)Str::uuid(),
                'reference_number'=>$p['p_reference_number'],'proof_path'=>$p['p_proof_path'],'status'=>'submitted','submitted_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        }
        return cleanRow(DB::table('payment_verifications')->where('order_id',$order->id)->where('buyer_id',$id)->first());
    }
}
