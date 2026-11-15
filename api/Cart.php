<?php
declare(strict_types=1);
use App\Models\{User,Product,CartItem};
use Illuminate\Support\Facades\DB;

final class MobileCart {
    public function __construct(private Identity $identity) {}
    public function edit(array $data): array {
        $uid=$this->identity->approved('buyer');
        validateData($data,['request_id'=>'required|uuid','cart_id'=>'required|uuid','quantity'=>'required|integer|min:1|max:99','group'=>'nullable|string|max:150','value'=>'nullable|string|max:150']);
        return MobileRequests::run('cart-edit:'.$uid,$data['request_id'],$data,function()use($data,$uid){
            User::whereKey($uid)->lockForUpdate()->firstOrFail();
            $item=CartItem::where('buyer_id',$uid)->whereKey($data['cart_id'])->lockForUpdate()->first();
            ensure($item!==null,'Cart item not found.',404);
            $product=Product::whereKey($item->product_id)->lockForUpdate()->first();
            ensure($product && $product->status==='active' && $product->seller?->status==='approved' && !$product->seller->is_on_vacation,'This product or shop is unavailable.');
            return (new MobileBuyer($this->identity))->native([
                'qty'=>$data['quantity'],'variation_group'=>$data['group']??'','variation_value'=>$data['value']??'',
            ],fn($controller,$request)=>$controller->cartEdit($request,$item->id));
        });
    }
    public function handle(array $data): array {
        $uid=$this->identity->approved('buyer');
        validateData($data,['request_id'=>'required|uuid','product_id'=>'required|uuid','quantity'=>'required|integer|min:1|max:99','group'=>'nullable|string|max:255','value'=>'nullable|string|max:255']);
        return MobileRequests::run('cart-add:'.$uid,$data['request_id'],$data,function() use($data,$uid) {
            User::whereKey($uid)->lockForUpdate()->firstOrFail();
            $p=Product::whereKey($data['product_id'])->where('status','active')->lockForUpdate()->first();
            ensure($p && $p->seller?->status==='approved' && !$p->seller->is_on_vacation,'This product or shop is unavailable or on vacation.');
            require_once __DIR__.'/Buyer.php';
            $result=(new MobileBuyer($this->identity))->native([
                'product_id'=>$p->id,'qty'=>$data['quantity'],
                'variation_group'=>$data['group']??'', 'variation_value'=>$data['value']??'',
            ],fn($controller,$request)=>$controller->cartAdd($request));
            $row=CartItem::where('buyer_id',$uid)->where('product_id',$p->id)
                ->where('variation_group',$p->variations ? ($data['group']??'') : '')
                ->where('variation_value',$p->variations ? ($data['value']??'') : '')->firstOrFail();
            return ['id'=>$row->id,'quantity'=>(int)$row->qty,'price'=>(float)$row->price];
        });
    }
}
