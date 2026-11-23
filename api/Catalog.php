<?php
declare(strict_types=1);
require_once __DIR__.'/Deals.php';
use App\Models\{Product,StoreCategory};
use Illuminate\Support\Facades\DB;

final class MobileCatalog {
    public static function handle(string $action,array $data): array {
        if($action==='deals')return MobileDeals::listing($data);
        if ($action==='details') {
            validateData($data,['product_id'=>'required|uuid']);
            $p=Product::whereKey($data['product_id'])->where('status','active')->sellerApproved()->first();ensure($p!==null,'Product unavailable.',404);
            $sale=MobileDeals::sale($p);
            if ($sale) { $sale->starts_at=\Illuminate\Support\Carbon::parse($sale->starts_at)->toIso8601String(); $sale->ends_at=\Illuminate\Support\Carbon::parse($sale->ends_at)->toIso8601String(); }
            return ['images'=>$p->images??[],'on_vacation'=>(bool)$p->seller->is_on_vacation,'sale'=>$sale?cleanRow($sale):null,
                'flash_remaining'=>$sale?->remaining??0,'flash_price'=>$sale?->price,'purchase_limit'=>null];
        }
        if ($action==='store-categories') {
            validateData($data,['seller_id'=>'required|integer']);
            return ['items'=>StoreCategory::visible()->where('seller_id',$data['seller_id'])->orderBy('position')->orderBy('id')->get(['id','parent_id','name'])->toArray()];
        }
        ensure($action==='search','Unknown catalog endpoint.',404);
        validateData($data,['query'=>'nullable|string|max:150','minimum'=>'nullable|numeric|min:0|max:1000000','maximum'=>'nullable|numeric|min:0|max:1000000',
            'rating'=>'nullable|numeric|between:0,5','category_id'=>'nullable|integer','seller_id'=>'nullable|integer','store_category_id'=>'nullable|integer','location'=>'nullable|string|max:100',
            'sort'=>'nullable|in:latest,price_low,price_high,best_selling','page'=>'nullable|integer|min:1|max:10000','limit'=>'nullable|integer|min:1|max:48']);
        ensure(!isset($data['minimum'],$data['maximum']) || $data['minimum']<=$data['maximum'],'Minimum price must not exceed maximum price.');
        $rating=DB::table('reviews')->selectRaw('product_id,AVG(rating) AS average_rating')->groupBy('product_id');
        $q=DB::table('products as p')->join('users as u','u.id','=','p.seller_id')->leftJoinSub($rating,'r','r.product_id','=','p.id')
            ->where('p.status','active')->where('u.status','approved')->where('u.account_type','seller');
        if (isset($data['minimum'])) $q->whereRaw('COALESCE(p.discount_price,p.price)>=?',[$data['minimum']]);
        if (isset($data['maximum'])) $q->whereRaw('COALESCE(p.discount_price,p.price)<=?',[$data['maximum']]);
        foreach (['category_id','seller_id'] as $field) if (!empty($data[$field])) $q->where('p.'.$field,$data[$field]);
        if (!empty($data['store_category_id'])) $q->whereIn('p.id',DB::table('store_category_products')->where('store_category_id',$data['store_category_id'])->select('product_id'));
        if (!empty($data['rating'])) $q->where('r.average_rating','>=',$data['rating']);
        $literal=fn($s)=>str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$s);
        if (!empty($data['location'])) $q->where(fn($x)=>$x->where('u.province','like','%'.$literal(trim($data['location'])).'%')->orWhere('u.municipality','like','%'.$literal(trim($data['location'])).'%'));
        $term=trim($data['query']??'');$suggestion=null;
        if ($term!=='') {
            $search=(clone $q)->where('p.name','like','%'.$literal($term).'%');
            if (!(clone $search)->exists() && preg_match('/^[\p{L}0-9 -]+$/u',$term)) {
                $best=INF;$candidate=null;
                foreach ((clone $q)->limit(300)->get(['p.name']) as $r) {
                    $words=preg_split('/\s+/',mb_strtolower($r->name));$score=0;
                    foreach (preg_split('/\s+/',mb_strtolower($term)) as $word) $score+=min(array_map(fn($w)=>levenshtein($word,$w),$words));
                    $score/=max(1,count(preg_split('/\s+/',$term)));
                    if ($score<$best) {$best=$score;$candidate=$r->name;}
                }
                if ($best<=1.5 && $candidate) { $suggestion=$candidate; $search=(clone $q)->where('p.name',$candidate); }
            }
            $q=$search;
        }
        $sort=$data['sort']??'latest';
        if ($sort==='best_selling') {
            $sales=DB::table('orders')->crossJoin(DB::raw("JSON_TABLE(items,'$[*]' COLUMNS(product_id VARCHAR(36) PATH '$.product_id',qty INT PATH '$.qty')) sold"))
                ->whereIn('status',['delivered','completed'])->selectRaw('sold.product_id,SUM(sold.qty) units')->groupBy('sold.product_id');
            $q->leftJoinSub($sales,'sales','sales.product_id','=','p.id')->orderByDesc('sales.units');
        } elseif ($sort==='price_low' || $sort==='price_high') $q->orderByRaw('COALESCE(p.discount_price,p.price) '.($sort==='price_low'?'ASC':'DESC'));
        else $q->orderByDesc('p.created_at');
        $q->orderBy('p.id');$limit=(int)($data['limit']??24);$page=(int)($data['page']??1);
        $rows=$q->select(['p.*','u.business_name','u.given_names','u.last_name','u.username','u.municipality','u.province','u.is_on_vacation','r.average_rating'])->offset(($page-1)*$limit)->limit($limit+1)->get();
        $shops=[];
        if ($term!=='' && empty($data['seller_id']) && $page===1) {
            $shops=cleanRows(DB::table('users')->where('account_type','seller')->where('status','approved')
                ->where(fn($q)=>$q->where('business_name','like','%'.$literal($term).'%')->orWhere('username','like','%'.$literal($term).'%'))
                ->orderBy('business_name')->limit(8)->get(['id','business_name','given_names','last_name','username','municipality','province']));
        }
        return ['shops'=>$shops,'items'=>cleanRows($rows->take($limit)->all()),'has_more'=>$rows->count()>$limit,'suggestion'=>$suggestion,'page'=>$page];
    }
}
