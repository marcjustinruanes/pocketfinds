<?php

namespace Tests\Feature;

use App\Models\{LogisticsIssue, Shipment, User};
use App\Services\{HubTransferWorkflow, RiderDeliveryOffers};
use Illuminate\Support\Facades\DB;

class ReverseLogisticsTransferHoldTest extends HubTransferWorkflowTest
{
    public function test_reverse_transfer_condition_hold_blocks_dispatch_but_allows_authorized_actual_receipt(): void
    {
        $parcels=Shipment::all();$admin=User::where('logistics_role','admin')->firstOrFail();
        $sender=User::where('logistics_role','hub_staff')->where('logistics_hub_id',$admin->logistics_hub_id)->firstOrFail();
        $receiver=User::where('logistics_role','hub_staff')->where('logistics_hub_id','!=',$admin->logistics_hub_id)->firstOrFail();
        $rider=User::where('account_type','rider')->firstOrFail();$flow=app(HubTransferWorkflow::class);
        foreach($parcels as $parcel)$this->transferOriginReceipt($parcel,$sender);
        $batch=$flow->request($sender,$sender->logistics_hub_id,$receiver->logistics_hub_id,$parcels->pluck('id')->all());
        $flow->decide($admin,$batch->id,'approved');$flow->dispatch($admin,$batch->id,$rider->id);
        foreach($parcels as $parcel)$flow->confirmPickup($rider,$parcel->hubLegs()->firstOrFail()->id);
        $parcel=$parcels->first();
        LogisticsIssue::create(['shipment_id'=>$parcel->id,'company_name'=>'Alpha','hub_id'=>$receiver->logistics_hub_id,'reported_by'=>$receiver->id,'kind'=>'damaged','description'=>'Damage observed during actual hub receipt','status'=>'open']);
        $flow->receive($receiver,$batch->id,[$parcel->id]);
        $this->assertSame('sorted',$parcel->fresh()->shipping_status);
        $this->assertNotNull($batch->members()->where('shipment_id',$parcel->id)->firstOrFail()->received_at);
        $this->assertSame('in_transit',$batch->fresh()->status);
        try{app(RiderDeliveryOffers::class)->offer($parcel->fresh());$this->fail('Damaged parcel was offered');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(422,$e->getStatusCode());}
        $this->assertSame(0,DB::table('delivery_assignments')->where('shipment_id',$parcel->id)->where('leg','delivery')->count());
    }
}
