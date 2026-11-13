<?php

namespace Tests\Feature;

use App\Services\LogisticsFinance;
use Illuminate\Support\Facades\DB;

class OfficialRiderPolicyTest extends RiderCompensationTest
{
    public function test_official_successful_delivery_pays_ten_from_logistics_only(): void
    {
        $earning = $this->earning();
        $this->assertSame('earned', $earning->status);
        $this->assertSame(1000, (int)$earning->amount_cents);
        $this->assertFalse($this->riderFlow()->recognize($earning->id, $this->admin));
        $this->payroll('10');
        $this->riderFlow()->prepare($this->admin, $this->riderPrepareData());
        $this->riderVerify();
        $data = array_replace($this->riderPayData(), ['amount' => '10']);
        $this->riderFlow()->pay($this->admin, $this->riderPayout()->id, $data, $this->proof('Official rider payment'));
        $this->assertSame(0, $this->treasury->balance($this->account->id, 'rider_payroll'));
        $this->assertSame(0, $this->treasury->balance($this->account->id, 'cod'));
        $this->assertSame(0, $this->treasury->balance($this->account->id, 'commission'));
        $this->assertDatabaseHas('logistics_financial_transactions', ['kind' => 'rider_payout', 'amount_cents' => 1000]);
        $this->assertDatabaseCount('logistics_seller_payouts', 0);
    }

    public function test_official_pending_delivery_does_not_earn_and_rates_cannot_be_overridden(): void
    {
        $earning = $this->earning();
        DB::table('logistics_compensations')->where('id', $earning->id)->update(['status' => 'pending', 'earned_at' => null, 'earned_source_key' => null]);
        DB::table('delivery_assignments')->where('id', $earning->source_id)->update(['status' => 'out_for_delivery', 'delivered_at' => null]);
        $this->assertFalse($this->riderFlow()->recognize($earning->id, $this->admin));
        $settings = app(LogisticsFinance::class)->settings('Alpha');
        $this->invalid(fn() => app(LogisticsFinance::class)->configure($this->admin, [
            'pickup' => '0', 'transfer' => '0', 'delivery' => '90', 'revision' => $settings->revision,
        ]));
    }
}
