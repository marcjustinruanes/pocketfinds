<?php
declare(strict_types=1);
use App\Models\Product;
use App\Services\BuyerCommerce;
use Illuminate\Support\Facades\DB;

final class MobileDeals {
    public static function sale(Product $product): ?object {
        $sale=app(BuyerCommerce::class)->flash($product);
        if(!$sale)$sale=DB::table('commerce_flash_sales')->where('product_id',$product->id)->where('is_active',true)->where('starts_at','>',now())->orderBy('starts_at')->first();
        if($sale)foreach(['starts_at','ends_at'] as $key)$sale->$key=\Illuminate\Support\Carbon::parse($sale->$key)->toIso8601String();
        return $sale;
    }
    public static function listing(array $data): array {
        validateData($data,['page'=>'nullable|integer|min:1|max:10000']);
        $page=(int)($data['page']??1);$limit=24;
        // Same eligible products as the website's Deals gallery, including upcoming flash sales.
        $flash=DB::table('commerce_flash_sales')->where('is_active',true)->where('ends_at','>',now())->select('product_id');
        $rows=Product::with('seller')->where('status','active')->sellerApproved()->whereHas('seller',fn($q)=>$q->where('is_on_vacation',false))
            ->where(fn($q)=>$q->whereColumn('discount_price','<','price')->orWhereIn('id',$flash))
            ->orderByDesc('created_at')->orderBy('id')->offset(($page-1)*$limit)->limit($limit+1)->get();
        return ['page'=>$page,'has_more'=>$rows->count()>$limit,'items'=>$rows->take($limit)->map(function($p){
            $sale=self::sale($p);
            return ['id'=>$p->id,'name'=>$p->name,'image'=>$p->image,'seller'=>$p->seller->business_name?:$p->seller->given_names,
                'price'=>app(BuyerCommerce::class)->unitPrice($p,(float)($p->discount_price!==null && $p->discount_price<$p->price?$p->discount_price:$p->price)),'regular_price'=>(float)$p->price,
                'sale'=>$sale?cleanRow($sale):null];
        })->values()->all()];
    }
}
