<?php
namespace Tests\Feature;

use App\Services\LogisticsTreasury;
use Illuminate\Support\Facades\{Cache,DB,Mail};
use Illuminate\Support\Str;

class GcashOnlyPaymentsTest extends LogisticsReconciliationTest
{
    public function test_gcash_only_account_creation_and_mobile_validation(): void
    {
        $bank=['channel'=>'bank','institution'=>'Legacy Bank','account_name'=>'Synthetic Account','account_number'=>'123456789'];
        $this->invalid(fn()=>$this->treasury->createAccount($this->admin,$bank));
        $this->invalid(fn()=>$this->treasury->destination($this->seller,$bank));
        $this->invalid(fn()=>$this->riderFlow()->destination($this->rider,$bank));
        $this->invalid(fn()=>$this->treasury->accountData(array_replace($bank,['channel'=>'gcash'])));
        $this->assertSame('GCash',$this->treasury->accountData(array_replace($bank,['channel'=>'gcash','account_number'=>'09123456789']))['institution']);
        $this->actingAs($this->seller)->get('/seller/payouts')->assertOk()->assertDontSee('value="bank"',false)->assertSee('GCash mobile number');
    }

    public function test_gcash_only_legacy_company_bank_cannot_move_new_money(): void
    {
        $this->fund();
        DB::table('logistics_treasury_accounts')->where('id',$this->account->id)->update(['channel'=>'bank','institution'=>'Legacy Bank']);
        $this->invalid(fn()=>$this->treasury->transfer($this->admin,['key'=>(string)Str::uuid(),'hub_id'=>$this->hub->id,'account_id'=>$this->account->id,'amount'=>'1','notes'=>'Must not authorize bank movement']));
        $this->invalid(fn()=>$this->payroll('100','LEGACYFUND001'));
        $this->invalid(fn()=>$this->flow->prepare($this->admin,$this->prepareData()));
        $this->assertSame(110000,$this->treasury->balance($this->account->id,'cod'));
        $this->assertSame(0,DB::table('logistics_seller_payouts')->count());
        $this->assertSame('bank',DB::table('logistics_treasury_accounts')->where('id',$this->account->id)->value('channel'));
    }

    public function test_gcash_only_legacy_seller_instruction_keeps_history_and_allows_cancel_unsent(): void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());$this->verify();
        DB::table('logistics_payout_destinations')->where('id',$this->destination->id)->update(['channel'=>'bank','institution'=>'Legacy Bank']);
        $this->invalid(fn()=>$this->pay());
        $this->flow->action($this->admin,$this->payout()->id,['revision'=>$this->payout()->revision,'action'=>'cancel','notes'=>'Confirmed never sent; replace with GCash destination']);
        $this->assertSame('cancelled',$this->payout()->status);
        $this->assertSame(110000,$this->treasury->available($this->account->id,'cod'));
        $this->assertDatabaseHas('logistics_payout_destinations',['id'=>$this->destination->id,'channel'=>'bank']);
    }

    public function test_gcash_only_legacy_rider_instruction_keeps_earning_and_allows_cancel_unsent(): void
    {
        $this->payroll();$d=$this->riderDestination();$this->riderFlow()->prepare($this->admin,$this->riderPrepareData());
        DB::table('logistics_rider_destinations')->where('id',$d->id)->update(['channel'=>'bank','institution'=>'Legacy Bank']);
        $this->invalid(fn()=>$this->riderVerify());$p=$this->riderPayout();
        $this->riderFlow()->action($this->admin,$p->id,['revision'=>$p->revision,'action'=>'cancel','notes'=>'Confirmed unsent legacy instruction']);
        $this->assertSame(10000,$this->riderFlow()->available($this->account->id));
        $this->assertSame('earned',$this->earning()->status);
        $this->assertDatabaseHas('logistics_rider_destinations',['id'=>$d->id,'channel'=>'bank']);
    }

    public function test_gcash_only_buyer_connections_reject_bank_and_old_verification_codes(): void
    {
        Mail::fake();$this->buyer->update(['email'=>'syntheticbuyer@gmail.com']);$this->actingAs($this->buyer);
        foreach(['bank','paymaya'] as $type)$this->postJson(route('buyer.payment-accounts.send-code'),['type'=>$type,'account_name'=>'Synthetic Buyer','account_number'=>'09123456789'])->assertUnprocessable()->assertJsonValidationErrors('type');
        Cache::put('payment_otp_'.$this->buyer->id,['otp'=>'123456','data'=>['type'=>'bank','account_name'=>'Legacy Buyer','account_number'=>'123456789']],600);
        $this->postJson(route('buyer.payment-accounts.verify'),['otp'=>'123456'])->assertUnprocessable();
        $this->assertDatabaseCount('buyer_payment_accounts',0);
        $this->postJson(route('buyer.payment-accounts.send-code'),['type'=>'gcash','account_name'=>'Synthetic Buyer','account_number'=>'09123456789'])->assertOk()->assertJsonPath('success',true);
        $otp=Cache::get('payment_otp_'.$this->buyer->id)['otp'];
        $this->postJson(route('buyer.payment-accounts.verify'),['otp'=>$otp])->assertOk()->assertJsonPath('account.type','gcash');
    }
}
