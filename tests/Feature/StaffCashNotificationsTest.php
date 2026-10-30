<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffCashNotificationsTest extends CodSellerPayoutTest
{
    public function test_rider_to_staff_http_receipt_identifies_people_and_records_shortage_once(): void
    {
        DB::table('users')->where('id',$this->rider->id)->update(['given_names'=>'Maria','last_name'=>'Santos']);
        DB::table('users')->where('id',$this->staff->id)->update(['given_names'=>'Alex','last_name'=>'Cruz']);
        $this->actingAs($this->rider->fresh())->from('/rider/remittances')->post('/rider/remittances',['key'=>(string)Str::uuid(),'allocations'=>[$this->collection->id=>'1100']])->assertRedirect('/rider/remittances');
        $h=DB::table('logistics_cash_handovers')->where('kind','remittance')->first();
        $this->actingAs($this->staff->fresh())->get('/logistics/cash-desk?handover='.$h->id)->assertOk()->assertSee('Maria Santos')->assertSee('Alex Cruz')->assertSee('Receive & Count Cash')->assertSee('1,100.00');
        $input=['count_handover'=>$h->id,'counted'=>'1000','allocations'=>[$this->collection->id=>'1000'],'notes'=>'Counted PHP 100 short in person'];
        $this->from('/logistics/cash-desk?handover='.$h->id)->post('/logistics/cash-desk/'.$h->id.'/count',$input)->assertRedirect()->assertSessionHas('success');
        $this->post('/logistics/cash-desk/'.$h->id.'/count',$input)->assertRedirect();
        $row=DB::table('logistics_cash_handovers')->where('id',$h->id)->first();
        $this->assertSame($this->staff->id,(int)$row->counted_by);$this->assertNotNull($row->counted_at);
        $this->assertSame(100000,(int)$row->counted_cents);$this->assertSame('discrepancy',$row->status);
        $this->assertSame(1,DB::table('logistics_cash_movements')->where('handover_id',$h->id)->count());
        $this->get('/logistics/cash-desk?handover='.$h->id)->assertOk()->assertSee('Received by')->assertSee('Maria Santos')->assertSee('Alex Cruz')->assertSee('Shortage')->assertSee('100.00')->assertDontSee('data-remittance-rider=',false);
    }

    public function test_staff_cash_milestones_notify_only_relevant_users_once_and_open_authorized_pages(): void
    {
        $otherHub=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Elsewhere','province'=>'Laguna','is_operational'=>true]);
        $otherStaff=$this->person('hub_staff','Alpha',$otherHub->id);
        $foreign=$this->person('hub_staff','Beta');
        $suspended=$this->person('hub_staff');DB::table('users')->where('id',$suspended->id)->update(['status'=>'suspended']);
        $input=['key'=>(string)Str::uuid(),'allocations'=>[$this->collection->id=>'1100']];
        $h=$this->desk->submit($this->rider,$input);$this->desk->submit($this->rider,$input);
        $this->assertSame(1,DB::table('notifications')->where('notification_type','cash_handover')->count());
        $n=DB::table('notifications')->where('reference_id',$h->id)->first();
        $this->assertSame($this->staff->id,(int)$n->user_id);
        $this->actingAs($this->staff)->get(route('account.notifications.open',$n->id))->assertRedirect(route('logistics.cash-desk',['handover'=>$h->id]));
        $count=['counted'=>'1100','allocations'=>[$this->collection->id=>'1100'],'notes'=>'Actual count'];
        $this->desk->count($this->staff,$h->id,$count);$this->desk->count($this->staff,$h->id,$count);
        $this->assertSame(3,DB::table('notifications')->where('notification_type','cash_handover')->count());
        $treasury=['key'=>(string)Str::uuid(),'hub_id'=>$this->hub->id,'account_id'=>$this->account->id,'amount'=>'1100','notes'=>'Treasury release'];
        $this->treasury->transfer($this->admin,$treasury);$this->treasury->transfer($this->admin,$treasury);
        $t=DB::table('logistics_cash_handovers')->where('kind','treasury')->first();
        $this->treasury->release($this->staff,$t->id,'1100');$this->treasury->release($this->staff,$t->id,'1100');
        $this->treasury->receipt($this->admin,$t->id,$this->receiptData(),$this->proof('Receipt'));
        $this->assertSame(6,DB::table('notifications')->where('notification_type','cash_handover')->count());
        $this->assertSame(0,DB::table('notifications')->where('notification_type','cash_handover')->whereIn('user_id',[$otherStaff->id,$foreign->id,$suspended->id,$this->seller->id,$this->buyer->id])->count());
        $adminAlert=DB::table('notifications')->where('user_id',$this->admin->id)->where('reference_id',$t->id)->first();
        $this->actingAs($this->admin)->get(route('account.notifications.open',$adminAlert->id))->assertRedirect(route('logistics.finance.payouts').'#treasury');
        $this->actingAs($otherStaff)->get(route('account.notifications.open',$n->id))->assertNotFound();
    }

    public function test_staff_cash_notification_failure_rolls_back_count_custody_and_event(): void
    {
        $h=$this->desk->submit($this->rider,['key'=>(string)Str::uuid(),'allocations'=>[$this->collection->id=>'1100']]);
        $events=DB::table('logistics_financial_events')->count();
        DB::unprepared("CREATE TRIGGER reject_cash_notification BEFORE INSERT ON notifications BEGIN SELECT RAISE(ABORT, 'notification unavailable'); END");
        try{$this->desk->count($this->staff,$h->id,['counted'=>'1100','allocations'=>[$this->collection->id=>'1100'],'notes'=>'Actual count']);$this->fail('Expected rollback');}
        catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('notification unavailable',$e->getMessage());}
        $this->assertSame('submitted',DB::table('logistics_cash_handovers')->where('id',$h->id)->value('status'));
        $this->assertSame(0,$this->desk->custody($this->hub->id));
        $this->assertSame($events,DB::table('logistics_financial_events')->count());
    }

    public function test_staff_cash_queue_and_old_notification_recheck_current_hub_scope(): void
    {
        $h=$this->desk->submit($this->rider,['key'=>(string)Str::uuid(),'allocations'=>[$this->collection->id=>'1100']]);
        $n=DB::table('notifications')->where('user_id',$this->staff->id)->first();
        $this->actingAs($this->staff)->get('/logistics/cash-desk?queue=counts')->assertOk()->assertSee($h->id)->assertSee('Awaiting Hub Verification (1)');
        $this->get('/logistics/cash-desk?queue=treasury')->assertOk()->assertDontSee($h->id);
        $other=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Other','province'=>'Laguna','is_operational'=>true]);
        DB::table('users')->where('id',$this->staff->id)->update(['logistics_hub_id'=>$other->id]);
        $this->actingAs($this->staff->fresh())->get(route('account.notifications.open',$n->id))->assertNotFound();
        $this->assertFalse((bool)DB::table('notifications')->where('id',$n->id)->value('is_read'));
    }
}
