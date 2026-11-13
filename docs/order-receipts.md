# Order details and printable payment records

The buyer and seller order modals, rider delivery page, logistics shipping-payment
page, GCash purchase page and seller settlement workspace retain their existing
routes, forms and actions. Added sections use the same recorded financial data
through the read-only `App\Support\OrderDetails` presenter. Queries are batched
for order lists; viewing or printing does not recognize earnings or move money.

The buyer can inspect an unpaid platform GCash order's products and total while
the existing payment-progress and fulfillment approval gates remain in place.
Product prices come from the agreed order snapshot, including promotional prices.
Product and shipping discounts are separated when voucher snapshots establish
their allocation; older combined discounts are labeled accordingly.

`GET /orders/{order}/receipt` provides a printable HTML record and a browser
Print / save PDF action. It requires an approved account and permits only the
owning buyer, owning seller with existing payment visibility, platform admin or
the shipment's logistics company. Buyers do not see internal settlement or payroll
amounts. Riders use their existing assigned-delivery page rather than this export.
Responses use `private, no-store`; no delivery OTP or private storage path is exported.

Payment labels are evidence-based:

- A placed, delivered or completed order does not by itself prove payment.
- A proof upload remains under review; confirmed partial payments leave a balance.
- Verified GCash identifies whether funds went to the platform or directly to the
  seller. Overpayment is shown separately from the amount applied and refund state.
- Recorded COD collection proves collection, not verified hub remittance. Cash
  allocations counted by hub staff establish the remittance amount independently.
- Seller payouts and rider payroll show their recorded status, amounts and payment
  dates. Buyer payment approval is never displayed as a released seller payout.
- Return and cancellation refunds distinguish requests from recorded repayments.

The audit confirmed two GCash review paths. New admin-reviewed purchases receive
payment at the platform account; legacy seller-reviewed purchases receive payment
directly at the seller account. The existing logistics seller-payout workflow is
COD-specific. This change exposes those facts without creating a new prepaid
settlement module or claiming a transfer that has not been recorded. Any platform
GCash seller disbursement must be reconciled through an explicit payment workflow;
printing a receipt does not perform or prove that disbursement.

Validation covers receipt authorization, pending/partial/verified payments, COD
versus remittance, legacy GCash, overpayment, existing order controls and financial
regressions. `tests/browser/order-receipt-check.cjs` also exercises desktop/mobile
receipts, PDF output and both order modals against synthetic rendered fixtures.

## Updating an existing teammate checkout

Keep the device's existing `.env`, application key, MySQL database and uploaded
files. Pull the code, then run:

```sh
composer install
npm ci
npm run build
php artisan config:clear
php artisan migrate
php artisan view:clear
```

The forward financial migration applies future defaults and commission enrollment;
it preserves existing order amounts, payment evidence and earned entitlements.
Use the existing database with `migrate`; do not recreate it with `migrate:fresh`.
Restart the local server and queue worker after updating. The app uses each
device's own ignored `.env`; no local database credentials or generated build
artifacts are committed.

The receipt browser check uses the Playwright development dependency and an
installed Chrome/Edge on Windows (or Playwright's Chromium on other platforms).
Its HTML previews are synthetic fixtures under ignored `storage/framework/testing`.
