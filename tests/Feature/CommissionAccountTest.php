<?php

namespace Tests\Feature;

use App\Services\{CommissionAccounts, CommissionLedger, CommissionPayments, CommissionRecovery};
use Illuminate\Support\Facades\{DB, Http, Mail};
use Illuminate\Support\Str;

class CommissionAccountTest extends CommissionWorkflowFixTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
    }

    private function fixture(string $name, ...$args): mixed
    {
        return (new \ReflectionMethod(CommissionTest::class, $name))->invoke($this, ...$args);
    }

    private function change(object $admin, string $action, array $data = [])
    {
        return $this->actingAs($admin)->post('/commissions/accounts', $data + [
            'action'=>$action, 'revision'=>app(CommissionAccounts::class)->settings()->revision,
            'reason'=>'Synthetic account ownership checked',
        ]);
    }

    private function createAccount(object $admin, string $name = 'Official Test Account', string $number = '09111111111'): object
    {
        $this->change($admin, 'create', ['account_name'=>$name, 'account_number'=>$number])->assertRedirect()->assertSessionHasNoErrors();
        $event = DB::table('commission_account_events')->orderByDesc('id')->first();
        return DB::table('commission_accounts')->find($event->account_id);
    }

    public function test_account_admin_can_add_edit_activate_deactivate_with_encryption_and_audit(): void
    {
        $admin=$this->fixture('platform');
        $account=$this->createAccount($admin);
        $this->assertNull(app(CommissionAccounts::class)->settings()->active_account_id);
        $this->assertSame('***********', $account->account_number);
        $this->assertStringNotContainsString('09111111111', $account->encrypted_details);
        $this->assertSame('09111111111', app(CommissionAccounts::class)->details($account)->account_number);
        $original=(array)$account;
        $this->change($admin,'activate',['account_id'=>$account->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->change($admin,'edit',['account_id'=>$account->id,'account_name'=>'Updated Test Account','account_number'=>'09222222222'])->assertRedirect()->assertSessionHasNoErrors();
        $newId=app(CommissionAccounts::class)->settings()->active_account_id;
        $this->assertNotSame($account->id,$newId);
        $this->assertSame($original,(array)DB::table('commission_accounts')->find($account->id));
        $this->assertSame('09222222222',app(CommissionAccounts::class)->details(DB::table('commission_accounts')->find($newId))->account_number);
        $this->change($admin,'deactivate',['account_id'=>$newId])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(app(CommissionAccounts::class)->settings()->active_account_id);
        $this->assertDatabaseCount('commission_account_events',4);
        $this->assertSame([$admin->id],DB::table('commission_account_events')->distinct()->pluck('actor_id')->all());
        $this->assertFalse((bool)config('commission.enabled'));
        $this->assertNull(config('commission.activation_at'));
        $this->get('/commissions')->assertOk()->assertSee('Admin Commission Settings')->assertSee('Receiving-account audit history');
    }

    public function test_account_only_platform_admin_can_manage_accounts_including_fresh_role_changes(): void
    {
        $admin=$this->fixture('platform');
        foreach([$this->seller,$this->admin,$this->staff,$this->buyer,$this->rider] as $actor) {
            foreach(['create','edit','activate','deactivate'] as $action) $this->change($actor,$action)->assertForbidden();
        }
        DB::table('users')->where('id',$admin->id)->update(['is_admin'=>false]);
        $this->change($admin,'create',['account_name'=>'Unauthorized','account_number'=>'09111111111'])->assertForbidden();
        $this->assertDatabaseCount('commission_accounts',0);
        $this->assertDatabaseCount('commission_account_events',0);
    }

    public function test_account_validation_stale_forms_and_atomic_rollback(): void
    {
        $admin=$this->fixture('platform');
        $this->change($admin,'create',['account_name'=>'','account_number'=>'123'])->assertSessionHasErrors(['account_name','account_number']);
        $account=$this->createAccount($admin);
        $this->change($admin,'activate',['account_id'=>$account->id,'revision'=>1])->assertStatus(409);
        $this->change($admin,'activate',['account_id'=>$account->id,'reason'=>''])->assertSessionHasErrors('reason');
        $before=app(CommissionAccounts::class)->settings();
        DB::beginTransaction();
        app(CommissionAccounts::class)->manage($admin,['action'=>'activate','account_id'=>$account->id,'revision'=>$before->revision,'reason'=>'Synthetic rollback']);
        DB::rollBack();
        $this->assertEquals($before,app(CommissionAccounts::class)->settings());
        $this->assertDatabaseCount('commission_account_events',1);
    }

    public function test_account_seller_sees_only_active_destination_for_new_payment_and_cannot_forge_inactive_id(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$admin=$this->fixture('platform');
        $inactive=$this->createAccount($admin,'Inactive Test Account','09222222222');
        $active=$this->createAccount($admin);
        $this->change($admin,'activate',['account_id'=>$active->id])->assertSessionHasNoErrors();
        $page=$this->actingAs($this->seller)->get('/commissions')->assertOk();
        // Recovery has its own historical selector; new payment instructions must contain only the active destination.
        $html=explode('Payment and remittance history',$page->getContent())[0];
        $this->assertStringContainsString('09111111111',$html);
        $this->assertStringNotContainsString('09222222222',$html);
        $this->assertStringNotContainsString('Admin Commission Settings',$page->getContent());
        $this->post('/commissions/payments',$this->fixture('submitData',$inactive,$o)+['proof'=>$this->proof('Synthetic inactive account')])->assertSessionHasErrors('receiving_account_id');
        $this->post('/commissions/payments',$this->fixture('submitData',$active,$o)+['proof'=>$this->proof('Synthetic active account')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_payments',1);
        $this->assertSame(0,app(CommissionLedger::class)->collected($o->id));
    }

    public function test_account_deactivation_blocks_new_payments_but_preserves_old_receipt_verification_and_retries(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$admin=$this->fixture('platform');
        $account=$this->createAccount($admin);$this->change($admin,'activate',['account_id'=>$account->id]);
        $proof=$this->proof('Synthetic original receipt');$data=$this->fixture('submitData',$account,$o);
        $payments=app(CommissionPayments::class);$id=$payments->submit($this->seller,$data,$proof);
        $original=(array)DB::table('commission_payments')->find($id);
        $this->change($admin,'deactivate',['account_id'=>$account->id])->assertSessionHasNoErrors();
        $this->assertSame($id,$payments->submit($this->seller,$data,$proof));
        $this->assertSame($original,(array)DB::table('commission_payments')->find($id));
        $this->actingAs($this->seller)->get('/commissions')->assertOk()->assertSee('No active GCash receiving account')->assertSee('Do not send money');
        $this->post('/commissions/payments',$this->fixture('submitData',$account,$o,'100','NEWCLAIM001')+['proof'=>$this->proof('Synthetic blocked receipt')])->assertSessionHasErrors('receiving_account_id');
        $this->fixture('verifyReceipt',$admin,$id);
        $this->assertSame(10000,app(CommissionLedger::class)->collected($o->id));
        $this->assertDatabaseCount('commission_payments',1);
        $this->assertSame('09111111111',app(CommissionAccounts::class)->details(DB::table('commission_accounts')->find($original['receiving_account_id']))->account_number);
    }

    public function test_account_already_sent_recovery_remains_available_after_deactivation(): void
    {
        $admin=$this->fixture('platform');$account=$this->createAccount($admin);
        $this->actingAs($this->seller)->post('/commissions/recoveries',[
            'submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'amount'=>'10','reference'=>'OLDTRANSFER001',
            'sent'=>1,'reason'=>'Synthetic payment sent before account disabled','proof'=>$this->proof('Synthetic old transfer'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_recoveries',1);
        $this->assertDatabaseCount('commission_obligations',0);
    }

    public function test_account_edit_keeps_original_receipt_destination_and_only_one_active_account(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$admin=$this->fixture('platform');
        $account=$this->createAccount($admin);$this->change($admin,'activate',['account_id'=>$account->id]);
        $this->actingAs($this->seller)->post('/commissions/payments',$this->fixture('submitData',$account,$o)+['proof'=>$this->proof('Synthetic historical receipt')])->assertSessionHasNoErrors();
        $payment=(array)DB::table('commission_payments')->first();
        $this->change($admin,'edit',['account_id'=>$account->id,'account_name'=>'Replacement Account','account_number'=>'09222222222'])->assertSessionHasNoErrors();
        $this->assertSame($payment,(array)DB::table('commission_payments')->first());
        $this->actingAs($this->seller)->get('/commissions')->assertOk()->assertSee('09111111111')->assertSee('09222222222');
        $other=$this->createAccount($admin,'Third Account','09333333333');
        $this->change($admin,'activate',['account_id'=>$other->id])->assertSessionHasNoErrors();
        $this->assertSame($other->id,app(CommissionAccounts::class)->settings()->active_account_id);
        $this->assertDatabaseCount('commission_account_settings',1);
    }

    public function test_account_legacy_plaintext_revision_is_readable_and_never_rewritten(): void
    {
        $admin=$this->fixture('platform');$id=(string)Str::uuid();
        DB::table('commission_accounts')->insert(['id'=>$id,'account_name'=>'Legacy Destination','account_number'=>'09444444444','created_by'=>$admin->id,'created_at'=>now()]);
        $original=(array)DB::table('commission_accounts')->find($id);
        $this->assertSame('09444444444',app(CommissionAccounts::class)->details((object)$original)->account_number);
        $this->change($admin,'edit',['account_id'=>$id,'account_name'=>'New Encrypted Destination','account_number'=>'09555555555'])->assertSessionHasNoErrors();
        $this->assertSame($original,(array)DB::table('commission_accounts')->find($id));
        $this->assertNull(app(CommissionAccounts::class)->settings()->active_account_id);
        $this->get('/commissions')->assertOk()->assertSee('Legacy Destination')->assertSee('New Encrypted Destination');
    }

    public function test_account_cod_remittance_requires_active_destination_and_preserves_existing_draft(): void
    {
        $this->fixture('paidCod');$o=$this->fixture('obligation');$admin=$this->fixture('platform');
        $account=$this->createAccount($admin);
        $data=['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'allocations'=>[$o->id=>'100']];
        $this->actingAs($this->admin)->post('/commissions/remittances',$data)->assertSessionHasErrors('receiving_account_id');
        $this->assertDatabaseCount('commission_payments',0);
        $this->change($admin,'activate',['account_id'=>$account->id])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/commissions/remittances',$data)->assertSessionHasNoErrors();
        $original=(array)DB::table('commission_payments')->first();
        $this->change($admin,'deactivate',['account_id'=>$account->id])->assertSessionHasNoErrors();
        $this->assertSame($original,(array)DB::table('commission_payments')->first());
        $this->assertSame(10000,$this->treasury->balance($this->account->id,'commission'));
        $this->assertSame(0,app(CommissionLedger::class)->collected($o->id));
    }
}
