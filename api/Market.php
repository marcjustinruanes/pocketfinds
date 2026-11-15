<?php
declare(strict_types=1);
use App\Models\{User,Order,Product,CartItem,PaymentMethod};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Website-guided buyer commerce, installed only in the mobile API. */
final class MobileMarket {
    public function __construct(private Identity $identity) {}
    public function handle(string $action,array $data): mixed {
        $uid=$this->identity->approved('buyer');
        if(($data['website_payment']??false)===true)return (new NativeBuyerCommerce($this->identity))->handle($action,$data);
        $websiteCheckout=($data['website_checkout']??false)===true && class_exists(\App\Services\BuyerCommerce::class);
        $flow=$websiteCheckout ? app(\App\Services\BuyerCommerce::class) : new MobileBuyerPricing;
        if ($action==='vouchers') {
            if (!$websiteCheckout) self::importVouchers();
            $prefix=$websiteCheckout?'commerce_':'mobile_commerce_';
            return cleanRows(DB::table($prefix.'vouchers as v')->leftJoin($prefix.'claims as c',fn($j)=>$j->on('c.voucher_id','=','v.id')->where('c.buyer_id',$uid))
                ->where('v.is_active',true)->where('v.ends_at','>',now())->select('v.*','c.id as claim_id')->orderBy('v.ends_at')->get());
        }
        if ($action==='claim') {
            validateData($data,['voucher_id'=>'required|integer']);
            return DB::transaction(function() use($data,$uid,$websiteCheckout) {
                $prefix=$websiteCheckout?'commerce_':'mobile_commerce_';
                $v=DB::table($prefix.'vouchers')->where('id',$data['voucher_id'])->lockForUpdate()->first();
                ensure($v && $v->is_active && strtotime($v->ends_at)>time() && strtotime($v->starts_at)<=time(),'This voucher is not available to claim.');
                if ($websiteCheckout) ensure(\App\Services\DeliveryGuarantee::voucherBelongsTo((int)$v->id,$uid),'This voucher belongs to another buyer.',403);
                DB::table($prefix.'claims')->insertOrIgnore(['buyer_id'=>$uid,'voucher_id'=>$v->id]);
                return ['claimed'=>true];
            });
        }
        if ($action==='reminder') {
            validateData($data,['sale_id'=>'required|integer']);
            (new MobileBuyer($this->identity))->native([],fn($unused,$request)=>app(\App\Http\Controllers\CommerceController::class)->reminder($request,(int)$data['sale_id']));
            return ['saved'=>true];
        }
        validateData($data,['cart_ids'=>'required|array|min:1|max:100','cart_ids.*'=>'required|uuid','delivery_address_id'=>'nullable|integer',
            'voucher_codes'=>'nullable|array|max:102','voucher_codes.*'=>'nullable|string|max:30']);
        if ($action==='quote') {
            return DB::transaction(function() use($flow,$data,$uid) {
                User::whereKey($uid)->lockForUpdate()->firstOrFail();
                $quote=$flow->quote($this->identity->user,$data['cart_ids'],$data['delivery_address_id']??null,$data['voucher_codes']??[]);
                return $quote+['quote_hash'=>hash('sha256',json_encode($quote))];
            },3);
        }
        ensure($action==='checkout','Unknown market action.',404);
        validateData($data,['checkout_id'=>'required|uuid','quote_hash'=>'required|string|size:64','payment_method_id'=>'required|integer','buyer_note'=>'nullable|string|max:200']);
        return MobileRequests::run('checkout:'.$uid,$data['checkout_id'],$data,function() use($flow,$data,$uid,$websiteCheckout) {
            User::whereKey($uid)->lockForUpdate()->firstOrFail();
            $quote=$flow->quote($this->identity->user,$data['cart_ids'],$data['delivery_address_id']??null,$data['voucher_codes']??[]);
            ensure(hash_equals(hash('sha256',json_encode($quote)),$data['quote_hash']),'Prices, availability, shipping or discounts changed. Refresh the summary and review it before placing your order.',409);
            $method=PaymentMethod::find($data['payment_method_id']);
            ensure($method && $method->buyerCheckoutKind(),'Choose an active COD or manual GCash payment method.');
            $quantities=[];foreach ($quote['orders'] as $row) foreach ($row['items'] as $item) $quantities[$item['product_id']]=($quantities[$item['product_id']]??0)+(int)$item['qty'];
            foreach ($quantities as $pid=>$qty) self::purchaseLimit($uid,$pid,$qty);
            $result=[];
            $platform = class_exists(App\Services\PlatformPayments::class) && app(App\Services\PlatformPayments::class)->available();
            if($method->buyerCheckoutKind()==='gcash' && ($data['payment_flow']??null)==='platform') ensure($platform,'Run api/install-platform-payments.php before using PocketFinds GCash checkout.',503);
            $platformGcash = $method->buyerCheckoutKind()==='gcash' && $platform;
            if ($platformGcash) {
                app(App\Services\PlatformPayments::class)->configuration();
            }
            if ($method->buyerCheckoutKind()==='gcash' && !$platformGcash) {
                $purchases=app(App\Services\GcashWorkflow::class)->create($this->identity->user,$data['cart_ids'],$method->id,$data['checkout_id'],null,$data['delivery_address_id']??null,$data['buyer_note']??null,$quote);
                foreach ($purchases as $p) {
                    self::saveQuote($p->id,$uid,$data['checkout_id'],$quote['orders'][$p->seller_id]);
                    $result[]=['id'=>$p->id,'order_number'=>'GCash verification pending','payment_pending'=>true];
                }
            } else {
                foreach ($quote['orders'] as $sellerId=>$row) {
                    foreach ($row['items'] as $item) Product::findOrFail($item['product_id'])->deductStock($item['qty'],$item['variation_group'],$item['variation_value']);
                    $order=new Order;
                    $order->forceFill(['order_number'=>'PF-'.strtoupper(Str::random(12)),'buyer_id'=>$uid,'seller_id'=>$sellerId,'app_buyer_id'=>$uid,'app_seller_id'=>$sellerId,
                        'status'=>$platformGcash?'pending_payment':'placed','items'=>$row['items'],'subtotal'=>$row['subtotal'],'shipping_amount'=>$row['shipping_amount'],'shipping_fee'=>$row['shipping_amount'],
                        'discount_amount'=>$row['discount_amount'],'total'=>$row['total'],'shipping_address'=>$quote['address'],'buyer_note'=>$data['buyer_note']??null,
                        'payment_method_id'=>$method->id,'payment_method'=>$method->name,'inventory_reserved'=>true,
                        ...($websiteCheckout?['commerce_snapshot'=>$row]:[])])->save();
                    if ($platformGcash) {
                        $order->forceFill(['platform_payment'=>true])->save();
                        app(App\Services\PlatformPayments::class)->initialize($order);
                    }
                    $flow->allocate($order->id,$this->identity->user,$row,false,$data['checkout_id']);
                    self::saveQuote($order->id,$uid,$data['checkout_id'],$row);
                    $order->logStatus($order->status,$uid);
                    if (!$platformGcash) foreach ([$uid,$sellerId] as $recipient) Order::notifyUser($recipient,'Order placed','Order '.$order->order_number.' was placed.','order_status',$order->id);
                    $result[]=['id'=>$order->id,'order_number'=>$order->order_number,'platform_payment'=>$platformGcash];
                }
                CartItem::where('buyer_id',$uid)->whereIn('id',$data['cart_ids'])->delete();
            }
            return $result;
        });
    }
    public static function saveQuote(string $id,int $uid,string $checkout,array $row): void {
        DB::table('mobile_order_quotes')->updateOrInsert(['subject_id'=>$id],['buyer_id'=>$uid,'checkout_id'=>$checkout,'snapshot'=>json_encode($row),'created_at'=>now()]);
    }
    public static function purchaseLimit(int $uid,string $product,int $qty): void {
        $limit=DB::table('mobile_product_rules')->where('product_id',$product)->value('per_buyer_limit');
        if (!$limit) return;
        $bought=0;
        foreach (Order::where('buyer_id',$uid)->whereNotIn('status',['cancelled','returned'])->get(['items']) as $order)
            foreach ($order->items??[] as $item) if (($item['product_id']??null)===$product) $bought+=(int)$item['qty'];
        foreach (App\Models\GcashPurchase::where('buyer_id',$uid)->whereIn('reservation_state',['initializing','held','paid'])->get(['snapshot']) as $p)
            foreach ($p->snapshot['items']??[] as $item) if (($item['product_id']??null)===$product) $bought+=(int)$item['qty'];
        ensure($bought+$qty<=$limit,'Purchase limit reached for this product.');
    }
    public static function importVouchers(): void {
        foreach (DB::table('vouchers')->get() as $v) {
            if (DB::table('mobile_commerce_vouchers')->where('legacy_store_id',$v->id)->exists()) continue;
            $shipping=$v->type==='free_shipping';
            DB::table('mobile_commerce_vouchers')->insertOrIgnore(['code'=>strtoupper($v->code),'kind'=>$shipping?'shipping':'store','seller_id'=>$v->seller_id,'legacy_store_id'=>$v->id,
                'legacy_used_count'=>$v->used_count,'discount_type'=>$shipping?'percent':'fixed','amount'=>$shipping?100:$v->discount_amount,
                'minimum_spend'=>$v->minimum_spend??0,'starts_at'=>$v->created_at??now(),'ends_at'=>$v->expires_at??now()->addYears(10),
                'usage_limit'=>$v->usage_limit??4294967295,'per_buyer_limit'=>1,'is_active'=>$v->is_active]);
        }
        foreach (DB::table('platform_vouchers')->whereIn('type',['discount','free_shipping'])->get() as $v) {
            if (DB::table('mobile_commerce_vouchers')->where('legacy_platform_id',$v->id)->exists()) continue;
            $shipping=$v->type==='free_shipping';$percent=$shipping || ($v->discount_percent??0)>0;
            DB::table('mobile_commerce_vouchers')->insertOrIgnore(['code'=>'PF'.strtoupper(substr(str_replace('-','',$v->id),0,10)),
                'kind'=>$shipping?'shipping':'platform','legacy_platform_id'=>$v->id,'legacy_used_count'=>$v->used_count,
                'discount_type'=>$percent?'percent':'fixed','amount'=>$shipping?100:($percent?$v->discount_percent:$v->discount_amount),
                'minimum_spend'=>$v->minimum_spend??0,'starts_at'=>$v->created_at??now(),'ends_at'=>$v->expires_at??now()->addYears(10),
                'usage_limit'=>$v->usage_limit??4294967295,'per_buyer_limit'=>1,'is_active'=>$v->is_active]);
        }
    }
}
