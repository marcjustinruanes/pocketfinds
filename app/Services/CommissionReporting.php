<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\{DB, Schema};

class CommissionReporting
{
    public function query(User $actor)
    {
        $q = app(CommissionPayments::class)->scoped(DB::table('commission_obligations'), $actor);
        $collected = '(SELECT COALESCE(SUM(a.allocated_cents),0) FROM commission_allocations a WHERE a.obligation_id=commission_obligations.id)';
        if(Schema::hasTable('commission_recovery_allocations'))$collected='('.$collected.' + (SELECT COALESCE(SUM(a.amount_cents),0) FROM commission_recovery_allocations a WHERE a.obligation_id=commission_obligations.id))';
        $reserved = "(SELECT COALESCE(SUM(a.requested_cents),0) FROM commission_allocations a JOIN commission_payments p ON p.id=a.payment_id WHERE a.obligation_id=commission_obligations.id AND p.status IN ('draft','pending','review'))";
        $net = '(commission_cents-reversed_cents)';
        $hold = "EXISTS (SELECT 1 FROM orders h WHERE h.id=commission_obligations.order_id AND h.status NOT IN ('delivered','completed'))";
        if (Schema::hasTable('complaints')) $hold .= " OR EXISTS (SELECT 1 FROM complaints h WHERE h.order_id=commission_obligations.order_id AND (h.status IS NULL OR h.status<>'resolved'))";
        if (Schema::hasTable('commerce_returns')) {
            $cutoff = DB::getPdo()->quote(now()->subDays(3)->toDateTimeString());
            $hold .= " OR EXISTS (SELECT 1 FROM commerce_returns h WHERE h.order_id=commission_obligations.order_id AND h.status<>'refunded' AND (h.status<>'rejected' OR h.decided_at IS NULL OR h.decided_at>$cutoff))";
        }
        $entitlement = "CASE WHEN earned_at IS NULL OR cancelled_at IS NOT NULL OR ($hold) THEN 0 WHEN method='gcash' THEN $net WHEN withheld_cents<$net THEN withheld_cents ELSE $net END";
        $due = "CASE WHEN $entitlement>$collected THEN $entitlement-$collected ELSE 0 END";
        return $q->select('commission_obligations.*')->selectRaw("$collected AS collected_cents, $reserved AS reserved_cents, $due AS due_cents, CASE WHEN ($hold) THEN 1 ELSE 0 END AS held, CASE WHEN earned_at IS NOT NULL THEN $net ELSE 0 END AS earned_cents");
    }

    public function totals($q): array
    {
        $r = DB::query()->fromSub($q, 'c')->selectRaw("COALESCE(SUM(CASE WHEN earned_at IS NULL AND cancelled_at IS NULL THEN commission_cents-reversed_cents ELSE 0 END),0) AS provisional, COALESCE(SUM(earned_cents),0) AS earned_net, COALESCE(SUM(reversed_cents),0) AS reversed, COALESCE(SUM(collected_cents),0) AS allocated_receipts, COALESCE(SUM(CASE WHEN held=1 AND earned_cents>collected_cents THEN earned_cents-collected_cents ELSE 0 END),0) AS held, COALESCE(SUM(CASE WHEN method='gcash' THEN due_cents ELSE 0 END),0) AS seller_due, COALESCE(SUM(CASE WHEN method='cod' THEN due_cents ELSE 0 END),0) AS logistics_due, COALESCE(SUM(CASE WHEN due_at < ? THEN due_cents ELSE 0 END),0) AS overdue", [now()])->first();
        return array_map('intval', (array) $r);
    }
}
