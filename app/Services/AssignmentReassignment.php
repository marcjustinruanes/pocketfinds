<?php

namespace App\Services;

use App\Models\{CompanyVehicle, DeliveryAssignment, LogisticsHub, Order, Shipment, User};
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Support\Str;

class AssignmentReassignment
{
    public function installed(): bool { return Schema::hasColumn('delivery_assignments', 'reassignment'); }

    public function assertNoPending(string $shipment, ?string $leg = null): void
    {
        app(LogisticsRecovery::class)->assertNotRecovered($shipment);
        if(\Illuminate\Support\Facades\Schema::hasColumn('logistics_issues','rts')) abort_if(\App\Models\LogisticsIssue::where('rts_shipment_id',$shipment)->where('rts->state','!=','seller_received')->lockForUpdate()->exists(),409,'Return custody is active. Use the RTS workflow.');
        if (! $this->installed()) return;
        abort_if(DeliveryAssignment::where('shipment_id',$shipment)->when($leg,fn($q)=>$q->where('leg',$leg))
            ->whereNotNull('reassignment')->lockForUpdate()->exists(),409,'A possession review is pending. Confirm or cancel it first.');
    }

    public function approvedRider(User $user, Shipment $shipment): User
    {
        $r=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        abort_unless($r->status==='approved' && $r->account_type==='rider' && ! $r->is_admin && ! $r->is_logistics && $r->business_name===$shipment->logistics_company,403);
        return $r;
    }

    public function actor(User $user): User
    {
        $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        abort_unless($user->status === 'approved' && ! $user->is_admin && $user->account_type === 'logistics'
            && ($user->isLogisticsAdmin() || $user->isHubStaff()) && filled($user->business_name), 403);
        if ($user->isHubStaff()) app(LogisticsAccess::class)->hub($user);
        return $user;
    }

    private function source(User $user, string $id, array $extraUsers = []): array
    {
        abort_unless($this->installed(), 503, 'Install the operational recovery migration.');
        $peek = DeliveryAssignment::findOrFail($id);
        $seller = Shipment::findOrFail($peek->shipment_id)->order?->seller_id;
        GcashLocks::operation([$peek->shipment_id], [], array_filter(array_merge([$user->id, $seller], $extraUsers)));
        $a = DeliveryAssignment::whereKey($id)->lockForUpdate()->firstOrFail();
        $s = Shipment::whereKey($a->shipment_id)->lockForUpdate()->firstOrFail();
        return [$a, $s];
    }

    private function scope(User $actor, Shipment $s, string $leg): void
    {
        abort_unless($actor->business_name === $s->logistics_company, 404);
        app(LogisticsAccess::class)->requireLocation($actor, $leg === 'pickup' ? $s->origin_hub : $s->destination_hub, $leg === 'pickup' ? $s->origin_province : $s->destination_province);
    }

    public function assertUncollected(DeliveryAssignment $a, Shipment $s): void
    {
        abort_unless(in_array($a->leg, ['pickup', 'delivery'], true), 422, 'Transfer reassignment is not supported.');
        app(LogisticsRecovery::class)->assertNotRecovered($s->id);
        $owner = $a->leg === 'pickup' ? $s->pickup_rider_id : $s->courier_id;
        abort_unless((int) $owner === (int) $a->courier_id && $a->courier_id, 409, 'Assignment ownership changed.');
        abort_if($a->picked_up_at || $a->delivered_at, 422, 'Recorded physical work cannot be reassigned.');
        if ($a->leg === 'pickup') {
            abort_unless($s->shipping_status === 'ready_for_pickup' && $a->status === 'accepted' && $s->pickup_approved_at, 422, 'Pickup is not awaiting collection.');
            abort_if($s->rider_confirmed_pickup_at || $s->seller_confirmed_pickup_at || $s->picked_up_at || $s->at_sorting_center_at || $s->delivered_at || $s->hubLegs()->exists(), 422, 'Pickup handover or route evidence exists.');
            app(LogisticsShipping::class)->gate($s);
        } else {
            // Earlier seller-to-hub pickup evidence belongs to a different leg and is preserved.
            abort_unless($s->shipping_status === 'assigned_to_rider' && $a->status === 'assigned_to_rider' && $s->sorted_at, 422, 'Delivery is not awaiting hub collection.');
            abort_if($s->out_for_delivery_at || $s->delivered_at || $s->delivery_failed_at, 422, 'Delivery release or attempt evidence exists.');
        }
        $order = $s->order_id ? Order::whereKey($s->order_id)->lockForUpdate()->firstOrFail() : null;
        if ($order) app(GcashWorkflow::class)->assertOrderOperation($order, $a->leg === 'pickup');
        if (Schema::hasTable('logistics_financial_transactions')) abort_if(DB::table('logistics_financial_transactions')->where('collection_shipment_id', $s->id)->lockForUpdate()->exists(), 422, 'Collection evidence exists.');
        abort_if(DB::table('logistics_compensations')->where('source_type', 'assignment')->where('source_id', $a->id)->whereNotIn('status', ['pending', 'cancelled'])->lockForUpdate()->exists(), 422, 'Earned work cannot be reassigned.');
    }

