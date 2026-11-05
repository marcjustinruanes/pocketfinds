<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShopCatalog
{
    public function categories(int $sellerId)
    {
        return Schema::hasTable('shop_categories') ? DB::table('shop_categories')->where('seller_id',$sellerId)->orderBy('name')->get() : collect();
    }
    public function filter(array $items, int $sellerId, ?string $selected): array
    {
        if(!$selected || !Schema::hasTable('shop_categories'))return $items;
        $categories=$this->categories($sellerId);
        if($selected==='uncategorized'){
            $assigned=DB::table('shop_category_products')->whereIn('shop_category_id',$categories->pluck('id'))->pluck('product_id')->all();
            return array_values(array_filter($items,fn($p)=>!in_array($p['id'],$assigned,true)));
        }
        abort_unless($categories->contains('id',(int)$selected),404);
        $ids=$categories->filter(fn($c)=>$c->id===(int)$selected || $c->parent_id===(int)$selected)->pluck('id');
        $products=DB::table('shop_category_products')->whereIn('shop_category_id',$ids)->pluck('product_id')->all();
        return array_values(array_filter($items,fn($p)=>in_array($p['id'],$products,true)));
    }
    public function vouchers(int $sellerId)
    {
        if(!BuyerCommerce::installed())return collect();
        return DB::table('commerce_vouchers')->where('seller_id',$sellerId)->where('is_active',true)->where('starts_at','<=',now())->where('ends_at','>',now())->get()->filter(function($v){
            if(!DeliveryGuarantee::voucherBelongsTo($v->id,(int)auth()->id()))return false;
            $uses=DB::table('commerce_allocations')->where('voucher_id',$v->id)->whereIn('state',['held','used']);
            $v->claimed=auth()->check() && DB::table('commerce_claims')->where('buyer_id',auth()->id())->where('voucher_id',$v->id)->exists();
            $v->exhausted=(clone $uses)->distinct()->count('usage_key')+(int)$v->legacy_used_count >= $v->usage_limit;
            $v->used=auth()->check() && (clone $uses)->where('buyer_id',auth()->id())->distinct()->count('usage_key') >= $v->per_buyer_limit;
            return !$v->exhausted;
        });
    }
}
