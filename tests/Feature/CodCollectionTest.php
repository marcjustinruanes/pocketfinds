<?php

namespace Tests\Feature;

use App\Models\DeliveryAssignment;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CodCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class CodCollectionTest extends TestCase
{
    use WorkflowSchema;

    private User $rider;
    private User $buyer;
    private User $seller;
    private LogisticsHub $hub;
    private Order $order;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        if (! Schema::hasColumn('orders', 'payment_method')) {
            Schema::table('orders', fn (Blueprint $t) => $t->string('payment_method')->nullable());
        }
        (require database_path('migrations/2026_10_28_000001_add_logistics_financial_foundation.php'))->up();
        (require database_path('migrations/2026_10_28_000002_add_logistics_financial_transactions.php'))->up();
        $this->hub = LogisticsHub::create(['company_name' => 'Alpha', 'municipality' => 'Santa Cruz', 'province' => 'Laguna', 'is_operational' => true]);
        $this->rider = $this->user('rider');
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
        [$this->order, $this->shipment] = $this->parcel();
    }

    private function user(string $role, string $company = 'Alpha'): User
    {
        return User::create(['username' => (string) Str::uuid(), 'account_type' => in_array($role, ['staff', 'admin']) ? 'logistics' : $role,
            'business_name' => $company, 'status' => 'approved', 'is_admin' => false,
            'is_logistics' => in_array($role, ['staff', 'admin']), 'logistics_role' => $role === 'admin' ? 'admin' : ($role === 'staff' ? 'hub_staff' : null),
            'logistics_hub_id' => $this->hub->id, 'given_names' => 'Test', 'last_name' => 'User']);
    }

    private function parcel(string $method = 'Cash on Delivery'): array
    {
        $order = Order::create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'status' => 'out_for_delivery',
            'payment_method' => $method, 'total' => '1125.50', 'subtotal' => '1000.00', 'shipping_amount' => '125.50', 'items' => []]);
        $shipment = Shipment::create(['order_id' => $order->id, 'logistics_company' => 'Alpha', 'courier_id' => $this->rider->id,
            'destination_hub' => 'Santa Cruz', 'destination_province' => 'Laguna', 'shipping_status' => 'out_for_delivery']);
        $assignment = DeliveryAssignment::create(['shipment_id' => $shipment->id, 'courier_id' => $this->rider->id, 'leg' => 'delivery', 'status' => 'assigned_to_rider']);
        DB::table('delivery_assignments')->where('id', $assignment->id)->update(['status' => 'out_for_delivery']);

        return [$order, $shipment];
    }

    private function data(): array
    {
        return ['from' => 'out_for_delivery', 'cod_amount' => '1125.50', 'cod_confirmed' => '1', 'collection_key' => (string) Str::uuid()];
    }

    public function test_delivery_and_exact_collection_commit_together_without_verifying_remittance(): void
    {
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$this->shipment->id.'/advance', $this->data())->assertRedirect();
        $this->assertSame('delivered', $this->shipment->fresh()->shipping_status);
        $this->assertSame('delivered', $this->order->fresh()->status);
        $this->assertDatabaseHas('logistics_financial_transactions', ['collection_shipment_id' => $this->shipment->id,
            'collection_order_id' => $this->order->id, 'actor_id' => $this->rider->id, 'hub_id' => $this->hub->id,
            'amount_cents' => 112550, 'kind' => 'cod_collection', 'status' => 'recorded', 'verification_state' => 'pending']);
        $this->assertDatabaseHas('logistics_financial_events', ['event' => 'cod_collected', 'actor_id' => $this->rider->id]);
        $this->assertDatabaseHas('logistics_compensations', ['rider_id' => $this->rider->id, 'status' => 'pending']);
    }

    public function test_missing_partial_excess_or_unconfirmed_collection_cannot_deliver(): void
    {
        foreach ([[], ['cod_amount' => '1000.00'], ['cod_amount' => '1126'], ['cod_amount' => '1e3'], ['cod_confirmed' => '0']] as $bad) {
            $data = $bad === [] ? ['from' => 'out_for_delivery'] : array_merge($this->data(), $bad);
            $this->actingAs($this->rider)->patchJson('/rider/deliveries/'.$this->shipment->id.'/advance', $data)->assertUnprocessable();
            $this->assertSame('out_for_delivery', $this->shipment->fresh()->shipping_status);
            $this->assertSame('out_for_delivery', $this->order->fresh()->status);
            $this->assertDatabaseCount('logistics_financial_transactions', 0);
        }
    }

    public function test_retries_cannot_duplicate_collection_or_its_audit_event(): void
    {
        $data = $this->data();
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$this->shipment->id.'/advance', $data)->assertRedirect();
        $this->patch('/rider/deliveries/'.$this->shipment->id.'/advance', $data)->assertRedirect();
        app(CodCollection::class)->record($this->rider, $this->shipment, $data);
        $this->assertDatabaseCount('logistics_financial_transactions', 1);
        $this->assertSame(1, DB::table('logistics_financial_events')->where('event', 'cod_collected')->count());
    }

    public function test_confirmation_key_cannot_be_reused_on_another_order(): void
    {
        $data = $this->data();
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$this->shipment->id.'/advance', $data)->assertRedirect();
        [$order, $shipment] = $this->parcel();
        $this->patchJson('/rider/deliveries/'.$shipment->id.'/advance', $data)->assertStatus(409);
        $this->assertSame('out_for_delivery', $shipment->fresh()->shipping_status);
        $this->assertSame('out_for_delivery', $order->fresh()->status);
        $this->assertDatabaseCount('logistics_financial_transactions', 1);
    }

    public function test_other_rider_and_logistics_staff_cannot_bypass_collection(): void
    {
        $this->actingAs($this->user('rider'))->patchJson('/rider/deliveries/'.$this->shipment->id.'/advance', $this->data())->assertNotFound();
        foreach (['staff', 'admin'] as $role) {
            $this->actingAs($this->user($role))->patchJson('/logistics/status/'.$this->shipment->id, ['status' => 'delivered'])->assertForbidden();
        }
        $this->assertDatabaseCount('logistics_financial_transactions', 0);
    }

    public function test_model_updates_cannot_skip_the_collection_requirement(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->shipment->update(['shipping_status' => 'delivered']);
    }

    public function test_rider_page_shows_collection_fields_and_separate_pending_verification(): void
    {
        $this->actingAs($this->rider)->get('/rider/deliveries/'.$this->shipment->id)->assertOk()
            ->assertSee('Amount collected (PHP)')->assertSee('name="cod_confirmed"', false);
        $this->patch('/rider/deliveries/'.$this->shipment->id.'/advance', $this->data())->assertRedirect();
        $this->get('/rider/deliveries/'.$this->shipment->id)->assertOk()->assertSee('COD collection recorded:')->assertSee('Hub cash verification: Pending.');
    }

    public function test_remittance_hub_comes_from_the_assignment_snapshot(): void
    {
        $other = LogisticsHub::create(['company_name' => 'Alpha', 'municipality' => 'Pila', 'province' => 'Laguna', 'is_operational' => true]);
        $this->rider->update(['logistics_hub_id' => $other->id]);
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$this->shipment->id.'/advance', $this->data())->assertRedirect();
        $this->assertDatabaseHas('logistics_financial_transactions', ['collection_shipment_id' => $this->shipment->id, 'hub_id' => $this->hub->id]);
    }

    public function test_client_cannot_forge_hub_verification_or_collection_ownership(): void
    {
        $data = $this->data() + ['verification_state' => 'verified', 'actor_id' => $this->buyer->id, 'hub_id' => 999999, 'company_name' => 'Beta', 'amount_cents' => 1];
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$this->shipment->id.'/advance', $data)->assertRedirect();
        $this->assertDatabaseHas('logistics_financial_transactions', ['actor_id' => $this->rider->id, 'hub_id' => $this->hub->id,
            'company_name' => 'Alpha', 'verification_state' => 'pending', 'amount_cents' => 112550]);
    }

    public function test_finance_collection_list_is_scoped_to_the_admin_company(): void
    {
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$this->shipment->id.'/advance', $this->data())->assertRedirect();
        $this->actingAs($this->user('admin'))->get('/logistics/finance')->assertOk()->assertViewHas('collections', fn ($rows) => $rows->total() === 1);
        $this->actingAs($this->user('admin', 'Beta'))->get('/logistics/finance')->assertOk()->assertViewHas('collections', fn ($rows) => $rows->total() === 0);
    }

    public function test_second_shipment_cannot_collect_the_same_order_again(): void
    {
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$this->shipment->id.'/advance', $this->data())->assertRedirect();
        $other = Shipment::create(['order_id' => $this->order->id, 'logistics_company' => 'Alpha', 'courier_id' => $this->rider->id,
            'destination_hub' => 'Santa Cruz', 'destination_province' => 'Laguna', 'shipping_status' => 'out_for_delivery']);
        DeliveryAssignment::create(['shipment_id' => $other->id, 'courier_id' => $this->rider->id, 'leg' => 'delivery', 'status' => 'out_for_delivery']);
        $this->patchJson('/rider/deliveries/'.$other->id.'/advance', $this->data())->assertStatus(409);
        $this->assertDatabaseCount('logistics_financial_transactions', 1);
        $this->assertSame('out_for_delivery', $other->fresh()->shipping_status);
    }

    public function test_historical_delivered_order_can_be_completed_without_inventing_collection(): void
    {
        // Represents the pre-migration delivered data; application delivery transitions cannot do this.
        DB::table('orders')->where('id', $this->order->id)->update(['status' => 'delivered']);
        DB::table('shipments')->where('id', $this->shipment->id)->update(['shipping_status' => 'delivered']);
        $this->order->fresh()->update(['status' => 'completed']);
        $this->assertDatabaseCount('logistics_financial_transactions', 0);
        $this->assertSame('completed', $this->order->fresh()->status);
    }

    public function test_prepaid_delivery_does_not_create_cod_cash_or_change_its_flow(): void
    {
        [$order, $shipment] = $this->parcel('GCash');
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$shipment->id.'/advance', ['from' => 'out_for_delivery'])->assertRedirect();
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertDatabaseCount('logistics_financial_transactions', 0);
    }

    public function test_audit_failure_rolls_back_cash_and_delivery_together(): void
    {
        DB::unprepared("CREATE TRIGGER fail_cash_audit BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'cod_collected' BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->rider)->patch('/rider/deliveries/'.$this->shipment->id.'/advance', $this->data());
            $this->fail('Audit failure must not commit the delivery.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('audit unavailable', $e->getMessage());
        }
        $this->assertSame('out_for_delivery', $this->shipment->fresh()->shipping_status);
        $this->assertSame('out_for_delivery', $this->order->fresh()->status);
        $this->assertDatabaseCount('logistics_financial_transactions', 0);
    }
}
