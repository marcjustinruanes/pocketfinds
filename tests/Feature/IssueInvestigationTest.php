<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\{DB,Storage};

class IssueInvestigationTest extends LogisticsReconciliationTest
{
    public function test_issue_private_evidence_revision_and_confirmed_safe_notifications(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['tracking_number'=>'ISSUE-PRIVATE']);
        $start=DB::table('notifications')->count();
        $this->actingAs($this->admin)->post('/logistics/issues',['tracking_number'=>'ISSUE-PRIVATE','kind'=>'lost','description'=>'Internal allegation must stay private','hub_id'=>$this->hub->id,'proof'=>$this->proof('Unique investigation proof')])->assertRedirect()->assertSessionHasNoErrors();
        $issue=DB::table('logistics_issues')->first();$e=DB::table('logistics_finance_evidence')->where('source_type','parcel_issue')->first();$this->assertNotNull($e);Storage::disk('local')->assertExists($e->path);
        $this->get(route('finance.evidence',$e->id))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->actingAs($this->rider)->get(route('finance.evidence',$e->id))->assertForbidden();
        $this->actingAs($this->staff)->get(route('finance.evidence',$e->id))->assertOk();
        $input=['status'=>'resolved','revision'=>0,'outcome'=>'escalated','resolution'=>'Internal allegation reviewed; referred to support','notify_participants'=>1,'public_update_confirmed'=>1];
        $this->patchJson('/logistics/issues/'.$issue->id,$input)->assertForbidden();
        $this->actingAs($this->admin)->patch('/logistics/issues/'.$issue->id,$input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($start+2,DB::table('notifications')->count());
        $this->assertSame(0,DB::table('notifications')->where('message','like','%allegation%')->count());
        $this->patchJson('/logistics/issues/'.$issue->id,$input)->assertStatus(409);
        $this->assertSame($start+2,DB::table('notifications')->count());
        $this->assertSame('delivered',$this->shipment->fresh()->shipping_status);
        $this->assertSame(110000,(int)DB::table('logistics_financial_transactions')->where('id',$this->collection->id)->value('amount_cents'));
    }
    public function test_issue_invalid_proof_or_audit_failure_cannot_leave_files_or_records(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['tracking_number'=>'ISSUE-ROLLBACK']);
        $this->actingAs($this->admin)->postJson('/logistics/issues',['tracking_number'=>'ISSUE-ROLLBACK','kind'=>'damaged','description'=>'Fixture','hub_id'=>$this->hub->id,'proof'=>\Illuminate\Http\UploadedFile::fake()->create('script.html',1,'text/html')])->assertUnprocessable();
        $this->assertSame(0,DB::table('logistics_issues')->count());
        DB::unprepared("CREATE TRIGGER issue_audit_failure BEFORE INSERT ON logistics_activity_logs BEGIN SELECT RAISE(ABORT, 'issue audit failure'); END");
        $this->withoutExceptionHandling();
        try{$this->post('/logistics/issues',['tracking_number'=>'ISSUE-ROLLBACK','kind'=>'damaged','description'=>'Fixture','hub_id'=>$this->hub->id,'proof'=>$this->proof('Rollback investigation proof')]);$this->fail('Expected audit failure');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('issue audit failure',$e->getMessage());}
        $this->assertSame(0,DB::table('logistics_issues')->count());$this->assertSame(0,DB::table('logistics_finance_evidence')->count());$this->assertSame([],Storage::disk('local')->allFiles('logistics-finance-proofs'));
    }
}
