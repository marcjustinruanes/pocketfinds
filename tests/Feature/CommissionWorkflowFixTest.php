<?php
namespace Tests\Feature;

use App\Services\{CommissionLedger,CommissionPayments,CommissionRecovery,CodRefundSettlement,LogisticsReconciliation};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommissionWorkflowFixTest extends CommissionTest
{
    protected function setUp(): void { parent::setUp();(require database_path('migrations/2026_10_29_000004_add_rider_compensation_payouts.php'))->up(); }
    private function fixture(string $name,...$args): mixed { return (new \ReflectionMethod(CommissionTest::class,$name))->invoke($this,...$args); }
    private function recoveryData(object $account,string $ref='RECOVERY001',string $amount='100'): array
    {
        return ['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'reference'=>$ref,'amount'=>$amount,'reason'=>'Actual Seller transfer needs ownership review','sent'=>1];
    }
    private function reviewData(string $ref='RECOVERY001',string $amount='100',int $revision=1): array
    {
        return ['revision'=>$revision,'decision'=>'verify','amount'=>$amount,'reference'=>$ref,'reason'=>'Bank receipt and rightful owner checked against original evidence','received'=>1,'ownership_checked'=>1];
    }
    public function test_fix_rejected_reference_is_recovered_by_rightful_seller_without_changing_old_claim(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);
        $this->actingAs($this->seller)->post('/commissions/payments',$this->fixture('submitData',$account,$o)+['proof'=>$this->proof('wrong reference owner')])->assertRedirect()->assertSessionHasNoErrors();
        $p=DB::table('commission_payments')->first();
        $this->actingAs($platform)->post('/commissions/payments/'.$p->id.'/review',['revision'=>1,'decision'=>'reject','reason'=>'This reference belongs to another Seller'])->assertRedirect()->assertSessionHasNoErrors();
        $original=(array)DB::table('commission_payments')->find($p->id);$rightful=$this->person('seller');
        $data=$this->recoveryData($account,'COMM001');
        $this->actingAs($rightful)->post('/commissions/recoveries',$data+['proof'=>$this->proof('rightful owner bank transfer')])->assertRedirect()->assertSessionHasNoErrors();
        $r=DB::table('commission_recoveries')->first();$this->assertSame(0,(int)$r->unapplied_cents);
        $this->actingAs($platform)->post('/commissions/recoveries/'.$r->id.'/review',$this->reviewData('COMM001'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($original,(array)DB::table('commission_payments')->find($p->id));
        $this->assertDatabaseHas('commission_recoveries',['id'=>$r->id,'seller_id'=>$rightful->id,'previous_payment_id'=>$p->id,'status'=>'verified','unapplied_cents'=>10000]);
        $this->assertDatabaseCount('commission_verified_receipts',1);
        $this->invalid(fn()=>$this->fixture('verifyReceipt',$platform,$p->id));
        $second=app(CommissionRecovery::class)->submit($rightful,$this->recoveryData($account,'COMM001'),$this->proof('duplicate actual transfer claim'));
        $this->invalid(fn()=>app(CommissionRecovery::class)->review($platform,$second,$this->reviewData('COMM001')));
        $this->assertDatabaseCount('commission_verified_receipts',1);$this->assertSame(0,app(CommissionLedger::class)->collected($o->id));
        $this->actingAs($rightful)->get('/commissions')->assertOk()->assertSee('100.00');
    }
    public function test_fix_zero_balance_recovery_can_be_submitted_verified_and_repaid_through_http(): void
    {
        $platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);
        $this->actingAs($this->seller)->get('/commissions?balance=due&page=9')->assertOk()->assertSee('Already sent a payment?')->assertSee('action="'.route('commissions.recoverySubmit').'"',false);
        $data=$this->recoveryData($account);
        $this->post('/commissions/recoveries',$data+['proof'=>$this->proof('zero balance actual payment')])->assertRedirect()->assertSessionHasNoErrors();
        $id=DB::table('commission_recoveries')->value('id');
        foreach([$this->seller,$this->admin,$this->staff,$this->buyer,$this->rider] as $u)$this->actingAs($u)->post('/commissions/recoveries/'.$id.'/review',$this->reviewData())->assertForbidden();
        $this->actingAs($platform)->post('/commissions/recoveries/'.$id.'/review',array_diff_key($this->reviewData(),['ownership_checked'=>1]))->assertRedirect()->assertSessionHasErrors('ownership_checked');
        $this->post('/commissions/recoveries/'.$id.'/review',$this->reviewData())->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/commissions/recoveries/'.$id.'/refund',['amount'=>'100','reference'=>'RECREFUND001','destination_id'=>$this->destination->id,'sent'=>1,'reason'=>'Actual verified excess returned to Seller','proof'=>$this->proof('actual recovery repayment')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0,(int)DB::table('commission_recoveries')->find($id)->unapplied_cents);$this->assertDatabaseCount('commission_obligations',0);$this->assertDatabaseCount('commission_recovery_refunds',1);
        $this->post('/commissions/recoveries/'.$id.'/refund',['amount'=>'100','reference'=>'RECREFUND002','destination_id'=>$this->destination->id,'sent'=>1,'reason'=>'Duplicate excess repayment','proof'=>$this->proof('duplicate recovery repayment')])->assertRedirect()->assertSessionHasErrors();
        $other=$this->person('seller');$this->actingAs($other)->get('/commissions/evidence/recovery/'.$id)->assertForbidden();$this->actingAs($this->admin)->get('/commissions/evidence/recovery/'.$id)->assertForbidden();
        $this->actingAs($this->seller)->get('/commissions')->assertOk()->assertSee('RECREFUND001');
    }
    public function test_fix_recovery_allocations_and_later_refunds_preserve_correct_commission_credit(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);$service=app(CommissionRecovery::class);
        $id=$service->submit($this->seller,$this->recoveryData($account),$this->proof('recovery for allocation'));$service->review($platform,$id,$this->reviewData());
        $this->actingAs($platform)->post('/commissions/recoveries/'.$id.'/allocate',['obligation_id'=>$o->id,'amount'=>'100'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10000,app(CommissionLedger::class)->collected($o->id));$this->assertSame(0,app(CommissionLedger::class)->due($o));
        $this->post('/commissions/recoveries/'.$id.'/allocate',['obligation_id'=>$o->id,'amount'=>'1'])->assertRedirect()->assertSessionHasErrors();
        DB::transaction(fn()=>app(CommissionLedger::class)->reverse($order->id,'test:recovery-refund',[0],'Actual partial refund'));
        $this->assertDatabaseHas('commission_credits',['obligation_id'=>$o->id,'amount_cents'=>6000,'payer'=>'platform']);
        $this->get('/commissions')->assertOk();
    }
    private function refundedCod(string $payer='company'): array
    {
        DB::table('orders')->where('id',$this->order->id)->update(['items'=>json_encode([['qty'=>1,'price'=>600,'name'=>'Returned item'],['qty'=>1,'price'=>400,'name'=>'Retained item']])]);$this->order->refresh();$this->fixture('enableFixture');$this->fund();
        $id=(string)Str::uuid();DB::table('commerce_returns')->insert(['id'=>$id,'order_id'=>$this->order->id,'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'item_keys'=>'[0]','reason'=>'Damaged item','description'=>'Actual partial buyer refund','photos'=>'[]','amount'=>600,'refund_destination'=>'GCash','status'=>'received','created_at'=>now()]);
        $platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);
        $this->actingAs($platform)->post('/commerce/returns/'.$id,['action'=>'refunded','reference'=>'BUYERREFUND001'])->assertRedirect()->assertSessionHasNoErrors();
        $data=['return_id'=>$id,'account_id'=>$this->account->id,'payer'=>$payer,'amount'=>'600','reference'=>'BUYERREFUND001','buyer_name'=>'Buyer Test','buyer_number'=>'09123456780','platform_account_id'=>$account->id,'confirmed'=>1,'reason'=>'Actual buyer refund evidence and funding verified'];
        return [$platform,$id,$data];
    }
    private function residualPayout(string $amount): void
    {
        $this->actingAs($this->admin)->post('/logistics/finance/payouts/prepare',$this->prepareData())->assertRedirect()->assertSessionHasNoErrors();$p=$this->payout();
        $this->post('/logistics/finance/payouts/'.$p->id.'/action',['revision'=>$p->revision,'action'=>'verify','notes'=>'Residual funds and revised commission verified'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/logistics/finance/payouts/'.$p->id.'/pay',$this->payData($amount)+['proof'=>$this->proof('actual residual Seller payout')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(4000,(int)$this->fixture('obligation')->withheld_cents);$this->assertSame(10000,$this->treasury->balance($this->account->id,'cod'));
        $this->assertDatabaseCount('cod_residual_settlements',1);$this->assertSame(0,app(CommissionLedger::class)->collected($this->fixture('obligation')->id));
        $report=app(LogisticsReconciliation::class)->snapshot($this->admin,now()->subDays(30)->toDateString(),now()->toDateString());
        $this->assertSame(0,$report['conservation']['residual_cents']);$this->assertSame(0,$report['metrics']['seller_product_unpaid']);
        $this->assertNotContains('seller_payout_basis',array_column($report['issues'],'code'));
        $this->actingAs($this->seller)->get('/seller/payouts')->assertOk()->assertSee('Residual settlement')->assertSee(number_format((float)$amount,2));
    }
    public function test_fix_company_refunded_cod_has_exact_residual_payout_and_no_duplicate_refund(): void
    {
        [$platform,$return,$data]=$this->refundedCod();$this->assertFalse($this->flow->eligibility($this->order,'Alpha')['eligible']);
        $this->actingAs($this->admin)->get('/commissions')->assertOk()->assertSee('Reconcile actual buyer refund');
        $this->post('/commissions/cod-refunds/'.$this->order->id.'/reconcile',$data+['proof'=>$this->proof('company actual buyer refund')])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/commissions/cod-refunds/'.$this->order->id.'/reconcile',$data+['proof'=>$this->proof('duplicate buyer refund')])->assertRedirect()->assertSessionHasErrors();
        $this->assertSame(50000,$this->treasury->balance($this->account->id,'cod'));$this->residualPayout('360');
        $this->actingAs($this->admin)->post('/logistics/finance/payouts/prepare',$this->prepareData())->assertStatus(409);
    }
    public function test_fix_seller_funded_refund_reimburses_seller_without_a_second_buyer_refund(): void
    {
        [$platform,$return,$data]=$this->refundedCod('seller');
        $this->actingAs($this->admin)->post('/commissions/cod-refunds/'.$this->order->id.'/reconcile',$data+['proof'=>$this->proof('Seller already refunded Buyer')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(110000,$this->treasury->balance($this->account->id,'cod'));$this->residualPayout('960');
        $this->assertSame(100000,(int)$this->payout()->product_cents);$this->assertSame(0,DB::table('logistics_treasury_movements')->where('source_type','cod_buyer_refund')->count());
    }
    public function test_fix_platform_funded_refund_requires_actual_reimbursement_and_platform_verification(): void
    {
        [$platform,$return,$data]=$this->refundedCod('platform');$url='/commissions/cod-refunds/'.$this->order->id.'/reconcile';
        $other=$this->person('admin','Other');$this->actingAs($other)->post($url,$data+['proof'=>$this->proof('foreign company attempt')])->assertForbidden();
        $this->actingAs($this->admin)->post($url,$data+['proof'=>$this->proof('Platform actually refunded Buyer')])->assertRedirect()->assertSessionHasNoErrors();
        $r=DB::table('cod_refund_reconciliations')->first();$this->assertFalse($this->flow->eligibility($this->order,'Alpha')['eligible']);
        $this->assertSame(50000,$this->treasury->available($this->account->id,'cod'));
        $send=['revision'=>1,'amount'=>'600','reference'=>'REFREIMBURSE001','sent'=>1,'reason'=>'Actual refund reimbursement to frozen Platform destination','proof'=>$this->proof('actual Platform reimbursement')];
        $this->post('/commissions/cod-refunds/'.$r->id.'/reimburse',$send)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(60000,$this->treasury->balance($this->account->id,'refund_transit'));$this->assertFalse($this->flow->eligibility($this->order,'Alpha')['eligible']);
        $verify=['revision'=>2,'amount'=>'600','reference'=>'REFREIMBURSE001','received'=>1,'reason'=>'Actual frozen Platform account receipt verified'];
        $this->post('/commissions/cod-refunds/'.$r->id.'/verify',$verify)->assertForbidden();
        $this->actingAs($platform)->get('/commissions')->assertOk()->assertSee('Verify reimbursement');$this->post('/commissions/cod-refunds/'.$r->id.'/verify',$verify)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0,$this->treasury->balance($this->account->id,'refund_transit'));$this->assertDatabaseCount('commission_verified_receipts',0);$this->residualPayout('360');
    }
    public function test_fix_history_date_status_filters_are_scoped_and_validated_before_export(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);$p=app(CommissionPayments::class);
        $id=$p->submit($this->seller,$this->fixture('submitData',$account,$o),$this->proof('history filter payment'));$this->fixture('verifyReceipt',$platform,$id);
        DB::transaction(fn()=>app(CommissionLedger::class)->reverse($order->id,'filter:refund',[0],'Recorded product refund'));
        $this->actingAs($this->seller)->get('/commissions/export/payments?status=verified&from='.now()->toDateString().'&to='.now()->toDateString())->assertOk()->assertSee('COMM001');
        $this->get('/commissions/export/payments?status=rejected')->assertOk()->assertDontSee('COMM001');
        $this->get('/commissions/export/payments?from=2000-01-01&to=2000-01-02')->assertOk()->assertDontSee('COMM001');
        $this->get('/commissions/export/credits?status=pending')->assertOk()->assertSee('60.00');$this->get('/commissions/export/credits?status=paid')->assertOk()->assertDontSee('60.00');
        $this->get('/commissions/export/credits?from=2000-01-01&to=2000-01-02')->assertOk()->assertDontSee('60.00');
        $this->getJson('/commissions/export/payments?from=2026-11-07&to=2026-11-01')->assertUnprocessable();$this->getJson('/commissions/export/credits?status=verified')->assertUnprocessable();
        $this->actingAs($this->person('seller'))->get('/commissions/export/payments?status=verified')->assertOk()->assertDontSee('COMM001');
        $this->actingAs($this->seller)->get('/commissions?payment_status=rejected&credit_status=paid')->assertOk()->assertSee('No commission payments submitted.')->assertSee('Export filtered CSV');
    }
    public function test_fix_large_histories_can_be_narrowed_below_export_cap(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);
        $payments=[];$credits=[];
        for($i=0;$i<10001;$i++){
            $recent=$i===10000;$date=$recent?now()->toDateTimeString():'2000-01-01 00:00:00';
            $payments[]=['id'=>(string)Str::uuid(),'submission_key'=>(string)Str::uuid(),'request_hash'=>hash('sha256',(string)$i),'receiving_account_id'=>$account->id,'submitted_by'=>$this->seller->id,'seller_id'=>$this->seller->id,'method'=>'gcash','amount_cents'=>100,'reference'=>'CAP'.$i,'status'=>$recent?'pending':'rejected','created_at'=>$date,'updated_at'=>$date];
            $credits[]=['id'=>(string)Str::uuid(),'obligation_id'=>$o->id,'source_key'=>'cap:'.$i,'payer'=>'platform','amount_cents'=>100,'status'=>$recent?'pending':'paid','reason'=>'Synthetic export capacity fixture','created_at'=>$date,'updated_at'=>$date];
            if(count($payments)===250 || $recent){DB::table('commission_payments')->insert($payments);DB::table('commission_credits')->insert($credits);$payments=[];$credits=[];}
        }
        $this->actingAs($this->seller)->getJson('/commissions/export/payments')->assertUnprocessable();$this->getJson('/commissions/export/credits')->assertUnprocessable();
        foreach(['payments','credits'] as $kind){$response=$this->get('/commissions/export/'.$kind.'?from='.now()->toDateString().'&to='.now()->toDateString().'&status=pending')->assertOk();$this->assertCount(2,array_filter(explode("\n",trim($response->getContent()))));}
    }
    public function test_fix_refund_reconciliation_rejects_changed_destination_and_existing_payout(): void
    {
        [$platform,$return,$data]=$this->refundedCod();DB::table('commerce_returns')->where('id',$return)->update(['refund_destination'=>'GCash: Buyer Test / 09123456780']);
        $this->actingAs($this->admin)->post('/commissions/cod-refunds/'.$this->order->id.'/reconcile',array_replace($data,['buyer_number'=>'09999999999','proof'=>$this->proof('wrong buyer destination')]))->assertRedirect()->assertSessionHasErrors();
        $this->assertSame(110000,$this->treasury->balance($this->account->id,'cod'));
        $this->post('/commissions/cod-refunds/'.$this->order->id.'/reconcile',$data+['proof'=>$this->proof('correct buyer destination')])->assertRedirect()->assertSessionHasNoErrors();
        DB::table('commerce_returns')->where('id',$return)->update(['amount'=>500]);$this->assertFalse($this->flow->eligibility($this->order,'Alpha')['eligible']);
        $this->assertDatabaseCount('logistics_seller_payouts',0);
    }
    public function test_fix_recovery_never_reuses_a_verified_or_unrelated_financial_reference(): void
    {
        $platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);$service=app(CommissionRecovery::class);
        app(\App\Services\LogisticsShipping::class)->reserveReference('UNRELATED001','shipping_payment',(string)Str::uuid());
        $id=$service->submit($this->seller,$this->recoveryData($account,'UNRELATED001'),$this->proof('unrelated transfer claim'));
        $this->invalid(fn()=>$service->review($platform,$id,$this->reviewData('UNRELATED001')));
        $this->assertDatabaseCount('commission_verified_receipts',0);
        $id=$service->submit($this->seller,$this->recoveryData($account),$this->proof('one verified proof'));$service->review($platform,$id,$this->reviewData());
        $other=$service->submit($this->seller,$this->recoveryData($account,'RECOVERY002'),$this->proof('one verified proof'));
        $this->actingAs($platform)->post('/commissions/recoveries/'.$other.'/review',$this->reviewData('RECOVERY002'))->assertStatus(409);
        $this->assertDatabaseCount('commission_verified_receipts',1);
    }
    public function test_fix_role_pages_render_recovery_and_actual_refund_forms(): void
    {
        [$platform,$return,$data]=$this->refundedCod();$account=DB::table('commission_accounts')->first();
        app(CommissionRecovery::class)->submit($this->seller,$this->recoveryData($account),$this->proof('Browser pending recovery'));
        foreach(['seller'=>$this->seller,'admin'=>$platform,'logistics'=>$this->admin] as $role=>$actor){
            $response=$this->actingAs($actor)->get('/commissions')->assertOk();
            if($role==='seller')$response->assertSee('Already sent a payment?')->assertDontSee('Review receipt ownership');
            if($role==='admin')$response->assertSee('Review receipt ownership');
            if($role==='logistics')$response->assertSee('Reconcile actual buyer refund')->assertDontSee('Browser pending recovery');
            if(getenv('COMMISSION_UI_PREVIEW')==='1'){$dir=storage_path('app/private/commission-ui');if(!is_dir($dir))mkdir($dir,0700,true);file_put_contents($dir.'/'.$role.'.html',$response->getContent());}
        }
    }
}
