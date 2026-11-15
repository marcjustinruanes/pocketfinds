<?php
declare(strict_types=1);
use Illuminate\Support\Facades\{DB,Schema,Storage};

/** Additive legacy import. Shared records are never overwritten. Run with old clients offline. */
final class BuyerCommerceAlignment {
    public static function run(bool $apply=false): array {
        return DB::transaction(function()use($apply){
            $report=['returns'=>0,'reminders'=>0,'conflicts'=>[]];
            foreach(DB::table('mobile_commerce_returns')->orderBy('id')->lockForUpdate()->get() as $legacy){
                $existing=DB::table('commerce_returns')->where('order_id',$legacy->order_id)->first();
                if($existing){
                    if($existing->id!==$legacy->id)$report['conflicts'][]='return:'.$legacy->id;
                    continue;
                }
                $order=DB::table('orders')->where('id',$legacy->order_id)->lockForUpdate()->first();
                if(!$order || (int)$order->buyer_id!==(int)$legacy->buyer_id || (int)$order->seller_id!==(int)$legacy->seller_id){$report['conflicts'][]='owner:'.$legacy->id;continue;}
                $photos=json_decode($legacy->photos,true,512,JSON_THROW_ON_ERROR);$valid=true;
                foreach($photos as &$photo){
                    if(!is_string($photo) || !str_starts_with($photo,$legacy->buyer_id.'/') || str_contains($photo,'..') || str_contains($photo,'\\')){$valid=false;break;}
                    $photo='return-evidence/'.$photo;
                    if(!Storage::disk('local')->exists($photo)){$valid=false;break;}
                }unset($photo);
                if(!$valid){$report['conflicts'][]='evidence:'.$legacy->id;continue;}
                $previous=null;
                if($order->status==='return_requested'){
                    $previous=DB::table('order_status_history')->where('order_id',$order->id)->whereIn('status',['delivered','completed'])->latest('created_at')->value('status');
                    if(!$previous){$report['conflicts'][]='status:'.$legacy->id;continue;}
                }
                $report['returns']++;
                if($apply){
                    $row=array_intersect_key((array)$legacy,array_flip(Schema::getColumnListing('commerce_returns')));
                    $row['photos']=json_encode($photos,JSON_THROW_ON_ERROR);
                    DB::table('commerce_returns')->insert($row);
                    if($previous)DB::table('orders')->where('id',$order->id)->update(['status'=>$previous]);
                }
            }
            foreach(DB::table('mobile_commerce_reminders')->orderBy('id')->lockForUpdate()->get() as $reminder){
                $sale=DB::table('mobile_commerce_flash_sales')->where('id',$reminder->flash_sale_id)->first();
                if(!$sale || !$sale->is_active || $sale->ends_at<=now()->toDateTimeString())continue;
                $matches=DB::table('commerce_flash_sales');
                foreach(['product_id','seller_id','price','quantity','starts_at','ends_at'] as $key)$matches->where($key,$sale->$key);
                $matches=$matches->get();
                if($matches->count()!==1){$report['conflicts'][]='reminder:'.$reminder->id;continue;}
                $id=$matches->first()->id;
                if(DB::table('commerce_reminders')->where('buyer_id',$reminder->buyer_id)->where('flash_sale_id',$id)->exists())continue;
                $report['reminders']++;
                if($apply)DB::table('commerce_reminders')->insert(['buyer_id'=>$reminder->buyer_id,'flash_sale_id'=>$id,'sent_at'=>$reminder->sent_at]);
            }
            if($apply && $report['conflicts'])throw new RuntimeException('Import cancelled; resolve conflicts first: '.implode(', ',$report['conflicts']));
            return $report;
        });
    }
}
