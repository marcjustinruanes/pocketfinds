<?php
declare(strict_types=1);
require_once __DIR__.'/WebsiteAftercare.php';
use App\Models\{Order,Review,User,Product};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class MobileAftercare {
    public function __construct(private Identity $identity) {}
    public static function linkLegacyReviews(Order $order): void {
        foreach (DB::table('reviews')->where('order_id',$order->id)->get() as $review) {
            if (DB::table('mobile_buyer_reviews')->where('review_id',$review->id)->exists()) continue;
            foreach ($order->items as $key=>$item) {
                if (($item['product_id']??null)!==$review->product_id) continue;
                DB::table('mobile_buyer_reviews')->insertOrIgnore(['order_id'=>$order->id,'item_key'=>$key,'review_id'=>$review->id,
                    'photos'=>json_encode($review->media_url?[$review->media_url]:[]),'edited_at'=>null]);
                break;
            }
        }
    }
    public static function eventDate(Order $order,string $status): Carbon {
        $date=DB::table('order_status_history')->where('order_id',$order->id)->where('status',$status)->max('created_at');
        return Carbon::parse(($status==='delivered'?$order->shipment?->delivered_at:null)??$date??$order->updated_at);
    }
    public static function hasOpenReturn(string $id): bool {
        return WebsiteAftercare::open($id);
    }
    public static function photos(array $paths,int $uid,string $bucket): array {
        ensure(count($paths)<=5,'Attach at most five photos.');
        foreach ($paths as $path) {
            ensure(is_string($path) && str_starts_with($path,$uid.'/') && !str_contains($path,'..'),'Use your own uploaded photos.',403);
            $stored=$bucket==='return-evidence'?'return-evidence/'.$path:'review-media/'.$path;
            $disk=$bucket==='return-evidence'?'local':'public';
            ensure(Storage::disk($disk)->exists($stored),'Upload each photo before submitting.');
            ensure(Storage::disk($disk)->size($stored)<=5*1024*1024 && in_array(Storage::disk($disk)->mimeType($stored),['image/jpeg','image/png'],true),'Use JPG or PNG photos of at most 5 MB.');
        }
        return array_values($paths);
    }
    public function handle(string $action,array $data): mixed {
        $uid=$this->identity->approved();
        if(in_array($action,['decision','returns','return','escalate'],true)) return (new WebsiteAftercare($this->identity))->handle($action,$data);
        $this->identity->approved('buyer');
        validateData($data,['order_id'=>'required|uuid']);
        $order=Order::where('buyer_id',$uid)->find($data['order_id']);ensure($order!==null,'Order not found.',404);
        if($action==='service-review') {
            validateData($data,['request_id'=>'required|uuid','review_type'=>'required|in:shop,rider','photos'=>'nullable|array|max:5','photos.*'=>'required|string']);
            return MobileRequests::run('service-review:'.$uid,$data['request_id'],$data,function()use($data,$order,$uid){
                $paths=self::photos($data['photos']??[],$uid,'review-media');
                $files=array_map(fn($p)=>new \Illuminate\Http\UploadedFile(Storage::disk('public')->path('review-media/'.$p),basename($p),null,null,true),$paths);
                return (new MobileBuyer($this->identity))->native($data,function($unused,$request)use($files,$order){
                    $request->files->set('media',$files);
                    return app(\App\Http\Controllers\CommerceController::class)->review($request,$order);
                });
            });
        }
        if ($action==='details') {
            self::linkLegacyReviews($order);
            $reviews=DB::table('mobile_buyer_reviews as m')->join('reviews as r','r.id','=','m.review_id')->where('m.order_id',$order->id)->select('r.*','m.item_key','m.photos','m.edited_at')->get();
            $serviceReviews=cleanRows(DB::table('order_service_reviews')->where('order_id',$order->id)->where('buyer_id',$uid)->get(['review_type','rating','comment','media','created_at']));
            foreach($serviceReviews as &$review)$review['media']=json_decode($review['media']??'[]',true);unset($review);
            return ['items'=>$order->items,'reviews'=>cleanRows($reviews),
                'service_reviews'=>$serviceReviews,
                'can_review'=>$order->status==='completed' && self::eventDate($order,'completed')->gte(now()->subDays(30))]
                +(new WebsiteAftercare($this->identity))->details($order);
        }
        if ($action==='review') {
            validateData($data,['request_id'=>'required|uuid','item_key'=>'required|integer|min:0','rating'=>'required|integer|between:1,5','comment'=>'nullable|string|max:500',
                'photos'=>'nullable|array|max:5','photos.*'=>'required|string','edit'=>'nullable|boolean','is_anonymous'=>'nullable|boolean']);
            return MobileRequests::run('review:'.$uid,$data['request_id'],$data,function() use($order,$data,$uid) {
                $o=Order::lockFresh($order->id);
                self::linkLegacyReviews($o);
                ensure($o->status==='completed' && self::eventDate($o,'completed')->gte(now()->subDays(30)),'Reviews are available only within 30 days of completion.');
                $item=$o->items[$data['item_key']]??null;ensure(is_array($item),'Choose an item from your order.');
                $slot=DB::table('mobile_buyer_reviews')->where('order_id',$o->id)->where('item_key',$data['item_key'])->lockForUpdate()->first();
                if (!empty($data['edit'])) ensure($slot && !$slot->edited_at,'A review can be edited only once.');
                else ensure(!$slot,'You already reviewed this item. Use Edit.');
                $paths=self::photos($data['photos']??[],$uid,'review-media');
                $values=['buyer_id'=>$uid,'seller_id'=>$o->seller_id,'product_id'=>$item['product_id'],'rating'=>$data['rating'],'comment'=>$data['comment']??null,
                    'is_anonymous'=>$data['is_anonymous']??false,'media_url'=>$paths[0]??null];
                // Keep per-item order linkage in the app table, compatible with older
                // website schemas that enforce one review per orders.order_id.
                $id=$slot?->review_id??(string)Str::uuid();
                if ($slot) DB::table('reviews')->where('id',$id)->where('buyer_id',$uid)->update($values);
                else DB::table('reviews')->insert(['id'=>$id,'order_id'=>null,'created_at'=>now()]+$values);
                DB::table('mobile_buyer_reviews')->updateOrInsert(['order_id'=>$o->id,'item_key'=>$data['item_key']],['review_id'=>$id,'photos'=>json_encode($paths),'edited_at'=>$slot?now():null]);
                return ['saved'=>true,'review_id'=>$id];
            });
        }
        throw new ApiError('Unknown aftercare action.',404);
    }
    public static function decorateReviews(array $rows,bool $private=false): array {
        foreach ($rows as &$row) {
            $meta=DB::table('mobile_buyer_reviews')->where('review_id',$row['id'])->first();
            $row['photos']=$meta?json_decode($meta->photos,true):(!empty($row['media_url'])?[$row['media_url']]:[]);
            if ($private && $meta) { $row['order_id']=$meta->order_id;$row['item_key']=$meta->item_key;$row['edited_at']=$meta->edited_at; }
            if ($meta) {
                $items=Order::find($meta->order_id)?->items??[];
                $row['variant_label']=$items[$meta->item_key]['variation_value']??'';
            }
        } unset($row);return $rows;
    }
    public static function sweep(): void {
        app(\App\Services\BuyerCommerce::class)->sweep();
        Order::autoCompleteDelivered();
    }
}