    private function replacement(int $id, Shipment $s, string $leg): User
    {
        $r = User::whereKey($id)->lockForUpdate()->firstOrFail();
        abort_unless($r->account_type === 'rider' && ! $r->is_admin && ! $r->is_logistics && $r->status === 'approved' && $r->business_name === $s->logistics_company
            && $r->vehicle_type === 'two_wheels' && in_array($r->vehicle_ownership, ['own', 'company'], true), 422, 'Choose an approved eligible company rider.');
        $hub = app(LogisticsAccess::class)->hub($r);
        abort_unless(app(LogisticsAccess::class)->matches($hub, $leg === 'pickup' ? $s->origin_hub : $s->destination_hub, $leg === 'pickup' ? $s->origin_province : $s->destination_province), 422, 'Replacement must belong to the parcel hub.');
        abort_if($r->vehicle_ownership === 'company' && ! CompanyVehicle::typeAvailableAtHub($hub->id, 'two_wheels'), 422, 'No approved matching company vehicle is available.');
        return $r;
    }

    public function request(User $user, string $id, array $input): void
    {
        $d = Validator::make($input, ['key'=>'required|uuid','revision'=>'required|integer|min:0','old_rider_id'=>'required|integer','replacement_rider_id'=>'required|integer|different:old_rider_id','reason'=>'required|string|min:3|max:1000'])->validate();
        DB::transaction(function () use ($user, $id, $d) {
            [$a, $s] = $this->source($user, $id, [$d['replacement_rider_id']]); $actor = $this->actor($user); $this->scope($actor, $s, $a->leg);
            $this->assertUncollected($a, $s);
            if ($a->reassignment && $a->reassignment['key'] === $d['key']) {
                abort_unless($a->reassignment['requested_by'] === $actor->id && $a->reassignment['replacement_rider_id'] === (int)$d['replacement_rider_id'] && $a->reassignment['reason'] === $d['reason'], 409); return;
            }
            abort_unless((int)$a->revision === (int)$d['revision'] && (int)$a->courier_id === (int)$d['old_rider_id'] && ! $a->reassignment, 409, 'Assignment or request changed. Refresh.');
            $this->replacement((int)$d['replacement_rider_id'], $s, $a->leg);
            // A key cannot start a second request after an earlier one has completed/cancelled.
            abort_if(DB::table('logistics_financial_events')->where('company_name', $s->logistics_company)->where('event','reassignment_requested')->whereJsonContains('details->key',$d['key'])->lockForUpdate()->exists(), 409, 'Request key already used.');
            $proposal = ['key'=>$d['key'],'requested_by'=>$actor->id,'old_rider_id'=>(int)$a->courier_id,'replacement_rider_id'=>(int)$d['replacement_rider_id'],'reason'=>$d['reason'],'requested_at'=>now()->toIso8601String(),'before'=>$a->only(['id','courier_id','status','accepted_at','picked_up_at','delivered_at','revision'])];
            $a->update(['revision'=>$a->revision+1,'reassignment'=>$proposal]);
            app(LogisticsFinance::class)->event($s->logistics_company, 'reassignment_requested','assignment',$a->id,$proposal,$actor->id);
            if ($a->leg === 'pickup' && $s->order) Order::notifyUser($s->order->seller_id, 'Pickup reassignment review', 'Confirm that your parcel is still with you before its pickup rider can change.', 'order_status', $s->order_id);
            if ($a->leg === 'delivery') $this->notifyReceivingHub($s, 'Delivery reassignment review', 'Confirm actual hub possession and no prior rider release before replacing this assignment.');
        }, 3);
    }

