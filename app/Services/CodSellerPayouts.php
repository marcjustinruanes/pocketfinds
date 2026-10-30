<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CodSellerPayouts
{
    public function eligibility(Order $order,string $company,bool $lock=false): array
    {
        $result=['eligible'=>false,'reason'=>'Awaiting delivered COD and verified financial records.','product_cents'=>0,'shipping_cents'=>0,'collection_id'=>null];
        $shipment=Shipment::where('order_id',$order->id)->where('logistics_company',$company)->when($lock,fn($q)=>$q->lockForUpdate())->first();
        if(!$shipment || !app(CodCollection::class)->isCod($order) || !in_array($order->status,['delivered','completed']) || !in_array($shipment->shipping_status,['delivered','completed']))return $result;
        $ob=DB::table('logistics_shipping_obligations')->where('shipment_id',$shipment->id)->where('company_name',$company)->when($lock,fn($q)=>$q->lockForUpdate())->first();
        if(!$ob || $ob->collection_mode!=='cod'){ $result['reason']='Legacy or unvalidated shipping entitlement requires financial review.';return $result; }
        $collection=DB::table('logistics_financial_transactions')->where('collection_shipment_id',$shipment->id)->where('kind','cod_collection')->where('status','recorded')
            ->where('company_name',$company)->when($lock,fn($q)=>$q->lockForUpdate())->first();
        if(!$collection)return $result;
        $result['collection_id']=$collection->id;$result['shipping_cents']=(int)$ob->amount_cents;
        $total=app(LogisticsCashDesk::class)->amount((string)$order->total);
        if((int)$collection->amount_cents!==$total || app(LogisticsCashDesk::class)->balances($collection,$lock)['verified']!==$total){$result['reason']='The full COD collection must be verified by Hub Staff.';return $result;}
        if($total<(int)$ob->amount_cents){$result['reason']='Collected COD cannot cover the approved shipping entitlement. Funding exception review is required.';return $result;}
        $result['product_cents']=$total-(int)$ob->amount_cents;
        if(Schema::hasTable('commerce_returns')){
            $returns=DB::table('commerce_returns')->where('order_id',$order->id)->when($lock,fn($q)=>$q->lockForUpdate())->get();
            foreach($returns as $return){
                if($return->status!=='rejected' || !$return->decided_at || CarbonImmutable::parse($return->decided_at,config('app.timezone'))->addDays(3)->isFuture()){
                    $result['reason']='Return/refund review or its existing appeal window prevents payout.';return $result;
                }
            }
        }
        if(Schema::hasTable('complaints') && DB::table('complaints')->where('order_id',$order->id)->where(fn($q)=>$q->where('status','!=','resolved')->orWhereNull('status'))
            ->when($lock,fn($q)=>$q->lockForUpdate())->get(['id'])->isNotEmpty()){$result['reason']='An active order dispute prevents payout.';return $result;}
        $delivered=$shipment->delivered_at ?? DB::table('order_status_history')->where('order_id',$order->id)->where('status','delivered')->min('created_at');
        $confirmed=$order->buyer_confirmed_at || DB::table('order_status_history')->where('order_id',$order->id)->where('status','completed')->where('changed_by',$order->buyer_id)->exists();
        if(!$delivered){$result['reason']='A reliable delivery timestamp is required.';return $result;}
        $eligibleAt=CarbonImmutable::parse($delivered,config('app.timezone'))->addHours(168);
        $result['eligible_at']=$eligibleAt;
        if(!$confirmed && $eligibleAt->isFuture()){$result['reason']='Awaiting buyer confirmation or 168 hours after delivery.';return $result;}
        $result['eligible']=true;$result['reason']='Eligible: full verified COD funds and buyer confirmation or the 168-hour timeout.';
        return $result;
    }

    private function locked(User $actor,string $orderId): array
    {
        $peek=Order::findOrFail($orderId);
        $shipment=Shipment::where('order_id',$orderId)->firstOrFail();
        GcashLocks::operation([$shipment->id],[],[$actor->id,$peek->seller_id]);
        $actor=app(LogisticsTreasury::class)->admin($actor);
        $order=Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
        abort_unless($shipment->logistics_company===$actor->business_name,403);
        return [$actor,$order];
    }

    public function prepare(User $actor,array $input): void
    {
        $data=Validator::make($input,['order_id'=>'required|uuid','account_id'=>'required|uuid','destination_id'=>'required|uuid',
            'adjustment'=>'required|string','adjustment_reason'=>'nullable|string|max:2000'])->validate();
        $treasury=app(LogisticsTreasury::class);$adjustment=app(LogisticsCashDesk::class)->amount($data['adjustment']);
        if($adjustment>0 && mb_strlen(trim($data['adjustment_reason']??''))<3)$treasury->fail('Document why the separately funded positive adjustment is approved.');
        DB::transaction(function()use($actor,$data,$adjustment,$treasury){
            [$actor,$order]=$this->locked($actor,$data['order_id']);
            $eligibility=$this->eligibility($order,$actor->business_name,true);
            if(!$eligibility['eligible'])$treasury->fail($eligibility['reason']);
            $account=$treasury->account($data['account_id'],$actor->business_name);$treasury->requireGcash($account);
            $destination=DB::table('logistics_payout_destinations')->where('id',$data['destination_id'])->where('seller_id',$order->seller_id)->lockForUpdate()->firstOrFail();
            $treasury->requireGcash($destination);
            $old=DB::table('logistics_seller_payouts')->where('order_id',$order->id)->lockForUpdate()->first();
            abort_if($old && $old->status!=='cancelled',409,'This order already has a reserved or recorded payout.');
            $product=$eligibility['product_cents'];
            if($product+$adjustment<=0)$treasury->fail('There is no positive seller payment to prepare.');
            if($treasury->available($account->id,'cod')<$product || $treasury->available($account->id,'adjustment')<$adjustment)$treasury->fail('Insufficient available verified treasury funds. COD proceeds and adjustments need their own funded balances.');
            $id=$old?->id ?? (string)Str::uuid();
            $values=['company_name'=>$actor->business_name,'order_id'=>$order->id,'collection_id'=>$eligibility['collection_id'],'seller_id'=>$order->seller_id,
                'treasury_account_id'=>$account->id,'destination_id'=>$destination->id,'product_cents'=>$product,'shipping_cents'=>$eligibility['shipping_cents'],
                'adjustment_cents'=>$adjustment,'adjustment_reason'=>$data['adjustment_reason']??null,'status'=>'prepared','revision'=>($old?->revision??0)+1,
                'prepared_by'=>$actor->id,'verified_by'=>null,'verified_at'=>null,'hold_reason'=>null,'notes'=>null,'updated_at'=>now()];
            if($old)DB::table('logistics_seller_payouts')->where('id',$id)->update($values);
            else DB::table('logistics_seller_payouts')->insert($values+['id'=>$id,'created_at'=>now()]);
            $treasury->event($actor,'seller_payout_prepared','seller_payout',$id,['product_cents'=>$product,'shipping_cents'=>$eligibility['shipping_cents'],'adjustment_cents'=>$adjustment,
                'adjustment_reason'=>$values['adjustment_reason'],'account_id'=>$account->id,'destination_id'=>$destination->id,'previous_revision'=>$old?->revision]);
        },3);
    }

    private function payout(User $actor,string $id,int $revision): array
    {
        $peek=DB::table('logistics_seller_payouts')->where('id',$id)->firstOrFail();
        [$actor,$order]=$this->locked($actor,$peek->order_id);
        $account=app(LogisticsTreasury::class)->account($peek->treasury_account_id,$actor->business_name);
        $p=DB::table('logistics_seller_payouts')->where('id',$id)->where('company_name',$actor->business_name)->lockForUpdate()->firstOrFail();
        abort_unless((int)$p->revision===$revision,409,'Payout changed. Refresh before continuing.');
        return [$actor,$order,$account,$p];
    }

    private function recheck(Order $order,object $account,object $p): void
    {
        $treasury=app(LogisticsTreasury::class);$treasury->requireGcash($account);$treasury->requireGcash(DB::table('logistics_payout_destinations')->where('id',$p->destination_id)->firstOrFail());$eligible=$this->eligibility($order,$p->company_name,true);
        if(!$eligible['eligible'])$treasury->fail($eligible['reason']);
        if($p->hold_reason)$treasury->fail('Release the documented payout hold before continuing.');
        if($eligible['collection_id']!==$p->collection_id || $eligible['product_cents']!==(int)$p->product_cents || $eligible['shipping_cents']!==(int)$p->shipping_cents)$treasury->fail('The financial basis changed. Cancel and review this payout.');
        if($treasury->available($account->id,'cod',$p->id)<(int)$p->product_cents || $treasury->available($account->id,'adjustment',$p->id)<(int)$p->adjustment_cents)$treasury->fail('Reserved payout funds are no longer available.');
    }

    public function action(User $actor,string $id,array $input): void
    {
        $data=Validator::make($input,['revision'=>'required|integer|min:1','action'=>'required|in:verify,cancel,hold,release','notes'=>'required|string|min:3|max:2000'])->validate();
        DB::transaction(function()use($actor,$id,$data){
            [$actor,$order,$account,$p]=$this->payout($actor,$id,(int)$data['revision']);$treasury=app(LogisticsTreasury::class);
            abort_unless(in_array($p->status,['prepared','verified']),409,'A recorded payout cannot be changed.');
            $values=['revision'=>$p->revision+1,'updated_at'=>now()];
            if($data['action']==='verify'){
                abort_unless($p->status==='prepared',409);$this->recheck($order,$account,$p);
                $values+=['status'=>'verified','verified_by'=>$actor->id,'verified_at'=>now()];
            }elseif($data['action']==='cancel')$values+=['status'=>'cancelled','notes'=>$data['notes']];
            elseif($data['action']==='hold')$values['hold_reason']=$data['notes'];
            else $values['hold_reason']=null;
            DB::table('logistics_seller_payouts')->where('id',$id)->update($values);
            $treasury->event($actor,'seller_payout_'.$data['action'],'seller_payout',$id,['notes'=>$data['notes'],'previous_status'=>$p->status,'revision'=>$values['revision']]);
        },3);
    }

    public function pay(User $actor,string $id,array $input,UploadedFile $file): void
    {
        $data=Validator::make($input,['revision'=>'required|integer|min:1','amount'=>'required|string','reference'=>['required','string','regex:/^[A-Za-z0-9-]{6,64}$/D'],
            'sent'=>'required|accepted','notes'=>'required|string|min:3|max:2000'])->validate();
        $amount=app(LogisticsCashDesk::class)->amount($data['amount']);
        DB::transaction(function()use($actor,$id,$data,$amount,$file){
            [$actor,$order,$account,$p]=$this->payout($actor,$id,(int)$data['revision']);$treasury=app(LogisticsTreasury::class);
            abort_unless($p->status==='verified',409,'Verify this payout before recording actual payment.');
            $this->recheck($order,$account,$p);
            if($amount!==(int)$p->product_cents+(int)$p->adjustment_cents)$treasury->fail('Confirm the exact prepared payout amount. Partial, excessive or negative payments are not accepted.');
            $reference=$treasury->reference($account,$data['reference'],'seller_payout',$id);
            $destination=DB::table('logistics_payout_destinations')->where('id',$p->destination_id)->where('seller_id',$p->seller_id)->lockForUpdate()->firstOrFail();
            $treasury->reference($destination,$data['reference'],'seller_payout',$id);
            $treasury->evidence($actor,'seller_payout',$id,$file);
            if((int)$p->product_cents>0)$treasury->move($actor,$account,'cod',-(int)$p->product_cents,'seller_payout',$id,$reference);
            if((int)$p->adjustment_cents>0)$treasury->move($actor,$account,'adjustment',-(int)$p->adjustment_cents,'seller_payout',$id,(int)$p->product_cents===0?$reference:null);
            DB::table('logistics_financial_transactions')->insert(['id'=>(string)Str::uuid(),'company_name'=>$p->company_name,'kind'=>'seller_payout','order_id'=>$order->id,
                'seller_payout_id'=>$id,'actor_id'=>$actor->id,'recipient_id'=>$p->seller_id,'amount_cents'=>$amount,'currency'=>'PHP','status'=>'recorded','verification_state'=>'verified',
                'idempotency_key'=>$id,'request_hash'=>hash('sha256',$id.'|'.$reference),'details'=>json_encode(['direction'=>'out','reference'=>$reference,'shipping_cents'=>$p->shipping_cents,'product_cents'=>$p->product_cents,'adjustment_cents'=>$p->adjustment_cents]),
                'recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            DB::table('logistics_seller_payouts')->where('id',$id)->update(['status'=>'paid','reference'=>$reference,'paid_by'=>$actor->id,'paid_at'=>now(),'notes'=>$data['notes'],'revision'=>$p->revision+1,'updated_at'=>now()]);
            DB::table('logistics_shipping_obligations')->where('order_id',$order->id)->where('collection_mode','cod')
                ->update(['status'=>'cod_retained','revision'=>DB::raw('revision + 1'),'updated_at'=>now()]);
            $treasury->event($actor,'seller_payout_paid','seller_payout',$id,['amount_cents'=>$amount,'reference'=>$reference,'notes'=>$data['notes'],'destination_id'=>$p->destination_id]);
        },3);
    }

    public function acknowledge(User $actor,string $id): void
    {
        DB::transaction(function()use($actor,$id){
            $actor=User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->account_type==='seller' && $actor->status==='approved' && !$actor->is_admin && !$actor->is_logistics,403);
            $p=DB::table('logistics_seller_payouts')->where('id',$id)->where('seller_id',$actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($p->status==='paid',422,'Only an actual recorded payout can be acknowledged.');
            if($p->acknowledged_at)return;
            DB::table('logistics_seller_payouts')->where('id',$id)->update(['acknowledged_at'=>now(),'updated_at'=>now()]);
            app(LogisticsFinance::class)->event($p->company_name,'seller_payout_acknowledged','seller_payout',$id,['seller_id'=>$actor->id],$actor->id);
        },3);
    }
}
