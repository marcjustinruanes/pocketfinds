<?php

namespace Tests\Feature;

use App\Models\{CompanyVehicle, LogisticsHub};
use App\Services\RiderDeliveryOffers;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

class LogisticsAdminDefectCorrectionsTest extends OperationalRecoveryTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_11_11_000001_add_rider_barangay_routes.php'))->up();
        $this->seller->update(['barangay'=>'Central']);
        $order = $this->shipment->order;
        DB::table('orders')->where('id',$order->id)->update(['shipping_address'=>json_encode(array_merge($order->shipping_address, ['barangay'=>'Central']))]);
        foreach ([$this->rider, $this->replacement] as $rider) {
            DB::table('rider_barangay_routes')->insert(['rider_id'=>$rider->id, 'hub_id'=>$this->hub->id,
                'barangay_code'=>'043426001', 'barangay_name'=>'Central', 'barangay_key'=>'central', 'created_at'=>now(), 'updated_at'=>now()]);
        }
    }

    public static function ineligibleReplacements(): array
    {
        $cases = [];
        foreach (['pickup','delivery'] as $leg) {
            foreach (['request','confirm'] as $stage) {
                foreach (['offline','suspended','company','hub','coverage','vehicle','ownership','unavailable_vehicle','capacity'] as $reason) {
                    $cases["$leg-$stage-$reason"] = [$leg, $stage, $reason];
                }
            }
        }
        return $cases;
    }

    private function jobs(int $count): void
    {
        foreach (array_slice(['picked_up','assigned_to_rider','ready_for_pickup'], 0, $count) as $state) {
            DB::table('shipments')->insert(['id'=>(string)Str::uuid(), 'order_id'=>$this->shipment->order_id, 'tracking_number'=>'CAPACITY-'.Str::uuid(),
                'logistics_company'=>'Alpha', 'shipping_status'=>$state,
                'pickup_rider_id'=>$state==='assigned_to_rider'?null:$this->replacement->id,
                'courier_id'=>$state==='assigned_to_rider'?$this->replacement->id:null]);
        }
    }

    #[DataProvider('ineligibleReplacements')]
    public function test_reassignment_rechecks_shared_eligibility_and_rolls_back(string $leg, string $stage, string $reason): void
    {
        if ($leg === 'delivery') $this->delivery();
        if ($stage === 'confirm') $this->requestChange();
        $before = $this->assignment->fresh()->getRawOriginal();
        // Simulate eligibility changes in disposable fixtures, including stale sessions.
        match ($reason) {
            'offline' => $this->replacement->forceFill(['is_available'=>false])->save(),
            'suspended' => DB::table('users')->where('id',$this->replacement->id)->update(['status'=>'suspended']),
            'company' => DB::table('users')->where('id',$this->replacement->id)->update(['business_name'=>'Other']),
            'hub' => DB::table('users')->where('id',$this->replacement->id)->update(['logistics_hub_id'=>null]),
            'coverage' => DB::table('rider_barangay_routes')->where('rider_id',$this->replacement->id)->delete(),
            'vehicle' => $this->replacement->update(['vehicle_type'=>'four_wheels']),
            'ownership' => $this->replacement->update(['vehicle_ownership'=>'invalid']),
            'unavailable_vehicle' => $this->replacement->update(['vehicle_ownership'=>'company']),
            'capacity' => $this->jobs(3),
        };
        if ($stage === 'request') {
            $this->actingAs($this->admin)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertUnprocessable();
        } else {
            $this->actingAs($leg==='pickup'?$this->seller:$this->staff)
                ->postJson(route($leg==='pickup'?'seller.assignments.decide':'logistics.assignments.decide',$this->assignment->id),$this->confirmData())->assertUnprocessable();
        }
        $after=$this->assignment->fresh();
        $this->assertSame($before['courier_id'],$after->getRawOriginal('courier_id'));
        $this->assertSame($before['revision'],$after->getRawOriginal('revision'));
        $this->assertSame($before['reassignment'],$after->getRawOriginal('reassignment'));
        $this->assertSame($this->rider->id,$this->shipment->fresh()->{$leg==='pickup'?'pickup_rider_id':'courier_id'});
        $this->assertSame(0,DB::table('logistics_financial_events')->where('event','assignment_reassigned_confirmed')->count());
    }

    public function test_eligible_reassignment_fills_only_third_slot_and_duplicate_confirmation_cannot_repeat(): void
    {
        $this->delivery();
        $this->jobs(2);
        $this->requestChange();
        $data=$this->confirmData();
        $this->actingAs($this->staff)->postJson(route('logistics.assignments.decide',$this->assignment->id),$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3,app(RiderDeliveryOffers::class)->activeJobs($this->replacement->id));
        $this->assertSame($this->replacement->id,$this->shipment->fresh()->courier_id);
        $this->postJson(route('logistics.assignments.decide',$this->assignment->id),$data)->assertConflict();
        $this->assertSame(1,DB::table('logistics_financial_events')->where('event','assignment_reassigned_confirmed')->count());
    }

    public function test_company_vehicle_eligibility_is_shared_and_rechecked_on_confirmation(): void
    {
        $this->replacement->update(['vehicle_ownership'=>'company']);
        $vehicle=CompanyVehicle::create(['company_name'=>'Alpha','logistics_hub_id'=>$this->hub->id,'vehicle_type'=>'two_wheels',
            'status'=>'approved','platform_status'=>'approved','is_available'=>true,'plate_number'=>'QA-SHARED']);
        $this->requestChange();
        $vehicle->update(['is_available'=>false]);
        $this->actingAs($this->seller)->postJson(route('seller.assignments.decide',$this->assignment->id),$this->confirmData())->assertUnprocessable();
        $this->assertSame($this->rider->id,$this->shipment->fresh()->pickup_rider_id);
    }

    public function test_reassignment_dropdown_uses_leg_specific_coverage_and_capacity(): void
    {
        $url=route('logistics.assignments.review');
        $this->actingAs($this->admin)->get($url)->assertOk()->assertViewHas('riders',
            fn ($riders) => $riders[$this->assignment->id]->contains('id',$this->replacement->id));
        $order=$this->shipment->order;
        DB::table('orders')->where('id',$order->id)->update(['shipping_address'=>json_encode(array_merge($order->shipping_address,['barangay'=>'Buyer Route']))]);
        $this->delivery();
        $this->get($url)->assertOk()->assertViewHas('riders',
            fn ($riders) => ! $riders[$this->assignment->id]->contains('id',$this->replacement->id));
        DB::table('rider_barangay_routes')->where('rider_id',$this->replacement->id)->update(['barangay_key'=>'buyer route','barangay_name'=>'Buyer Route']);
        $this->get($url)->assertOk()->assertViewHas('riders',
            fn ($riders) => $riders[$this->assignment->id]->contains('id',$this->replacement->id));
        $this->jobs(3);
        $this->get($url)->assertOk()->assertViewHas('riders',
            fn ($riders) => ! $riders[$this->assignment->id]->contains('id',$this->replacement->id));
    }

    public function test_origin_staff_cannot_select_another_hubs_delivery_replacement(): void
    {
        $this->delivery();
        $other=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true]);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['destination_hub'=>'Pila']);
        DB::table('users')->where('id',$this->replacement->id)->update(['logistics_hub_id'=>$other->id]);
        DB::table('rider_barangay_routes')->where('rider_id',$this->replacement->id)->update(['hub_id'=>$other->id]);
        $url=route('logistics.assignments.review');
        $this->actingAs($this->admin)->get($url)->assertOk()->assertViewHas('riders',
            fn ($riders) => $riders[$this->assignment->id]->contains('id',$this->replacement->id));
        $this->actingAs($this->staff)->get($url)->assertOk()->assertViewHas('riders',
            fn ($riders) => $riders[$this->assignment->id]->isEmpty());
        $this->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertForbidden();
        $this->assertNull($this->assignment->fresh()->reassignment);
    }

    public function test_private_delivery_proof_rechecks_current_approval_role_and_company(): void
    {
        Storage::disk('sensitive_documents')->put('delivery_proofs/qa.png','private proof');
        $this->shipment->update(['delivery_proof_file'=>'delivery_proofs/qa.png']);
        $url=route('delivery-proofs.show',$this->shipment->id);
        $this->actingAs($this->admin)->get($url)->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        foreach (['pending','rejected','suspended'] as $status) {
            DB::table('users')->where('id',$this->admin->id)->update(['status'=>$status]);
            $this->actingAs($this->admin)->get($url)->assertNotFound();
        }
        DB::table('users')->where('id',$this->admin->id)->update(['status'=>'approved','logistics_role'=>'invalid']);
        $this->actingAs($this->admin)->get($url)->assertNotFound();
        DB::table('users')->where('id',$this->admin->id)->update(['logistics_role'=>'admin','business_name'=>'Other']);
        $this->actingAs($this->admin)->get($url)->assertNotFound();
        $this->actingAs($this->staff)->get($url)->assertOk();
        $this->actingAs($this->seller)->get($url)->assertOk();
        $this->actingAs($this->person('buyer'))->get($url)->assertNotFound();
        auth()->logout();
        $this->get($url)->assertRedirect('/login');
    }
}