    public function decide(User $user, string $id, array $input): void
    {
        $d = Validator::make($input, ['key'=>'required|uuid','revision'=>'required|integer|min:0','action'=>'required|in:confirm,cancel','confirmed'=>(($input['action']??null)==='confirm'?'required|accepted':'sometimes|accepted'),'reason'=>'required_if:action,cancel|nullable|string|min:3|max:1000'])->validate();
        DB::transaction(function () use ($user, $id, $d) {
            $peek = DeliveryAssignment::findOrFail($id); $p = $peek->reassignment;
            [$a,$s] = $this->source($user,$id, $p ? [$p['replacement_rider_id']] : []);
            $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->status === 'approved',403);
            $seller = $actor->account_type === 'seller' && ! $actor->is_admin && ! $actor->is_logistics && $s->order?->seller_id === $actor->id && $a->leg === 'pickup';
            if (! $seller) { $actor=$this->actor($actor); $this->scope($actor,$s,$a->leg); }
            abort_unless($a->reassignment && $a->reassignment['key'] === $d['key'] && (int)$a->revision === (int)$d['revision'],409,'Reassignment already changed. Refresh.');
            $p=$a->reassignment;
            if ($d['action'] === 'confirm') {
                if ($a->leg === 'pickup') abort_unless($seller,403,'Only this seller can confirm current possession.');
                else app(LogisticsAccess::class)->receivingStaff($actor,$s->destination_hub,$s->destination_province);
                $this->assertUncollected($a,$s);
                abort_unless((int)$a->courier_id === $p['old_rider_id'],409);
                $r=$this->replacement($p['replacement_rider_id'],$s,$a->leg);
                $a->update(['courier_id'=>$r->id,'revision'=>$a->revision+1,'reassignment'=>null]);
                $s->update([$a->leg==='pickup'?'pickup_rider_id':'courier_id'=>$r->id]);
                app(LogisticsFinance::class)->event($s->logistics_company,'assignment_reassigned_confirmed','assignment',$a->id,$p+['attested_by'=>$actor->id,'attested_at'=>now()->toIso8601String(),'after'=>$a->only(['id','courier_id','revision','status'])],$actor->id);
                foreach (array_unique(array_filter([$p['old_rider_id'],$r->id,$s->order?->seller_id])) as $person) Order::notifyUser($person,'Assignment reassigned','An uncollected '.$a->leg.' assignment was reassigned after possession confirmation.','order_status',$s->order_id);
                if ($a->leg === 'delivery') $this->notifyReceivingHub($s, 'Delivery assignment reassigned', 'Hub possession was confirmed; the replacement rider must use the normal collection workflow.');
            } else {
                $a->update(['revision'=>$a->revision+1,'reassignment'=>null]);
                app(LogisticsFinance::class)->event($s->logistics_company,'reassignment_cancelled','assignment',$a->id,$p+['cancel_reason'=>$d['reason']],$actor->id);
            }
        },3);
    }

    private function notifyReceivingHub(Shipment $shipment, string $title, string $message): void
    {
        $hub = LogisticsHub::where('company_name',$shipment->logistics_company)->where('municipality',$shipment->destination_hub)->where('province',$shipment->destination_province)->where('is_operational',true)->first();
        if (! $hub) return;
        foreach (User::where('business_name',$shipment->logistics_company)->where('account_type','logistics')->where('is_admin',false)->where('is_logistics',true)->where('logistics_role','hub_staff')->where('status','approved')->where('logistics_hub_id',$hub->id)->pluck('id') as $id) {
            Order::notifyUser($id,$title,$message,'order_status',$shipment->order_id);
        }
    }
}
