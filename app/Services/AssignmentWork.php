<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

/** Operational availability only. Never recognizes, reprices or pays compensation. */
class AssignmentWork
{
    public function classify(DeliveryAssignment $assignment): string
    {
        if (in_array($assignment->status, ['cancelled', 'rejected'], true)) return 'completed'; // Closed, not earned work.
        $s = Shipment::whereKey($assignment->shipment_id)->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->first();
        if (! $s) return 'ambiguous';
        if (\Illuminate\Support\Facades\Schema::hasColumn('logistics_issues','recovery') && DB::table('logistics_issues')->whereJsonContains('recovery->shipment_ids',$s->id)->where('recovery->rider_id',(int)$assignment->courier_id)->where('recovery->leg',$assignment->leg)->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->exists()) return 'ambiguous';
        if ($assignment->leg==='delivery' && $s->shipping_status==='returned' && (int)$s->courier_id===(int)$assignment->courier_id && \Illuminate\Support\Facades\Schema::hasColumn('logistics_issues','rts') && DB::table('logistics_issues')->where('rts_shipment_id',$s->id)->where('rts->state','seller_received')->where('rts->rider_id',(int)$assignment->courier_id)->exists()) return 'completed'; // Closed unsuccessful work; never earnings.
        if ($assignment->leg === 'pickup' && (int) $assignment->courier_id === (int) $s->pickup_rider_id
            && $s->seller_confirmed_pickup_at && $s->rider_confirmed_pickup_at && $s->picked_up_at && $s->at_sorting_center_at
            && $s->picked_up_at->gte($s->seller_confirmed_pickup_at) && $s->picked_up_at->gte($s->rider_confirmed_pickup_at) && $s->at_sorting_center_at->gte($s->picked_up_at)
            && in_array($assignment->status, ['accepted', 'picked_up', 'completed'], true)) return 'completed';
        if ($assignment->leg === 'delivery' && (int) $assignment->courier_id === (int) $s->courier_id
            && in_array($assignment->status, ['delivered', 'completed'], true) && $assignment->delivered_at && $s->delivered_at && $assignment->delivered_at->equalTo($s->delivered_at)) return 'completed';
        if ($assignment->leg === 'hub_transfer' && $assignment->status === 'completed') {
            $legs = DB::table('shipment_hub_legs')->where('shipment_id', $s->id)->where('rider_id', $assignment->courier_id)
                ->where('status', 'completed')->whereNotNull('completed_at')->whereNotNull('rider_confirmed_pickup_at')
                ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get();
            foreach ($legs as $leg) {
                if (! ($leg->transfer_batch_id ?? null)) continue;
                $b = DB::table('hub_transfer_batches')->where('id', $leg->transfer_batch_id)->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->first();
                $members = DB::table('hub_transfer_members')->where('batch_id', $leg->transfer_batch_id)->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get();
                if ($b && (int)$b->rider_id === (int)$assignment->courier_id && $b->status === 'completed' && $b->received_at && $b->received_by && $members->isNotEmpty() && ! $members->contains(fn ($m) => ! $m->received_at)) {
                    $finished=DB::table('shipment_hub_legs')->whereIn('id',$members->pluck('leg_id'))->where('transfer_batch_id',$b->id)->where('rider_id',$assignment->courier_id)->where('status','completed')->whereNotNull('completed_at')->whereNotNull('rider_confirmed_pickup_at')->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->get();
                    if($finished->count()===$members->count()) return 'completed';
                }
                if ($b && $b->status === 'in_transit') return 'active';
            }
            return 'ambiguous';
        }
        if (in_array($assignment->status, ['completed', 'delivered'], true) || in_array($s->shipping_status, ['delivered', 'completed', 'returned', 'cancelled'], true)) return 'ambiguous';
        return in_array($assignment->status, ['requested', 'accepted', 'assigned_to_rider', 'picked_up', 'out_for_delivery', 'hub_transfer', 'delivery_failed'], true) ? 'active' : 'ambiguous';
    }

    public function blocksRider(int $rider): bool
    {
        foreach (DeliveryAssignment::where('courier_id', $rider)->whereNotIn('status', ['cancelled', 'rejected'])->lockForUpdate()->get() as $assignment) {
            if ($this->classify($assignment) !== 'completed') return true;
        }
        return false;
    }

    public function closeReceivedPickup(Shipment $shipment): void
    {
        $a = DeliveryAssignment::where('shipment_id', $shipment->id)->where('leg', 'pickup')->where('courier_id', $shipment->pickup_rider_id)->lockForUpdate()->first();
        if (! $a || ! in_array($a->status, ['accepted', 'picked_up'], true) || $this->classify($a) !== 'completed') return;
        $before = $a->status;
        $a->update(['status' => 'completed']);
        if (app(LogisticsFinance::class)->installed()) app(LogisticsFinance::class)->event($shipment->logistics_company, 'pickup_assignment_completed', 'assignment', $a->id, ['before' => $before, 'after' => 'completed', 'shipment_id' => $shipment->id]);
    }
}
