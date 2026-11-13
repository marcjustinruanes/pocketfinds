# Official financial policy

The existing order, commission, shipping obligation, cash desk, treasury and payout
modules remain authoritative. No parallel payment module was introduced.

- Product commission is 10% of the product subtotal, excluding shipping. Centavo
  rounding happens once per order; commission lines retain the existing largest
  remainder allocation for exact reversals.
- Seller earnings before seller-funded promotional discounts are the remaining
  90%. Existing vouchers, funding allocations, refunds and dispute holds remain
  separate; they can change the actual cash transferred.
- Each seller order represents one parcel in the current shipment workflow.
  New checkout quotes charge PHP 50 per parcel, including the legacy checkout
  fallback. Weight and carrier selection do not increase this fee.
- Logistics receives the full PHP 50 shipping entitlement. GCash sellers forward
  the shipping amount using the existing verified carrier-payment workflow; COD
  settlement retains shipping in logistics custody, excluding it from seller pay.
- Future rider snapshots are PHP 10 for final delivery only. Pickup and transfer
  operations remain available without additional parcel compensation. Successful
  delivery evidence and COD collection checks still gate earning recognition.
- Rider payments debit logistics' separately funded `rider_payroll` balance.
  Seller proceeds and platform commission cannot fund these payments. Assignment
  snapshots, source uniqueness, payout verification and payment references retain
  their existing replay protection.
- Seller Reports displays gross product sales, product commission, net earnings,
  current prepared/verified COD payouts and actual released COD payouts. Direct
  seller GCash receipts and separate commission remittances retain their existing
  reporting and payment workflow.

The forward migration sets future company defaults and enables commission
enrollment prospectively if it was disabled. It does not backfill commission,
rewrite order totals, reprice existing entitlements, or change posted payments.
Historical obligations retain their original prices and payment evidence. Regional
rates remain available for historical orders; the new checkout policy overrides
them for new fixed-fee parcels. Existing commission suspension, dispute, refund,
approval and receiving-account controls remain available.

Example without promotional discounts: PHP 1,000 products + PHP 50 delivery =
PHP 1,050 buyer total; PHP 900 seller earnings; PHP 100 product commission;
PHP 50 logistics shipping revenue. Logistics separately owes the delivering rider
PHP 10 after successful delivery. Paying that rider leaves PHP 40 of the parcel
fee before logistics' other costs, without changing commission or buyer charges.
