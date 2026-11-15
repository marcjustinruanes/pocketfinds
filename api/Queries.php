<?php
declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Services\LogisticsAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class MobileQueries {
    private const TABLES=['products','categories','users','logistics_centers','payment_methods','orders','shipments','cart_items','shop_follows','wishlist_items','platform_vouchers','policies','id_types'];
    private const SELLER_COLUMNS=['id','given_names','last_name','business_name','username','municipality','province','is_on_vacation','profile_picture','store_description','store_banner','created_at'];
    public function __construct(private Identity $identity) {}
    public function handle(array $input): array {
        $table=(string)($input['table']??'');
        ensure(in_array($table,self::TABLES,true),'Table is not available.',403);
        $physical=$table==='wishlist_items'?'wishlist_entries':$table;
        $action=$input['action']??'select';
        if ($table==='logistics_centers' && $action==='select') return $this->centers($input);
        ensure(in_array($action,['select','insert','update','delete'],true),'Unsupported query.');
        ensure($action==='select' || in_array($table,['cart_items','wishlist_items','shop_follows'],true),'Use an authorized action endpoint to change this data.',403);
        $available=Schema::getColumnListing($physical);
        $virtualPlatform=$table==='orders' && !in_array('platform_payment',$available,true);
        if($virtualPlatform) $available[]='platform_payment';
        if ($table==='users') $available=self::SELLER_COLUMNS;
        if ($table==='shipments') $available=array_values(array_diff($available,['delivery_otp_hash','delivery_otp_ciphertext']));
        if ($table==='policies') $available=array_values(array_intersect($available,['id','type','account_type','company_name','title','content','created_at','updated_at']));
        $columns=trim((string)($input['columns']??'*'));
        $columns=$columns==='*'?$available:array_map('trim',explode(',',$columns));
        ensure(count($columns)>0 && count(array_diff($columns,$available))===0,'A requested column is not available.',403);
        $q=DB::table($physical);
        $this->scope($q,$table);
        foreach ($input['filters']??[] as $f) {
            $column=$f['column']??''; $op=$f['op']??''; $value=$f['value']??null;
            ensure(in_array($column,$available,true),'Filter column is not available.',403);
            if ($table==='orders' && in_array($column,['app_buyer_id','app_seller_id'],true)) $column=$column==='app_buyer_id'?'buyer_id':'seller_id';
            if ($table==='orders' && $column==='status' && $op==='=') {
                $q->whereIn('status',match($value) {
                    'to_ship'=>['placed','confirmed','preparing','ready_for_pickup','to_ship'],
                    'in_transit'=>['picked_up','at_sorting_center','hub_transfer','sorted','assigned_to_rider','in_transit','delivery_failed','rescheduled','return_in_transit'],
                    'completed'=>['completed'], 'refunds'=>['return_requested','return_in_progress','refunded'], 'delivered'=>['delivered'], 'cancelled'=>['cancelled','returned'], default=>[$value],
                }); continue;
            }
            if ($table==='shipments' && $column==='shipping_status') {
                $values=$op==='in'?(array)$value:[$value];
                $mapped=[];
                foreach ($values as $status) $mapped=array_merge($mapped,match($status) {
                    'available'=>['sorted','available'], 'accepted'=>['assigned_to_rider','accepted'], default=>[$status],
                });
                $q->whereIn($column,$mapped); continue;
            }
            match($op) {
                '='=>$q->where($column,$value),
                'like'=>$q->where($column,'like',(string)$value),
                'is'=>$value===null?$q->whereNull($column):$q->where($column,$value),
                'in'=>$q->whereIn($column,array_slice((array)$value,0,1000)),
                default=>throw new ApiError('Unsupported filter.'),
            };
        }
        if ($action!=='select') return $this->write($q,$table,$physical,$action,(array)($input['data']??[]));
        foreach ($input['order']??[] as $order) {
            ensure(in_array($order['column']??'',$available,true),'Order column is not available.');
            $q->orderBy($order['column'],($order['ascending']??true)?'asc':'desc');
        }
        // Read native identity/status columns for normalization, even for a narrow projection.
        $extra=match($table) { 'orders'=>['buyer_id','seller_id'],default=>[] };
        $selected=array_unique(array_merge($columns,$extra));
        if($virtualPlatform) $selected=array_map(fn($column)=>$column==='platform_payment'?DB::raw('0 as platform_payment'):$column,$selected);
        $rows=cleanRows($q->limit(max(1,min(1000,(int)($input['limit']??1000))))->get($selected));
        foreach ($rows as &$row) {
            if ($table==='orders') {
                if (in_array('app_buyer_id',$columns,true)) $row['app_buyer_id']=$row['buyer_id'];
                if (in_array('app_seller_id',$columns,true)) $row['app_seller_id']=$row['seller_id'];
                // Preserve native states so Preparing and Ready for Pickup stay distinct.
            }

            $row=array_intersect_key($row,array_flip($columns));
        }
        return $rows;
    }
    public static function buyerStatus(string $value): string {
        return match($value) {
            'placed','confirmed','preparing','ready_for_pickup'=>'to_ship',
            'picked_up','at_sorting_center','hub_transfer','sorted','assigned_to_rider'=>'in_transit',
            default=>$value,
        };
    }
    private function centers(array $input): array {
        $hubs=\App\Models\LogisticsHub::orderBy('id')->get();
        $rows=[];
        foreach ($hubs as $hub) {
            $state=app(\App\Services\HubHiring::class)->availability($hub,'rider');
            $rows[]=['id'=>$hub->id,'name'=>$hub->name?:$hub->company_name,'location'=>trim($hub->municipality.', '.$hub->province,', '),
                'address'=>$hub->address??'','hours_note'=>null,'accepting_applications'=>$state['eligible'],'available_slots'=>$state['remaining'],'service_area'=>$hub->province];
        }
        foreach ($input['filters']??[] as $filter) {
            ensure(($filter['column']??'')==='id' && ($filter['op']??'')==='=','Unsupported center filter.');
            $rows=array_values(array_filter($rows,fn($row)=>(string)$row['id']===(string)$filter['value']));
        }
        return $rows;
    }
    private function scope($q,string $table): void {
        if ($table==='products') {
            $q->where('status','active')->whereIn('seller_id',DB::table('users')->select('id')->where('account_type','seller')->where('status','approved')); return;
        }
        if ($table==='users') { $q->where('account_type','seller')->where('status','approved'); return; }
        if (in_array($table,['categories','logistics_centers','id_types','policies'],true)) return;
        if ($table==='payment_methods') {
            $q->whereIn('id',\App\Models\PaymentMethod::forBuyerCheckout()->select('id')); return;
        }
        if ($table==='platform_vouchers') { $q->where('is_active',true); return; }
        $id=$this->identity->approved();
        if (in_array($table,['cart_items','wishlist_items','shop_follows'],true)) { $this->identity->approved('buyer'); $q->where('buyer_id',$id); return; }
        if ($table==='orders') {
            if ($this->identity->user->account_type==='rider') {
                $q->whereIn('id',DB::table('shipments')->select('order_id')->where(function($s) use ($id) {
                    $s->where('courier_id',$id)->orWhereIn('id',$this->openDeliveryIds());
                }));
            } else { $this->identity->approved('buyer'); $q->where('buyer_id',$id); }
            return;
        }
        if ($table==='shipments') {
            if ($this->identity->user->account_type==='rider') {
                $q->where(fn($s)=>$s->where('courier_id',$id)->orWhereIn('id',$this->openDeliveryIds()));
            } else {
                $this->identity->approved('buyer');
                $q->whereIn('order_id',DB::table('orders')->select('id')->where('buyer_id',$id));
            }
        }
    }
    private function openDeliveryIds(): array {
        return DB::table('delivery_assignments')->where('courier_id',$this->identity->id())->where('leg','delivery')->where('status','offered')->where('expires_at','>',now())->pluck('shipment_id')->all();
    }
    private function write($q,string $table,string $physical,string $action,array $data): array {
        $id=$this->identity->approved('buyer');
        if ($action==='delete') { $q->delete(); return []; }
        if ($action==='update') {
            ensure($table==='cart_items','This update is not allowed.',403);
            validateData($data,['qty'=>'required|integer|min:1|max:99999']);
            return DB::transaction(function() use($q,$data,$id) {
                User::whereKey($id)->lockForUpdate()->firstOrFail();
                foreach ((clone $q)->lockForUpdate()->get() as $row) {
                    $p=Product::whereKey($row->product_id)->where('status','active')->lockForUpdate()->first();
                    ensure($p && $p->seller?->status==='approved' && !$p->seller->is_on_vacation,'This cart item is unavailable.');
                    ensure($data['qty']<=$p->availableStock($row->variation_group?:null,$row->variation_value?:null),'Requested quantity exceeds available stock.');
                    MobileCommerce::price($p,$row->variation_group??'',$row->variation_value??'');
                }
                $q->update(['qty'=>(int)$data['qty'],'updated_at'=>now()]); return [];
            });
        }
        ensure(!isset($data['buyer_id']) || (int)$data['buyer_id']===$id,'Buyer does not match your account.',403);
        if ($table==='shop_follows') {
            validateData($data,['seller_id'=>'required|integer']);
            ensure(User::whereKey($data['seller_id'])->where('account_type','seller')->where('status','approved')->exists(),'Shop unavailable.');
            DB::table($physical)->insertOrIgnore(['id'=>(string)Str::uuid(),'buyer_id'=>$id,'seller_id'=>$data['seller_id'],'created_at'=>now()]); return [];
        }
        validateData($data,['product_id'=>'required|uuid']);
        $product=Product::whereKey($data['product_id'])->where('status','active')->sellerApproved()->first();
        ensure($product!==null,'Product is no longer available.');
        if ($table==='wishlist_items') {
            DB::table($physical)->insertOrIgnore(['id'=>(string)Str::uuid(),'buyer_id'=>$id,'product_id'=>$product->id,'product_name'=>$product->name,
                'image'=>$product->image,'price'=>$product->discount_price??$product->price,'created_at'=>now(),'updated_at'=>now()]); return [];
        }
        validateData($data,['qty'=>'required|integer|min:1|max:99','variation_group'=>'nullable|string|max:255','variation_value'=>'nullable|string|max:255']);
        $seller=$product->seller;
        ensure(!$seller->is_on_vacation,'This shop is on vacation.');
        [$group,$value]=[$data['variation_group']??'',$data['variation_value']??''];
        $price=MobileCommerce::price($product,$group,$value);
        ensure($product->availableStock($group?:null,$value?:null)>=(int)$data['qty'],'Not enough stock for this option.');
        DB::table('cart_items')->insert(['id'=>(string)Str::uuid(),'buyer_id'=>$id,'product_id'=>$product->id,'seller_slug'=>$seller->username,
            'seller'=>$seller->business_name?:trim($seller->given_names.' '.$seller->last_name),'name'=>$product->name,'img'=>$product->image??'',
            'price'=>$price,'qty'=>(int)$data['qty'],'variation_group'=>$group,'variation_value'=>$value,'created_at'=>now(),'updated_at'=>now()]);
        return [];
    }
}
