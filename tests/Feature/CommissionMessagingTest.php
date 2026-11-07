<?php

namespace Tests\Feature;

use App\Services\{CommissionConversations,CommissionLedger,CommissionPayments};
use Illuminate\Support\Facades\{DB,Schema,Storage};
use Illuminate\Support\Str;

class CommissionMessagingTest extends CommissionStagingEndToEndTest
{
    private function fixture(string $name,...$args): mixed {return (new \ReflectionMethod(CommissionTest::class,$name))->invoke($this,...$args);}
    private function payment(): array
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$admin=$this->fixture('platform');$account=$this->fixture('receiving',$admin);
        $this->actingAs($this->seller)->post('/commissions/payments',$this->fixture('submitData',$account,$o)+['message'=>'Synthetic partial-payment question','proof'=>$this->proof('Synthetic Seller receipt')])->assertRedirect()->assertSessionHasNoErrors();
        return [$admin,$o,DB::table('commission_payments')->first()];
    }
    private function postMessage(object $actor,string $kind,string $id,string $body,array $extra=[])
    {
        return $this->actingAs($actor)->post(route('commissions.message',[$kind,$id]),$extra+['submission_key'=>(string)Str::uuid(),'message'=>$body]);
    }

    public function test_messaging_gcash_manual_receipt_two_way_notifications_corrections_and_verification(): void
    {
        [$admin,$o,$p]=$this->payment();$ledger=app(CommissionLedger::class);
        $this->assertSame(0,$ledger->collected($o->id));
        $this->assertDatabaseHas('notifications',['user_id'=>$admin->id,'notification_type'=>'commission_payment','reference_id'=>$p->id]);
        $adminNotification=DB::table('notifications')->where('user_id',$admin->id)->where('notification_type','commission_payment')->first();
        $this->actingAs($admin)->get(route('account.notifications.open',$adminNotification->id))->assertRedirect(route('commissions.conversation',['payment',$p->id]));
        $this->actingAs($admin)->get(route('commissions.conversation',['payment',$p->id]))->assertOk()->assertSee('Synthetic partial-payment question')->assertSee('Messages')->assertHeader('Cache-Control','no-store, private');
        $before=(array)DB::table('commission_payments')->find($p->id);
        $this->postMessage($admin,'payment',$p->id,'Please explain the reference',['decision'=>'verify','received'=>1,'amount'=>'100'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before,(array)DB::table('commission_payments')->find($p->id));
        $notification=DB::table('notifications')->where('user_id',$this->seller->id)->where('notification_type','commission_payment')->first();
        $this->actingAs($this->seller)->get(route('seller.notifications.open',$notification->id))->assertRedirect(route('commissions.conversation',['payment',$p->id]));
        $this->assertDatabaseHas('notifications',['id'=>$notification->id,'is_read'=>1]);
        $this->actingAs($admin)->post('/commissions/payments/'.$p->id.'/review',['revision'=>1,'decision'=>'correction','reason'=>'Please provide a clearer screenshot'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('commission_payments',['id'=>$p->id,'status'=>'review','review_decision'=>'correction']);
        $this->postMessage($this->seller,'payment',$p->id,'<script>not executable</script> Corrected evidence attached',['proof'=>$this->proof('Synthetic clearer proof')])->assertRedirect()->assertSessionHasNoErrors();
        $message=DB::table('commission_payment_messages')->whereNotNull('proof_path')->first();
        DB::transaction(fn()=>app(\App\Services\MediaLifecycle::class)->deleteAfterCommit($message->proof_path,'local'));
        Storage::disk('local')->assertExists($message->proof_path);Storage::disk('local')->assertExists($p->proof_path);
        $this->actingAs($admin)->get(route('commissions.message-proof',$message->id))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->get(route('commissions.conversation',['payment',$p->id]))->assertOk()->assertSee('&lt;script&gt;not executable&lt;/script&gt;',false)->assertDontSee('<script>not executable</script>',false)->assertSeeInOrder(['Synthetic partial-payment question','Please explain the reference','Please provide a clearer screenshot','Corrected evidence attached']);
        $this->assertSame($p->proof_path,DB::table('commission_payments')->find($p->id)->proof_path);
        $this->assertSame(0,$ledger->collected($o->id));
        $review=['revision'=>2,'decision'=>'verify','amount'=>'100','reference'=>'COMM001','reason'=>'Actual synthetic receipt checked'];
        $this->post('/commissions/payments/'.$p->id.'/review',$review)->assertSessionHasErrors('received');
        $this->post('/commissions/payments/'.$p->id.'/review',$review+['received'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10000,$ledger->collected($o->id));
        $this->assertDatabaseHas('commission_payment_messages',['payment_id'=>$p->id,'kind'=>'verified']);
    }

    public function test_messaging_cod_rejection_keeps_transit_reserved_and_only_admin_collects(): void
    {
        $this->fixture('paidCod');$o=$this->fixture('obligation');$admin=$this->fixture('platform');$account=$this->fixture('receiving',$admin);
        $this->actingAs($this->admin)->post('/commissions/remittances',['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'allocations'=>[$o->id=>'40']])->assertSessionHasNoErrors();
        $p=DB::table('commission_payments')->first();
        $this->post('/commissions/remittances/'.$p->id,['revision'=>1,'action'=>'send','sent'=>1,'amount'=>'40','reference'=>'MSGREMIT001','reason'=>'Synthetic partial remittance','message'=>'Remaining sixty follows later','proof'=>$this->proof('Synthetic COD transfer')])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notifications',['user_id'=>$admin->id,'notification_type'=>'commission_payment','reference_id'=>$p->id]);
        $this->actingAs($admin)->post('/commissions/payments/'.$p->id.'/review',['revision'=>2,'decision'=>'reject','reason'=>'Screenshot is unclear; transfer must be reconciled'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('commission_payments',['id'=>$p->id,'status'=>'review','review_decision'=>'reject']);
        $this->assertSame(4000,$this->treasury->balance($this->account->id,'comm_transit'));$this->assertSame(6000,$this->treasury->balance($this->account->id,'commission'));
        $this->assertSame(4000,(int)DB::table('commission_allocations')->where('payment_id',$p->id)->sum('requested_cents'));
        $this->assertSame(0,app(CommissionLedger::class)->collected($o->id));
        $this->assertDatabaseHas('notifications',['user_id'=>$this->admin->id,'notification_type'=>'commission_payment','reference_id'=>$p->id]);
        $notice=DB::table('notifications')->where('user_id',$this->admin->id)->where('notification_type','commission_payment')->first();
        $this->actingAs($this->admin)->get(route('account.notifications.open',$notice->id))->assertRedirect(route('commissions.conversation',['payment',$p->id]));
        $this->actingAs($this->admin)->get('/commissions')->assertOk()->assertSee('Remittance proof rejected')->assertSee('Messages');
        $this->postMessage($this->admin,'payment',$p->id,'Here is clearer remittance evidence',['proof'=>$this->proof('Synthetic corrected COD proof')])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/commissions/payments/'.$p->id.'/review',['revision'=>3,'decision'=>'verify','amount'=>'40','reference'=>'MSGREMIT001','received'=>1,'reason'=>'Synthetic receipt actually verified'])->assertSessionHasNoErrors();
        $this->assertSame(4000,app(CommissionLedger::class)->collected($o->id));
        $this->assertSame(6000,app(CommissionLedger::class)->due(DB::table('commission_obligations')->find($o->id)));
        $this->assertSame(0,$this->treasury->balance($this->account->id,'comm_transit'));
        $this->actingAs($this->seller)->get(route('commissions.conversation',['payment',$p->id]))->assertForbidden();
    }

    public function test_messaging_foreign_roles_fresh_status_and_private_proof_are_guarded(): void
    {
        [$admin,$o,$p]=$this->payment();
        $this->postMessage($this->seller,'payment',$p->id,'Private correction',['proof'=>$this->proof('Private synthetic correction')])->assertSessionHasNoErrors();
        $message=DB::table('commission_payment_messages')->whereNotNull('proof_path')->first();
        foreach([$this->buyer,$this->rider,$this->staff,$this->admin,$this->person('seller'),$this->person('admin','Beta')] as $actor){
            $this->actingAs($actor)->get(route('commissions.conversation',['payment',$p->id]))->assertForbidden();
            $this->postMessage($actor,'payment',$p->id,'Unauthorized')->assertForbidden();
            $this->get(route('commissions.message-proof',$message->id))->assertForbidden();
        }
        DB::table('users')->where('id',$this->seller->id)->update(['status'=>'suspended']);
        $this->postMessage($this->seller,'payment',$p->id,'Stale session')->assertForbidden();
        $this->assertSame(0,app(CommissionLedger::class)->collected($o->id));
    }

    public function test_messaging_retries_validation_and_notification_failure_rollback(): void
    {
        [$admin,$o,$p]=$this->payment();$key=(string)Str::uuid();
        $data=['submission_key'=>$key,'message'=>'A single audited reply'];
        $url=route('commissions.message',['payment',$p->id]);
        $this->actingAs($this->seller)->post($url,$data)->assertSessionHasNoErrors();
        $messages=DB::table('commission_payment_messages')->count();$notifications=DB::table('notifications')->count();
        $this->post($url,$data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_payment_messages',$messages);$this->assertDatabaseCount('notifications',$notifications);
        $this->post($url,['submission_key'=>$key,'message'=>'Changed retry'])->assertStatus(409);
        $this->postMessage($this->seller,'payment',$p->id,str_repeat('x',4001))->assertSessionHasErrors('message');
        $this->postMessage($this->seller,'payment',$p->id,'  ')->assertSessionHasErrors('message');
        $this->postMessage($this->seller,'payment',$p->id,'Malformed attachment',['proof'=>[$this->proof('Only one attachment is allowed')]])->assertSessionHasErrors('proof');
        $files=Storage::disk('local')->allFiles();
        DB::unprepared("CREATE TRIGGER reject_commission_notification BEFORE INSERT ON notifications WHEN NEW.notification_type='commission_payment' BEGIN SELECT RAISE(ABORT,'synthetic notification failure'); END");
        $this->postMessage($this->seller,'payment',$p->id,'Must roll back',['proof'=>$this->proof('Synthetic rolled-back attachment')])->assertStatus(409);
        $this->assertDatabaseCount('commission_payment_messages',$messages);$this->assertDatabaseCount('notifications',$notifications);
        $this->assertSame($files,Storage::disk('local')->allFiles());
        $this->assertSame(0,app(CommissionLedger::class)->collected($o->id));
    }

    public function test_messaging_recovery_and_chronological_pagination_preserve_history(): void
    {
        $admin=$this->fixture('platform');$account=$this->fixture('receiving',$admin);
        $this->actingAs($this->seller)->post('/commissions/recoveries',['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'reference'=>'MSGRECOVERY001','amount'=>'10','sent'=>1,'reason'=>'Synthetic already sent payment','proof'=>$this->proof('Recovery proof')])->assertSessionHasNoErrors();
        $r=DB::table('commission_recoveries')->first();
        $this->assertDatabaseHas('notifications',['user_id'=>$admin->id,'notification_type'=>'commission_recovery','reference_id'=>$r->id]);
        $this->postMessage($admin,'recovery',$r->id,'Please clarify the earlier rejected claim')->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get(route('commissions.conversation',['recovery',$r->id]))->assertForbidden();
        $rows=[];foreach(range(0,60) as $i)$rows[]=['recovery_id'=>$r->id,'sender_id'=>$this->seller->id,'sender_label'=>'Synthetic Seller','sender_role'=>'Seller','kind'=>'message','body'=>'History message '.$i,'created_at'=>now()];
        DB::table('commission_payment_messages')->insert($rows);
        $this->actingAs($admin)->get(route('commissions.conversation',['recovery',$r->id]))->assertOk()->assertSeeInOrder(['History message 50','History message 60']);
        $this->get(route('commissions.conversation',['recovery',$r->id,'messages_page'=>1]))->assertOk()->assertSee('Synthetic already sent payment')->assertDontSee('History message 60');
        $this->assertDatabaseHas('commission_recoveries',['id'=>$r->id,'status'=>'pending','unapplied_cents'=>0]);
        $this->assertDatabaseCount('commission_obligations',0);
    }

    public function test_messaging_missing_installation_fails_closed_without_breaking_legacy_payments(): void
    {
        Schema::drop('commission_payment_messages');
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$admin=$this->fixture('platform');$account=$this->fixture('receiving',$admin);
        $this->actingAs($this->seller)->post('/commissions/payments',$this->fixture('submitData',$account,$o)+['message'=>'Cannot silently lose this note','proof'=>$this->proof('Blocked uninstalled message')])->assertStatus(503);
        $this->assertDatabaseCount('commission_payments',0);
        $id=app(CommissionPayments::class)->submit($this->seller,$this->fixture('submitData',$account,$o),$this->proof('Legacy payment without message'));
        $this->postMessage($this->seller,'payment',$id,'Unavailable')->assertStatus(503);
        $this->actingAs($this->seller)->get('/commissions')->assertOk()->assertSee('Payment messaging requires installation');
        $this->assertSame(0,app(CommissionLedger::class)->collected($o->id));
    }

    public function test_messaging_initial_payment_note_retries_notify_once_and_cannot_replace_history(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$admin=$this->fixture('platform');$account=$this->fixture('receiving',$admin);
        $input=$this->fixture('submitData',$account,$o)+['message'=>'Original synthetic payment context'];
        $proof=$this->proof('Synthetic receipt for retry');$service=app(CommissionPayments::class);
        $id=$service->submit($this->seller,$input,$proof);
        $this->assertSame($id,$service->submit($this->seller,$input,$proof));
        $this->assertDatabaseCount('commission_payments',1);$this->assertDatabaseCount('commission_payment_messages',1);
        $this->assertSame(1,DB::table('notifications')->where('user_id',$admin->id)->where('notification_type','commission_payment')->count());
        $this->actingAs($this->seller)->post('/commissions/payments',array_replace($input,['message'=>'Changed retry context','proof'=>$proof]))->assertStatus(409);
        $this->assertDatabaseHas('commission_payment_messages',['payment_id'=>$id,'body'=>'Original synthetic payment context']);
        $this->assertSame(0,app(CommissionLedger::class)->collected($o->id));
    }
}
