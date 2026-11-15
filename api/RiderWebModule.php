<?php
declare(strict_types=1);

use App\Models\ShipmentHubLeg;
use App\Services\{HubTransferWorkflow,RiderCompensation,RiderPayouts};
use Illuminate\Support\Facades\{DB,Schema};

/** Thin mobile adapter for the existing website's rider operations. */
final class MobileRiderWebModule {
    public function __construct(private Identity $identity) {}
    public function handle(array $data): array {
        $uid=$this->identity->approved('rider');
        $user=$this->identity->user;
        ensure(!$user->is_admin && !$user->is_logistics,'Only rider accounts can use these actions.',403);
        $action=$data['action']??'';
        if ($action==='cash-submit') {
            $row=app(App\Services\LogisticsCashDesk::class)->submit($user,$data);
            return ['id'=>$row->id,'status'=>$row->status];
        }
        if ($action==='overview') {
            ensure(Schema::hasTable('logistics_compensations') && Schema::hasTable('logistics_rider_payouts') && Schema::hasTable('logistics_cash_allocations'),'Shared logistics records are unavailable.',503);
            $credits=DB::table('logistics_compensations')->where('rider_id',$uid)->where('company_name',$user->business_name)->get();
            $payouts=DB::table('logistics_rider_payouts')->where('rider_id',$uid)->where('company_name',$user->business_name)->get();
            $totals=['earned'=>0,'pending'=>0,'awaiting_payout'=>0,'prepared'=>0,'verified'=>0,'on_hold'=>0,'paid'=>0,'acknowledged'=>0];
            foreach($credits as $c) {
                if($c->status==='pending')$totals['pending']+=(int)$c->amount_cents;
                if($c->status!=='earned')continue;
                $totals['earned']+=(int)$c->amount_cents;
                $p=$payouts->firstWhere('compensation_id',$c->id);
                $stage=$p && in_array($p->status,['prepared','verified','paid'],true)?$p->status:'awaiting_payout';
                if($p && $p->hold_reason && in_array($stage,['prepared','verified'],true))$stage='on_hold';
                $totals[$stage]+=(int)$c->amount_cents;
                if($stage==='paid' && $p->acknowledged_at)$totals['acknowledged']+=(int)$c->amount_cents;
            }
            $collections=[];$cash=['collected'=>0,'confirmed'=>0,'submitted'=>0,'outstanding'=>0,'available'=>0];
            $desk=app(App\Services\LogisticsCashDesk::class);
            foreach(DB::table('logistics_financial_transactions as t')->leftJoin('shipments as s','s.id','=','t.shipment_id')->where('t.actor_id',$uid)->where('t.company_name',$user->business_name)->where('t.kind','cod_collection')->where('t.status','recorded')->select('t.*','s.tracking_number')->get() as $c) {
                $b=$desk->balances($c,false);
                $cash['collected']+=(int)$c->amount_cents;$cash['confirmed']+=$b['verified'];$cash['submitted']+=$b['reserved'];$cash['outstanding']+=$b['outstanding'];$cash['available']+=$b['available'];
                $collections[]=['id'=>$c->id,'tracking_number'=>$c->tracking_number,'hub_id'=>$c->hub_id,'amount'=>$c->amount_cents/100,'available'=>$b['available']/100,'outstanding'=>$b['outstanding']/100,'due_at'=>$desk->dueAt($c)->toIso8601String()];
            }
            $daily=[];
            foreach($credits->where('status','earned')->where('leg','delivery') as $c) {
                $day=Illuminate\Support\Carbon::parse($c->earned_at,config('app.timezone'))->setTimezone('Asia/Manila')->toDateString();
                $daily[$day]??=['date'=>$day,'successful_deliveries'=>0,'earned'=>0,'paid'=>0,'unpaid'=>0];
                $daily[$day]['successful_deliveries']++;
                $daily[$day]['earned']+=(int)$c->amount_cents;
                $p=$payouts->firstWhere('compensation_id',$c->id);
                $daily[$day][$p && $p->status==='paid'?'paid':'unpaid']+=(int)$c->amount_cents;
            }
            krsort($daily);
            foreach($daily as &$day)foreach(['earned','paid','unpaid'] as $field)$day[$field]/=100;
            unset($day);
            return ['delivery_rate'=>10,'daily'=>array_values($daily),'earnings'=>array_map(fn($v)=>$v/100,$totals),'cash'=>array_map(fn($v)=>$v/100,$cash),'collections'=>$collections,
                'jobs'=>$credits->where('status','earned')->map(fn($c)=>['id'=>$c->id,'leg'=>$c->leg,'amount'=>$c->amount_cents/100,'created_at'=>$c->earned_at])->values()->all()];
        }
        if ($action==='transfers') {
            return ['transfers'=>ShipmentHubLeg::with(['shipment','transferBatch.members'])->where('rider_id',$uid)->where('status','in_transit')->latest('started_at')->get()->map(fn($leg)=>[
                'id'=>$leg->id,'tracking_number'=>$leg->shipment?->tracking_number,'from_hub'=>$leg->from_hub,'to_hub'=>$leg->to_hub,
                'leg_type'=>$leg->leg_type,'status'=>$leg->status,'started_at'=>$leg->started_at?->toIso8601String(),
                'picked_up_at'=>$leg->rider_confirmed_pickup_at?->toIso8601String(),
                'batch'=>$leg->transferBatch && (int)$leg->transferBatch->rider_id===$uid ? ['id'=>$leg->transferBatch->id,'status'=>$leg->transferBatch->status,'parcel_count'=>$leg->transferBatch->members->count(),'received_count'=>$leg->transferBatch->members->whereNotNull('received_at')->count(),'parcels'=>$leg->transferBatch->members->map(fn($m)=>['tracking_number'=>$m->tracking_number,'received_at'=>$m->received_at?->toIso8601String()])->all()]:null,
            ])->all()];
        }
        if ($action==='confirm-transfer') {
            validateData($data,['id'=>'required|uuid','request_id'=>'required|uuid']);
            return MobileRequests::run('hub-pickup:'.$uid,$data['request_id'],$data,function()use($user,$data){
                app(HubTransferWorkflow::class)->confirmPickup($user,$data['id']);
                return ['confirmed'=>true];
            });
        }
        ensure(Schema::hasTable('logistics_rider_destinations'),'Install the rider website schema before opening payouts.',503);
        if ($action==='payouts') {
            $destinations=DB::table('logistics_rider_destinations')->where('rider_id',$uid)->orderByDesc('created_at')->orderByDesc('id')->get();
            $rows=[];
            foreach(DB::table('rider_payouts')->where('rider_id',$uid)->latest('created_at')->get() as $p) $rows[]=[
                'id'=>$p->id,'source'=>'app','amount'=>(float)$p->amount,'status'=>$p->status,'reference'=>$p->reference,
                'released_at'=>$p->released_at,'acknowledged_at'=>$p->acknowledged_at??null,'created_at'=>$p->created_at,
                'can_acknowledge'=>$p->status==='released' && empty($p->acknowledged_at),
            ];
            foreach(DB::table('logistics_rider_payouts')->where('rider_id',$uid)->latest('created_at')->get() as $p) $rows[]=[
                'id'=>$p->id,'source'=>'web','amount'=>$p->amount_cents/100,'status'=>$p->status,'reference'=>$p->reference,
                'released_at'=>$p->paid_at,'acknowledged_at'=>$p->acknowledged_at,'created_at'=>$p->created_at,
                'can_acknowledge'=>$p->status==='paid' && empty($p->acknowledged_at),
            ];
            usort($rows,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
            return ['destinations'=>$destinations->map(fn($d)=>['id'=>$d->id,'channel'=>$d->channel,'institution'=>$d->institution,'account_name'=>$d->account_name,'account_number'=>$d->account_number,'created_at'=>$d->created_at])->all(),'payouts'=>$rows];
        }
        if ($action==='destination') {
            validateData($data,['request_id'=>'required|uuid']);
            return MobileRequests::run('rider-destination:'.$uid,$data['request_id'],$data,function()use($user,$data){
                app(RiderCompensation::class)->destination($user,$data);
                return ['saved'=>true];
            });
        }
        if ($action==='acknowledge') {
            validateData($data,['id'=>'required|uuid','source'=>'required|in:app,web','received'=>'required|accepted']);
            if($data['source']==='web') app(RiderCompensation::class)->acknowledge($user,$data['id']);
            else app(RiderPayouts::class)->acknowledge($user,$data['id']);
            return ['acknowledged'=>true];
        }
        throw new ApiError('Unknown rider action.',404);
    }
}
