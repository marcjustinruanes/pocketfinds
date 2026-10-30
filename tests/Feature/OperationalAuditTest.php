<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\DB;

class OperationalAuditTest extends LogisticsReconciliationTest
{
    public function test_operational_context_and_revoked_actor_rollback(): void
    {
        $this->actingAs($this->admin)->patch('/logistics/status/'.$this->shipment->id,['status'=>'completed','reason'=>'Close existing delivered record'])->assertRedirect()->assertSessionHasNoErrors();
        $row=DB::table('logistics_activity_logs')->where('action','logistics.status.update')->latest('id')->first();$context=json_decode($row->changes,true)[$this->shipment->id];
        $this->assertSame(['old'=>'delivered','new'=>'completed'],$context['shipping_status']);$this->assertSame('Close existing delivered record',$context['reason']);$this->assertSame($this->admin->id,(int)$row->actor_id);$this->assertNotNull($row->created_at);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'delivered']);DB::table('orders')->where('id',$this->order->id)->update(['status'=>'delivered']);
        DB::table('users')->where('id',$this->admin->id)->update(['status'=>'suspended']);
        $this->patchJson('/logistics/status/'.$this->shipment->id,['status'=>'completed','reason'=>'Stale actor must not change state'])->assertForbidden();
        $this->assertSame('delivered',$this->shipment->fresh()->shipping_status);
    }
    public function test_operational_export_audit_failure_prevents_download(): void
    {
        DB::unprepared("CREATE TRIGGER export_audit_failure BEFORE INSERT ON logistics_activity_logs BEGIN SELECT RAISE(ABORT, 'export audit failure'); END");$this->withoutExceptionHandling();
        try{$this->actingAs($this->admin)->get('/logistics/reports/export');$this->fail('Unaudited export returned.');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('export audit failure',$e->getMessage());}
    }
}
