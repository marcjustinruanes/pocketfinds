<?php

namespace Tests\Feature;

use App\Services\CommissionLedger;
use Illuminate\Support\Facades\{DB, Http, Mail};
use Illuminate\Support\Str;

/** Local simulated receipts only. Never run against an application database. */
class CommissionStagingEndToEndTest extends CommissionWorkflowFixTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Http::preventStrayRequests();
        Mail::fake();
    }

    private function scenario(string $name, ...$args): mixed
    {
        return (new \ReflectionMethod(CommissionTest::class, $name))->invoke($this, ...$args);
    }

    private function receivingAccount(object $platform): object
    {
        $this->actingAs($platform)->post('/commissions/accounts', [
            'action' => 'create', 'revision' => 1, 'reason' => 'Synthetic receiving account setup',
            'account_name' => 'SIMULATED PLATFORM - NO MONEY',
            'account_number' => '09111111111',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $account = DB::table('commission_accounts')->first();
        $this->post('/commissions/accounts', ['action'=>'activate','revision'=>2,'account_id'=>$account->id,'reason'=>'Synthetic destination verified'])
            ->assertRedirect()->assertSessionHasNoErrors();
        return $account;
    }

    public function test_staging_cod_payout_remittance_and_verified_receipt_through_http(): void
    {
        $this->scenario('enableFixture');
        $this->fund();
        $this->actingAs($this->admin)->post('/logistics/finance/payouts/prepare', $this->prepareData())
            ->assertRedirect()->assertSessionHasNoErrors();
        $payout = $this->payout();
        $this->post('/logistics/finance/payouts/'.$payout->id.'/action', [
            'revision' => $payout->revision, 'action' => 'verify', 'notes' => 'Synthetic settlement reviewed',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/logistics/finance/payouts/'.$payout->id.'/pay', $this->payData('900') + [
            'proof' => $this->proof('SIMULATED seller net transfer; no money sent'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $obligation = $this->scenario('obligation');
        $ledger = app(CommissionLedger::class);
        $this->assertSame(10000, (int) $obligation->withheld_cents);
        $this->assertSame(0, $ledger->collected($obligation->id));
        $this->assertSame(10000, $this->treasury->balance($this->account->id, 'cod'));
        $this->assertSame(10000, $this->treasury->balance($this->account->id, 'commission'));

        $platform = $this->scenario('platform');
        $account = $this->receivingAccount($platform);
        $this->actingAs($this->admin)->post('/commissions/remittances', [
            'submission_key' => (string) Str::uuid(), 'receiving_account_id' => $account->id,
            'allocations' => [$obligation->id => '100'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $payment = DB::table('commission_payments')->first();
        $this->post('/commissions/remittances/'.$payment->id, [
            'revision' => 1, 'action' => 'send', 'sent' => 1, 'amount' => '100',
            'reference' => 'STAGECOD001', 'reason' => 'SIMULATED transfer only',
            'proof' => $this->proof('SIMULATED remittance; no money sent'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $ledger->collected($obligation->id));
        $this->assertSame(10000, $this->treasury->balance($this->account->id, 'comm_transit'));
        $this->assertDatabaseHas('notifications',['user_id'=>$platform->id,'notification_type'=>'commission_payment','reference_id'=>$payment->id]);
        $this->actingAs($platform)->get(route('commissions.conversation',['payment',$payment->id]))->assertOk()->assertSee('Manual COD remittance submitted');
        $this->post(route('commissions.message',['payment',$payment->id]),['submission_key'=>(string)Str::uuid(),'message'=>'Synthetic Platform reply: awaiting receipt confirmation'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get(route('commissions.conversation',['payment',$payment->id]))->assertOk()->assertSee('awaiting receipt confirmation');
        $this->assertSame(0,$ledger->collected($obligation->id));
        $review = ['revision' => 2, 'decision' => 'verify', 'amount' => '100',
            'reference' => 'STAGECOD001', 'received' => 1, 'reason' => 'SIMULATED receipt verification'];
        $this->actingAs($this->staff)->post('/commissions/payments/'.$payment->id.'/review', $review)->assertForbidden();
        $this->actingAs($platform)->post('/commissions/payments/'.$payment->id.'/review', $review)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10000, $ledger->collected($obligation->id));
        $this->assertSame(0, $ledger->due($this->scenario('obligation')));
        $this->assertSame(0, $this->treasury->balance($this->account->id, 'comm_transit'));
        $this->actingAs($this->seller)->get('/commissions')->assertOk();
        $this->actingAs($this->admin)->get('/commissions')->assertOk();
        $this->actingAs($platform)->get('/commissions')->assertOk();
    }

    public function test_staging_gcash_receipt_credit_partial_refund_and_repayment_through_http(): void
    {
        $order = $this->scenario('gcash');
        $obligation = $this->scenario('obligation', $order);
        $platform = $this->scenario('platform');
        $account = $this->receivingAccount($platform);
        $ledger = app(CommissionLedger::class);
        $this->assertSame(10000, $ledger->due($obligation));
        $this->actingAs($this->seller)->post('/commissions/payments',
            $this->scenario('submitData', $account, $obligation, '120', 'STAGEGCASH001') + [
                'proof' => $this->proof('SIMULATED seller receipt; no money sent'),
            ])->assertRedirect()->assertSessionHasNoErrors();
        $payment = DB::table('commission_payments')->first();
        $this->assertSame(0, $ledger->collected($obligation->id));
        $review = ['revision' => 1, 'decision' => 'verify', 'amount' => '120',
            'reference' => 'STAGEGCASH001', 'received' => 1, 'reason' => 'SIMULATED exact receipt'];
        $this->actingAs($this->admin)->post('/commissions/payments/'.$payment->id.'/review', $review)->assertForbidden();
        $this->actingAs($platform)->post('/commissions/payments/'.$payment->id.'/review', $review)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10000, $ledger->collected($obligation->id));
        $this->assertSame(2000, (int) DB::table('commission_payments')->find($payment->id)->unapplied_cents);

        // Synthetic recorded return: initial buyer return initiation is tested separately.
        $return = (string) Str::uuid();
        DB::table('commerce_returns')->insert(['id' => $return, 'order_id' => $order->id,
            'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'item_keys' => '[0]',
            'reason' => 'Damaged item', 'description' => 'Synthetic returned merchandise',
            'photos' => '[]', 'amount' => 600, 'refund_destination' => 'GCash', 'status' => 'received']);
        $this->post('/commerce/returns/'.$return, ['action' => 'refunded', 'reference' => 'STAGEBUYERREF001'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $credit = DB::table('commission_credits')->first();
        $this->assertSame(6000, (int) $credit->amount_cents);
        $this->assertSame(6000, (int) $this->scenario('obligation', $order)->reversed_cents);
        $this->post('/commissions/credits/'.$credit->id.'/repay', [
            'sent' => 1, 'amount' => '60', 'reference' => 'STAGECREDIT001',
            'reason' => 'SIMULATED repayment only', 'destination_id' => $this->destination->id,
            'proof' => $this->proof('SIMULATED credit repayment; no money sent'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('commission_credits', ['id' => $credit->id, 'status' => 'paid']);
        $this->assertSame(2000, (int) DB::table('commission_payments')->find($payment->id)->unapplied_cents);
        $this->actingAs($this->seller)->get('/commissions')->assertOk()->assertSee('STAGECREDIT001');
    }
}
