<?php

namespace Tests\Feature;

use App\Models\{Shipment, User};
use App\Services\{HubTransferWorkflow, ParcelCustody};
use Illuminate\Support\Facades\DB;

class ParcelCustodyTest extends HubTransferWorkflowTest
{
    public function test_custody_dispatch_is_not_pickup_and_member_receipt_is_not_whole_batch_receipt(): void
    {
        $s=Shipment::firstOrFail();$actor=User::where('logistics_role','admin')->firstOrFail();
        $sender=User::where('logistics_role','hub_staff')->where('logistics_hub_id',$actor->logistics_hub_id)->firstOrFail();
        $receiver=User::where('logistics_role','hub_staff')->where('logistics_hub_id','!=',$actor->logistics_hub_id)->firstOrFail();
        $rider=User::where('account_type','rider')->firstOrFail();
        // Synthetic original handover/receipt evidence; no configured records are touched.
        DB::table('shipments')->update(['picked_up_at'=>now()->subMinutes(10),'seller_confirmed_pickup_at'=>now()->subMinutes(11),'rider_confirmed_pickup_at'=>now()->subMinutes(11),'at_sorting_center_at'=>now()->subMinutes(5)]);
        $view=app(ParcelCustody::class);$flow=app(HubTransferWorkflow::class);
        $this->assertSame('At Hub',$view->describe($s->fresh(),$sender)['label']);
        $before=DB::table('shipments')->where('id',$s->id)->first();$view->describe($s->fresh(),$sender);$this->assertEquals($before,DB::table('shipments')->where('id',$s->id)->first());
        $batch=$flow->request($sender,$sender->logistics_hub_id,$receiver->logistics_hub_id,Shipment::pluck('id')->all());
        $flow->decide($actor,$batch->id,'approved');$flow->dispatch($actor,$batch->id,$rider->id);
        $this->assertSame('Awaiting Rider Pickup',$view->describe($s->fresh(),$sender)['label']);
        $leg=$s->hubLegs()->firstOrFail();$flow->confirmPickup($rider,$leg->id);
        $this->assertSame('Rider Pickup Confirmed',$view->describe($s->fresh(),$receiver)['label']);
        $flow->receive($receiver,$batch->id,[$s->id]);
        $this->assertSame('Awaiting Final Delivery',$view->describe($s->fresh(),$receiver)['label']);
        $this->assertSame('in_transit',$batch->fresh()->status);
        $this->actingAs($receiver)->get('/logistics/assignments')->assertOk()->assertSee('Awaiting Final Delivery');
    }

    public function test_missing_receipt_evidence_does_not_claim_at_hub_and_scanner_exposes_read_only_description(): void
    {
        $s=Shipment::firstOrFail();$staff=User::where('logistics_role','hub_staff')->firstOrFail();
        $this->assertSame('Custody needs review',app(ParcelCustody::class)->describe($s,$staff)['label']);
        $this->actingAs($staff)->postJson('/logistics/scan/lookup',['code'=>$s->tracking_number])->assertOk()->assertJsonPath('custody.label','Custody needs review');
    }
}
