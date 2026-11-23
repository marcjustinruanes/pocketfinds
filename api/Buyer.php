<?php
declare(strict_types=1);
use App\Http\Controllers\BuyerController;
use App\Models\{BuyerAddress,Order,User};
use Illuminate\Http\Request;
use Illuminate\Session\{Store,ArraySessionHandler};
use Illuminate\Support\Facades\{Auth,DB};
require_once __DIR__.'/BuyerOrderSupport.php';

/** Adapt the existing website buyer rules without editing website source. */
final class MobileBuyer {
    public function __construct(private Identity $identity) {}
    public function handle(string $action,array $data): array {
        $uid=$this->identity->approved('buyer');
        if ($action==='addresses') {
            DB::transaction(function() use($uid) { User::whereKey($uid)->lockForUpdate()->firstOrFail(); BuyerAddress::ensureDefaultFor($this->identity->user); });
            return cleanRows(BuyerAddress::where('buyer_id',$uid)->orderByDesc('is_default')->latest()->get()->map(fn($a)=>$a->toArray()+['full_address'=>$a->full_address,'postal_code'=>DB::table('mobile_address_details')->where('address_id',$a->id)->value('postal_code')])->all());
        }
        if (str_starts_with($action,'address-')) {
            if ($action==='address-create') ensure(BuyerAddress::where('buyer_id',$uid)->count()<10,'You can save at most 10 delivery addresses.');
            if (array_key_exists('postal_code',$data)) validateData($data,['postal_code'=>['nullable','regex:/^[0-9]{4}$/D']]);
            $address=null;
            if ($action!=='address-create') {
                validateData($data,['id'=>'required|integer']);
                $address=BuyerAddress::where('buyer_id',$uid)->find($data['id']); ensure($address!==null,'Address not found.',404);
            }
            return DB::transaction(function() use($data,$uid,$action,$address) {
                User::whereKey($uid)->lockForUpdate()->firstOrFail();
                if ($action==='address-create') ensure(BuyerAddress::where('buyer_id',$uid)->count()<10,'You can save at most 10 delivery addresses.');
                $result=$this->native($data,function($controller,$request) use($action,$address) {
                return match($action) {
                    'address-create'=>$controller->storeAddress($request),
                    'address-update'=>$controller->editAddress($request,$address),
                    'address-default'=>$controller->defaultAddress($request,$address),
                    'address-delete'=>$controller->destroyAddress($request,$address),
                    default=>throw new ApiError('Unknown address action.',404),
                };
                });
                if (isset($data['postal_code'])) {
                    $target=$address?->id ?? ($result['address']['id']??BuyerAddress::where('buyer_id',$uid)->latest('id')->value('id'));
                    DB::table('mobile_address_details')->updateOrInsert(['address_id'=>$target],['postal_code'=>$data['postal_code']]);
                }
                return $result;
            });
        }
        validateData($data,['order_id'=>'required|uuid']);
        $order=Order::where('buyer_id',$uid)->find($data['order_id']); ensure($order!==null,'Order not found.',404);
        if ($action==='order') {
            require_once __DIR__.'/OrderDetails.php';
            $snapshot=BuyerOrderSupport::snapshot($order,$uid);
            $snapshot['details']=MobileOrderDetails::read($order,$uid);
            if($order->gcash_purchase_id){require_once __DIR__.'/Gcash.php';$snapshot['payment_verification']=(new MobileGcash($this->identity))->handle('order-payment',['order_id'=>$order->id]);}
            if(MobileRider::installed() && $order->shipment) {
                $s=$order->shipment;
                $snapshot['delivery_code']=$s->shipping_status==='out_for_delivery' && $s->delivery_otp_ciphertext?\Illuminate\Support\Facades\Crypt::decryptString($s->delivery_otp_ciphertext):null;
                $snapshot['delivery_proof']=$s->delivery_proof_file?['photo'=>$s->delivery_proof_file,'recorded_at'=>$s->delivered_at?->toDateTimeString()]:null;
            }
            return $snapshot;
        }
        if ($action==='report-issue') return BuyerOrderSupport::issue($order,$uid,$data);
        if ($action==='cancel') {
            if ($order->status==='cancelled') return ['cancelled'=>true,'message'=>'Order already cancelled.'];
            return DB::transaction(function() use($data,$order,$uid) {
                $locked=Order::lockFresh($order->id);
                $shipment=\App\Models\Shipment::where('order_id',$order->id)->lockForUpdate()->first();
                ensure(BuyerOrderSupport::canCancel($locked,$shipment),'This parcel has been picked up or can no longer be cancelled.');
                if ($locked->platform_payment) {
                    validateData($data,['cancellation_reason'=>'required|string|min:3|max:300','cancellation_note'=>'nullable|string|max:500']);
                    $locked->cancelAndRestock($data['cancellation_reason'],$data['cancellation_note']??null,$uid,array_merge(Order::CANCELLABLE_STATUSES,['pending_payment','pending_payment_verification']));
                    (new MobileBuyerPricing)->release($locked->id);
                    app(App\Services\PlatformPayments::class)->audit($locked->id,$uid,'order_cancelled',['reason'=>$data['cancellation_reason']]);
                    return ['cancelled'=>true,'message'=>'Order cancelled. Any transfer already sent requires admin refund review.'];
                }
                $result=$this->native($data,fn($c,$r)=>$c->cancelOrder($r,$locked));
                (new MobileBuyerPricing)->release($locked->id);
                if ($locked->gcash_purchase_id) {
                    DB::table('mobile_refunds')->insertOrIgnore(['id'=>(string)\Illuminate\Support\Str::uuid(),'order_id'=>$locked->id,'buyer_id'=>$locked->buyer_id,
                        'seller_id'=>$locked->seller_id,'amount'=>$locked->total,'status'=>'pending_reconciliation','destination'=>'Original GCash payment; seller/admin must record actual repayment.',
                        'created_at'=>now(),'updated_at'=>now()]);
                    Order::notifyUser($locked->seller_id,'Repayment required','Cancelled prepaid order '.$locked->order_number.' requires actual repayment verification.','order_status',$locked->id);
                }
                return $result;
            },3);
        }
        if ($action==='review') return DB::transaction(function() use($data,$order) {
            $locked=Order::lockFresh($order->id);
            validateData($data,['comment'=>'nullable|string|max:500']);
            ensure($locked->status==='completed' && MobileAftercare::eventDate($locked,'completed')->gte(now()->subDays(30)),'Reviews are available only within 30 days of completion.');
            MobileAftercare::linkLegacyReviews($locked);
            ensure(!DB::table('mobile_buyer_reviews')->where('order_id',$locked->id)->exists(),'You already reviewed an item. Use the item review form.');
            $result=$this->native($data,fn($c,$r)=>$c->storeReview($r,$locked));
            MobileAftercare::linkLegacyReviews($locked);
            return $result;
        });
        if ($action==='buy-again') {
            validateData($data,['request_id'=>'required|uuid']);
            return DB::transaction(function() use($data,$uid,$order) {
                User::whereKey($uid)->lockForUpdate()->firstOrFail();
                $hash=hash('sha256',json_encode(['buy-again',$order->id]));
                $saved=DB::table('mobile_checkouts')->where('buyer_id',$uid)->where('checkout_id',$data['request_id'])->first();
                if ($saved) { ensure(hash_equals($hash,$saved->request_hash),'Request ID already used.',409); return json_decode($saved->result,true); }
                $result=BuyerOrderSupport::buyAgain($order,$uid);
                DB::table('mobile_checkouts')->insert(['buyer_id'=>$uid,'checkout_id'=>$data['request_id'],'request_hash'=>$hash,'result'=>json_encode($result),'created_at'=>now()]);
                return $result;
            });
        }
        throw new ApiError('Unknown buyer action.',404);
    }
    public function native(array $data,Closure $operation): array {
        $request=Request::create('/mobile-buyer','POST',$data); $request->headers->set('Accept','application/json');
        $request->setUserResolver(fn()=>$this->identity->user);
        $session=new Store('mobile-buyer',new ArraySessionHandler(30)); $session->start(); $request->setLaravelSession($session);
        $redirector=app('redirect'); $redirector->setSession($session);
        $previous=Auth::user(); Auth::setUser($this->identity->user);
        try {
            $response=$operation(app(BuyerController::class),$request);
            if ($response instanceof Illuminate\Http\JsonResponse) {
                $result=$response->getData(true);
                ensure($response->getStatusCode()<400,$result['message']??'This buyer action could not be completed.',$response->getStatusCode());
                return $result;
            }
            // Redirect responses carry the website's actual success/error flash.
            $flash=$response->getSession();
            ensure(!$flash->has('error'),(string)$flash->get('error'));
            $errors=$flash->get('errors'); ensure(!$errors || !$errors->any(),$errors?->first()??'The action failed.');
            return ['ok'=>true,'message'=>$flash->get('success','Updated.')];
        } finally { $redirector->setSession(app('session.store')); if ($previous) Auth::setUser($previous); else Auth::forgetUser(); }
    }
}
