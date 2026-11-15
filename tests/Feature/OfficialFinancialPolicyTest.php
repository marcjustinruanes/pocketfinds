<?php

namespace Tests\Feature;

use App\Services\{CommissionLedger, FinancialPolicy};

class OfficialFinancialPolicyTest extends LogisticsShippingTest
{
    public function test_official_parcel_fee_survives_weight_based_carrier_rates(): void
    {
        $order = $this->order([
            'shipping_amount' => '50.00', 'total' => '1050.00',
            'commerce_snapshot' => ['financial_policy' => FinancialPolicy::VERSION, 'vouchers' => []],
        ]);
        $ob = $this->obligation($order);
        $this->assertSame(5000, (int)$ob->amount_cents);
        $this->assertSame('1050.00', number_format($order->fresh()->total, 2, '.', ''));
        $this->assertSame(2, (int)$ob->chargeable_kg);
        $this->assertDatabaseCount('logistics_financial_transactions', 0);
        // A replay cannot duplicate the logistics entitlement.
        $this->assertDatabaseCount('logistics_shipping_obligations', 1);
    }

    public function test_official_product_split_never_includes_shipping_or_rider_pay(): void
    {
        $calc = app(CommissionLedger::class)->calculate([
            'subtotal' => '1000.00', 'shipping_amount' => '50.00', 'discount_amount' => 0,
            'items' => [['name' => 'Parcel', 'qty' => 1, 'price' => '1000.00']],
        ]);
        $this->assertSame(10000, $calc['commission_cents']);
        $this->assertSame(90000, $calc['basis_cents'] - $calc['commission_cents']);
        $this->assertSame(105000, $calc['basis_cents'] + FinancialPolicy::DELIVERY_CENTS);
        $this->assertSame(1000, FinancialPolicy::RIDER_CENTS);
    }
}
