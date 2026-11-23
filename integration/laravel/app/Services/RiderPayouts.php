<?php
namespace App\Services;

use App\Models\{Order, User};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Reads existing job credits; admin records actual payouts, never transfers money. */
class RiderPayouts
{
    public function acknowledge(User $rider,string $id): void
    {
        DB::transaction(function()use($rider,$id){
            $rider=User::whereKey($rider->id)->lockForUpdate()->firstOrFail();
            abort_unless($rider->account_type==='rider' && $rider->status==='approved' && !$rider->is_admin && !$rider->is_logistics,403);
            $p=DB::table('rider_payouts')->where('id',$id)->where('rider_id',$rider->id)->lockForUpdate()->firstOrFail();
            if($p->status!=='released') throw ValidationException::withMessages(['payout'=>'Only released payouts can be acknowledged.']);
            if($p->acknowledged_at)return;
            DB::table('rider_payouts')->where('id',$id)->update(['acknowledged_at'=>now()]);
            app(PlatformPayments::class)->audit($p->shipment_id,$rider->id,'payout_acknowledged',['payout_id'=>$id]);
        },3);
    }
    public function summary(int $rider): array
    {
        if (!Schema::hasTable('rider_payouts')) return ['payouts'=>[], 'payout_totals'=>[]];
        $jobs = DB::table('mobile_rider_pay as p')->join('shipments as s','s.id','=','p.shipment_id')
            ->join('orders as o','o.id','=','s.order_id')->where('p.rider_id',$rider)
            ->select('p.*','o.status as order_status','o.id as order_id')->get();
        $payouts = DB::table('rider_payouts')->where('rider_id',$rider)->orderByDesc('created_at')->get();
        $totals = ['total'=>0, 'pending'=>0, 'payable'=>0, 'approved'=>0, 'released'=>0, 'failed'=>0, 'reversed'=>0, 'completed_deliveries'=>0];
        foreach ($jobs as $job) {
            $payout=$payouts->first(fn($p)=>$p->shipment_id===$job->shipment_id && $p->leg===$job->leg);
            $status=$payout?->status ?? ($this->payable($job->order_id) ? 'payable' : 'pending');
            $totals['total']+=(float)$job->amount;
            $totals[$status]+=(float)$job->amount;
            if($job->leg==='delivery') $totals['completed_deliveries']++;
        }
        return ['payout_totals'=>$totals,'payouts'=>$payouts->map(fn($p)=>(array)$p)->all()];
    }

    private function payable(string $order): bool
    {
        $record=Order::whereKey($order)->where('status','completed')->first();
        if (!$record) return false;
        // Reuse the website's actual return policy; never shorten its seven-day
        // protection to the three-day example used by another application.
        if (BuyerCommerce::returnWindowOpen($record)) return false;
        if (Schema::hasTable('commerce_returns') && DB::table('commerce_returns')->where('order_id',$order)->whereNotIn('status',['rejected','closed','cancelled'])->exists()) return false;
        if ($record->gcash_purchase_id && !$record->gcashPurchase?->verified_at) return false;
        if (app(CodCollection::class)->isCod($record)) {
            $shipment=\App\Models\Shipment::where('order_id',$order)->first();
            $collection=$shipment ? app(CodCollection::class)->find($shipment->id) : null;
            if (!$collection || $collection->verification_state!=='verified') return false;
        }
        if (Schema::hasTable('logistics_issues') && DB::table('logistics_issues')->whereIn('shipment_id',DB::table('shipments')->select('id')->where('order_id',$order))->where('status','!=','resolved')->exists()) return false;
        if (Schema::hasTable('mobile_commerce_returns') && DB::table('mobile_commerce_returns')->where('order_id',$order)->whereNotIn('status',['rejected','closed','cancelled'])->exists()) return false;
        return true;
    }

    public function record(User $admin, array $data): array
    {
        abort_unless($admin->is_admin && $admin->status==='approved',403);
        return DB::transaction(function() use($admin,$data) {
            GcashLocks::shipment($data['shipment_id']);
            // Reuse the existing logistics lock order before touching financial rows.
            User::whereKey($data['rider_id'])->lockForUpdate()->firstOrFail();
            $job=DB::table('mobile_rider_pay')->where('rider_id',$data['rider_id'])->where('shipment_id',$data['shipment_id'])->where('leg',$data['leg'])->lockForUpdate()->first();
            abort_unless($job,404);
            $shipment=\App\Models\Shipment::findOrFail($job->shipment_id);
            $order=Order::lockFresh($shipment->order_id);
            if(in_array($data['status'],['approved','released'],true) && !$this->payable($order->id)) throw ValidationException::withMessages(['payout'=>'Order completion, the website return window, payment verification and COD reconciliation must be satisfied before payout.']);
            $row=DB::table('rider_payouts')->where('shipment_id',$job->shipment_id)->where('leg',$job->leg)->lockForUpdate()->first();
            $action=$data['status'];
            $allowed = $row ? match($row->status) {
                'approved'=>['released','failed'], 'failed'=>['approved'], 'released'=>['reversed'], 'reversed'=>['approved'], default=>[],
            } : ['approved'];
            if(!in_array($action,$allowed,true)) throw ValidationException::withMessages(['payout'=>'This payout transition is no longer available. Refresh the record.']);
            if(in_array($action,['released','reversed','failed'],true) && mb_strlen(trim($data['reference']??''))<5)
                throw ValidationException::withMessages(['reference'=>'Record the actual transaction reference or failure report reference.']);
            $values=['status'=>$action];
            if(!$row) $values+=['id'=>(string)Str::uuid(),'shipment_id'=>$job->shipment_id,'leg'=>$job->leg,'rider_id'=>$job->rider_id,'amount'=>$job->amount,'approved_by'=>$admin->id,'created_at'=>now()];
            if($action==='released') $values+=['reference'=>trim($data['reference']),'released_by'=>$admin->id,'released_at'=>now()];
            if($row) DB::table('rider_payouts')->where('id',$row->id)->update($values);
            else DB::table('rider_payouts')->insert($values);
            app(PlatformPayments::class)->audit($job->shipment_id,$admin->id,'payout_'.$action,['amount'=>$job->amount,'rider_id'=>$job->rider_id,'leg'=>$job->leg,'reference'=>$data['reference']??null]);
            Order::notifyUser($job->rider_id,'Payout '.ucfirst($action),'Your '.$job->leg.' earnings record was updated by PocketFinds admin.','rider_earnings',$job->shipment_id);
            return ['recorded'=>true];
        },3);
    }
}
