<?php
namespace App\Services;

use App\Models\{DeliveryAssignment,LogisticsIssue,Order,Shipment,User};
use Illuminate\Support\Facades\{DB,Schema,Validator};

/** Return custody in existing Issues; never creates a paid assignment or rewrites forward legs. */
class LogisticsRts
{
    public function act(User $user,string $shipmentId,array $input): LogisticsIssue
    {
        abort_unless(Schema::hasColumn('logistics_issues','rts'),503,'Install RTS custody migration.');
        $d=Validator::make($input,['action'=>'required|in:request,to_hub,hub_received,hub_released,to_seller,seller_received','key'=>'required|uuid','revision'=>'required|integer|min:0','reason'=>'required|string|min:3|max:500','confirmed'=>'required|accepted'])->validate();
        abort_unless(preg_match('/[\p{L}\p{N}]/u',trim($d['reason'])),422,'Enter a meaningful custody reason/reference.');
        return DB::transaction(function()use($user,$shipmentId,$d){
            GcashLocks::operation([$shipmentId],[],[$user->id]);
            $actor=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->status==='approved',403);
            $s=Shipment::whereKey($shipmentId)->lockForUpdate()->firstOrFail();$order=Order::lockFresh($s->order_id);
            $a=DeliveryAssignment::where('shipment_id',$s->id)->where('leg','delivery')->where('courier_id',$s->courier_id)->lockForUpdate()->first();
            $issue=LogisticsIssue::where('rts_shipment_id',$s->id)->lockForUpdate()->first();
            $seller=$actor->account_type==='seller' && !$actor->is_admin && !$actor->is_logistics && (int)$order->seller_id===(int)$actor->id;
            $rider=$actor->account_type==='rider' && !$actor->is_admin && !$actor->is_logistics && (int)$s->courier_id===(int)$actor->id && $actor->business_name===$s->logistics_company;
            $operator=$actor->account_type==='logistics' && !$actor->is_admin && $actor->is_logistics && ($actor->isLogisticsAdmin() || $actor->isHubStaff()) && $actor->business_name===$s->logistics_company;
            abort_unless($seller || $rider || $operator,403);
            if($operator && $actor->isHubStaff())app(LogisticsAccess::class)->receivingStaff($actor,$s->destination_hub,$s->destination_province);
            $hash=hash('sha256',json_encode([$d,$actor->id]));
            if($issue && ($issue->rts['key']??null)===$d['key']){abort_unless(($issue->rts['hash']??null)===$hash,409,'Retry key changed.');return $issue;}
            app(LogisticsRecovery::class)->assertNotRecovered($s->id);
            abort_if(DeliveryAssignment::where('shipment_id',$s->id)->whereNotNull('reassignment')->lockForUpdate()->exists(),409,'Possession review is pending.');
            abort_unless($s->shipping_status==='delivery_failed' && $order->status==='delivery_failed' && !$s->delivered_at && $a && !$a->delivered_at,422,'RTS needs an undelivered failed assignment.');
            abort_if(DB::table('logistics_financial_transactions')->where('collection_order_id',$order->id)->lockForUpdate()->exists(),422,'Recorded COD requires financial exception review.');
            abort_if(DB::table('logistics_compensations')->where('source_type','assignment')->where('source_id',$a->id)->where('status','earned')->lockForUpdate()->exists(),422,'Earned delivery needs financial exception review.');
            $state=$issue?->rts['state']??null;$revision=(int)($issue?->rts['revision']??0);
            abort_unless($revision===(int)$d['revision'],409,'Return custody changed. Refresh.');
            $local=mb_strtolower(trim($s->origin_hub))===mb_strtolower(trim($s->destination_hub)) && mb_strtolower(trim($s->origin_province))===mb_strtolower(trim($s->destination_province));
            $action=$d['action'];
            if($action==='request'){
                abort_unless($operator && !$issue,403,'Only scoped logistics personnel may open a return request.');
                $hub=\App\Models\LogisticsHub::where('company_name',$s->logistics_company)->whereRaw('LOWER(municipality) = ?',[mb_strtolower(trim($s->destination_hub))])->whereRaw('LOWER(province) = ?',[mb_strtolower(trim($s->destination_province))])->where('is_operational',true)->lockForUpdate()->firstOrFail();
                $issue=LogisticsIssue::create(['shipment_id'=>$s->id,'rts_shipment_id'=>$s->id,'company_name'=>$s->logistics_company,'hub_id'=>$hub->id,'reported_by'=>$actor->id,'kind'=>'return_to_sender','description'=>$d['reason'],'status'=>'open']);$next='requested';
            }elseif($action==='to_hub'){abort_unless($rider && $state==='requested' && $s->out_for_delivery_at,422,'Assigned rider pickup evidence required.');$next='to_hub';}
            elseif($action==='hub_received'){abort_unless($operator && $actor->isHubStaff() && $state==='to_hub',403,'Actual destination Hub Staff must receive.');$next='at_hub';}
            elseif($action==='hub_released'){abort_unless($operator && $actor->isHubStaff() && $state==='at_hub' && $local,403,'Cross-hub reverse dispatch requires separate approval.');$next='released_to_rider';}
            elseif($action==='to_seller'){abort_unless($rider && in_array($state,['requested','released_to_rider']) && $local && $s->out_for_delivery_at,422,'Local assigned-rider custody evidence required; cross-hub returns remain held.');$next='to_seller';}
            else {abort_unless($seller && $state==='to_seller' && $local,403,'Only the owning Seller can attest full sellable receipt.');$next='seller_received';}
            $issue->rts=['state'=>$next,'revision'=>$revision+1,'rider_id'=>$s->courier_id,'key'=>$d['key'],'hash'=>$hash,'actor_id'=>$actor->id,'recorded_at'=>now()->toIso8601String(),'local'=>$local];
            $issue->status=$next==='seller_received'?'resolved':'in_progress';
            if($next==='seller_received'){$issue->resolution='Seller confirmed full parcel received in sellable condition.';$issue->resolved_at=now();}
            $issue->save();
            if($next==='seller_received'){
                $s->update(['shipping_status'=>'returned','returned_at'=>now()]);$order->update(['status'=>'returned']);$order->restoreStock();
                $order->logStatus('returned',$actor->id,'Authenticated Seller confirmed full sellable physical receipt.');
                Order::notifyUser($order->buyer_id,'Return received','The Seller confirmed receipt of the returned parcel. Any payment review remains separate.','order_status',$order->id);
            }
            DB::table('logistics_issue_events')->insert(['issue_id'=>$issue->id,'actor_id'=>$actor->id,'status'=>$issue->status,'assigned_to'=>null,'note'=>json_encode(['action'=>$action,'old_state'=>$state,'new_state'=>$next,'reason'=>$d['reason'],'revision'=>$revision+1]),'created_at'=>now()]);
            app(LogisticsFinance::class)->event($s->logistics_company,'rts_custody_recorded','issue',(string)$issue->id,['action'=>$action,'old_state'=>$state,'new_state'=>$next,'shipment_status'=>['old'=>'delivery_failed','new'=>$s->shipping_status],'reason'=>$d['reason'],'revision'=>$revision+1],$actor->id);
            return $issue;
        },3);
    }
}
