<?php
declare(strict_types=1);

use App\Models\Product;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Voucher;
use App\Services\GcashWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MobileCommerce {
    public function __construct(private Identity $identity) {}
    public function quote(array $data): array {
        $this->identity->approved('buyer');
        validateData($data,['seller_ids'=>'required|array|min:1|max:100','seller_ids.*'=>'required|integer']);
        $sellers=User::whereIn('id',array_unique($data['seller_ids']))->where('status','approved')->where('account_type','seller')->get();
        ensure($sellers->count()===count(array_unique($data['seller_ids'])),'One of these shops is unavailable.');
        return ['shipping_amount'=>(float)$sellers->sum('shipping_fee')];
    }
    public static function price(Product $product,string $group,string $value): float {
        $price=(float)($product->discount_price!==null && (float)$product->discount_price<(float)$product->price?$product->discount_price:$product->price);
        if (!empty($product->variant_axes)) {
            $option=$product->combinationOption($group);
            ensure($option!==null,'Choose an available product option.'); return (float)$option['price'];
        }
        if (!empty($product->variations)) {
            foreach ($product->variations as $axis) {
                if (($axis['name']??'')!==$group) continue;
                foreach ($axis['options']??[] as $option) if (($option['value']??'')===$value) return (float)($option['price']??$price);
            }
            throw new ApiError('Choose an available product option.');
        }
        return $price;
    }
    public function checkout(array $data): array {
        $id=$this->identity->approved('buyer');
        validateData($data,['cart_ids'=>'required|array|min:1|max:100','cart_ids.*'=>'required|uuid',
            'payment_method_id'=>'required|integer','shipping_address'=>'required|array',
            'voucher_code'=>'nullable|string|max:30','platform_voucher_id'=>'nullable|uuid','checkout_id'=>'required|uuid']);
        validateData($data,['delivery_address_id'=>'nullable|integer','buyer_note'=>'nullable|string|max:200']);
        $requestHash=hash('sha256',json_encode($data));
        $completed=DB::table('mobile_checkouts')->where('buyer_id',$id)->where('checkout_id',$data['checkout_id'])->first();
        if ($completed) {
            ensure(hash_equals($completed->request_hash,$requestHash),'This checkout identifier was already used for different items.');
            return json_decode($completed->result,true);
        }
        if (!empty($data['delivery_address_id'])) {
            $address=\App\Models\BuyerAddress::where('buyer_id',$id)->find($data['delivery_address_id']);
            ensure($address!==null,'Choose one of your saved delivery addresses.',403);
            $data['shipping_address']=$address->toShippingArray();
        }
        validateData($data['shipping_address'],['barangay'=>'required|string|max:255','municipality'=>'required|string|max:255','province'=>'required|string|max:255']);
        ensure(!empty($data['shipping_address']['house_no']) || !empty($data['shipping_address']['street']),'Add your house number or street.');
        // Match the website: refresh changed prices and require review before ordering.
        if (!DB::table('mobile_checkouts')->where('buyer_id',$id)->where('checkout_id',$data['checkout_id'])->exists()) {
            $changed=DB::transaction(function() use($data,$id) {
                User::whereKey($id)->lockForUpdate()->firstOrFail(); $changed=false;
                $items=DB::table('cart_items')->where('buyer_id',$id)->whereIn('id',$data['cart_ids'])->orderBy('product_id')->lockForUpdate()->get();
                foreach ($items as $item) {
                    $product=Product::whereKey($item->product_id)->lockForUpdate()->first();
                    if (!$product || $product->status!=='active') continue;
                    $price=self::price($product,$item->variation_group??'',$item->variation_value??'');
                    if (abs($price-(float)$item->price)>=0.005) { DB::table('cart_items')->where('id',$item->id)->update(['price'=>$price,'updated_at'=>now()]); $changed=true; }
                }
                return $changed;
            },3);
            ensure(!$changed,'Prices changed. Your cart has been updated; review the new prices before checking out again.');
        }
        // Serialize checkout retries on the buyer row before product/order locks.
        return DB::transaction(function () use ($data,$id,$requestHash) {
            User::whereKey($id)->lockForUpdate()->firstOrFail();
            $retry=DB::table('mobile_checkouts')->where('buyer_id',$id)->where('checkout_id',$data['checkout_id'])->first();
            if ($retry) {
                ensure(hash_equals($retry->request_hash,$requestHash),'This checkout identifier was already used for different items.');
                return json_decode($retry->result,true);
            }
            $cart=DB::table('cart_items')->where('buyer_id',$id)->whereIn('id',array_unique($data['cart_ids']))->orderBy('product_id')->lockForUpdate()->get();
            ensure($cart->count()===count(array_unique($data['cart_ids'])),'Some cart items changed. Refresh your cart.');
            $method=PaymentMethod::whereKey($data['payment_method_id'])->first();
            ensure($method!==null && $method->buyerCheckoutKind()!==null,'Choose an active payment method.');
            if ($method->buyerCheckoutKind()==='gcash') {
                ensure(empty($data['platform_voucher_id']),'Platform vouchers cannot be combined with GCash prepayment.');
                $purchases=app(GcashWorkflow::class)->create($this->identity->user,$data['cart_ids'],$method->id,$data['checkout_id'],$data['voucher_code']??null,$data['delivery_address_id']??null,$data['buyer_note']??null);
                // GCash is a pending purchase until seller verification, as on the website.
                $result=array_map(fn($p)=>['id'=>$p->id,'order_number'=>'GCash payment pending','payment_pending'=>true],$purchases);
                DB::table('mobile_checkouts')->insert(['buyer_id'=>$id,'checkout_id'=>$data['checkout_id'],'request_hash'=>$requestHash,'result'=>json_encode($result),'created_at'=>now()]);
                return $result;
            }
            $groups=[]; $subtotalAll=0.0;
            foreach ($cart as $item) {
                $product=Product::whereKey($item->product_id)->lockForUpdate()->first();
                ensure($product && $product->status==='active','A product is no longer available.');
                $seller=User::whereKey($product->seller_id)->first();
                ensure($seller && $seller->status==='approved' && !$seller->is_on_vacation,'A shop is not accepting orders.');
                $price=self::price($product,$item->variation_group??'',$item->variation_value??'');
                ensure(abs($price-(float)$item->price)<0.005,'A product price changed. Remove it and add it to your cart again.');
                ensure($item->qty>0 && $product->availableStock($item->variation_group?:null,$item->variation_value?:null)>=$item->qty,'Not enough stock. Refresh your cart.');
                $product->deductStock((int)$item->qty,$item->variation_group?:null,$item->variation_value?:null);
                $groups[$seller->id]['seller']=$seller;
                $groups[$seller->id]['items'][]=['product_id'=>$product->id,'name'=>$product->name,'price'=>$price,'qty'=>(int)$item->qty,
                    'variation_group'=>$item->variation_group,'variation_value'=>$item->variation_value,'color'=>$item->variation_group,'size'=>$item->variation_value,'image'=>$product->image];
                $subtotalAll+=$price*$item->qty;
            }
            $platform=null;
            if (!empty($data['platform_voucher_id'])) {
                $platform=DB::table('platform_vouchers')->where('id',$data['platform_voucher_id'])->lockForUpdate()->first();
                $this->validateVoucher($platform,$subtotalAll);
                DB::table('platform_vouchers')->where('id',$platform->id)->increment('used_count');
            }
            $code=strtoupper(trim($data['voucher_code']??'')); $applied=false; $result=[];
            foreach ($groups as $group) {
                $seller=$group['seller']; $items=$group['items'];
                $subtotal=array_sum(array_map(fn($i)=>$i['price']*$i['qty'],$items));
                $shipping=(float)($seller->shipping_fee??0); $discount=0.0; $voucher=null;
                if ($code!=='') {
                    $voucher=Voucher::where('seller_id',$seller->id)->where('code',$code)->lockForUpdate()->first();
                    if ($voucher && $voucher->isUsable() && $subtotal>=(float)$voucher->minimum_spend) {
                        $discount=$voucher->isFreeShipping()?$shipping:min($subtotal,(float)$voucher->discount_amount);
                        $voucher->increment('used_count'); $applied=true;
                    } else $voucher=null;
                }
                $share=$subtotalAll>0?$subtotal/$subtotalAll:0;
                $platformDiscount=$platform?match($platform->type) {
                    'free_shipping'=>$shipping,'discount'=>$share*$this->discount($platform,$subtotalAll),default=>0,
                }:0;
                $number='PF-'.now()->format('YmdHis').'-'.strtoupper(Str::random(5));
                $order=new Order();
                $order->forceFill(['order_number'=>$number,'buyer_id'=>$id,'seller_id'=>$seller->id,'app_buyer_id'=>$id,'app_seller_id'=>$seller->id,
                    'status'=>'placed','items'=>$items,'subtotal'=>$subtotal,'shipping_fee'=>$shipping,'shipping_amount'=>$shipping,
                    'discount_amount'=>$discount,'voucher_code'=>$voucher?$code:null,
                    'platform_voucher_id'=>$platform?->id,'platform_discount_amount'=>$platformDiscount,
                    'total'=>max(0,$subtotal+$shipping-$discount-$platformDiscount),
                    'shipping_address'=>$data['shipping_address'],'buyer_note'=>$data['buyer_note']??null,'payment_method_id'=>$method->id,'payment_method'=>$method->name,'inventory_reserved'=>true]);
                $order->save();
                $order->logStatus('placed',$id);
                $result[]=['id'=>$order->id,'order_number'=>$number];
            }
            ensure($code==='' || $applied,'This voucher does not apply to these items.');
            DB::table('cart_items')->where('buyer_id',$id)->whereIn('id',$data['cart_ids'])->delete();
            DB::table('mobile_checkouts')->insert(['buyer_id'=>$id,'checkout_id'=>$data['checkout_id'],'request_hash'=>$requestHash,'result'=>json_encode($result),'created_at'=>now()]);
            return $result;
        },3);
    }
    public function validateVoucher(?object $voucher,float $subtotal): void {
        ensure($voucher && $voucher->is_active && (!$voucher->expires_at || strtotime((string)$voucher->expires_at)>time())
            && ($voucher->usage_limit===null || $voucher->used_count<$voucher->usage_limit) && $subtotal>=(float)$voucher->minimum_spend,'Voucher is expired or does not apply.');
    }
    public function discount(object $voucher,float $subtotal): float {
        return min($subtotal,max((float)$voucher->discount_amount,$subtotal*(float)($voucher->discount_percent??0)/100));
    }
}
