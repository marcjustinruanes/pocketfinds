# App GCash verification and rider payouts — November 8, 2026

Current app-only QR setup: place `pocketfinds-qr.png` in `api/storage/gcash/`
and fill in `api/config/gcash.php`, then set `enabled` to `true`. This app-local
PHP configuration overrides the environment-based settings described below;
no Flutter `.env` file or website change is required.

Scope: Flutter and the app-local PHP API only, as requested. No files in the
neighboring website were changed. The API boots Laravel and reuses its database,
models, stock operations, OTP/photo handover, receipt confirmation, and notifications.
The service/config/migration sources in `integration/laravel/` are loaded by the
app API now and can be moved into Laravel when the web dashboards are updated.
Do not load two copies of these services when doing that integration.

## Existing implementation inspected

- `BuyerController::checkout`, `GcashWorkflow`, `GcashPurchase`, `GcashInventory`:
  the old workflow reserves a purchase, requests seller instructions and seller
  verification, and creates an order only afterward. Existing purchases remain
  available in the wallet screen; they are not silently migrated or called paid.
- `Order` stores snapshotted `items` JSON; `OrderItem` is not the active checkout
  source. `Product::deductStock` and `Order::cancelAndRestock` own inventory.
- `payment_verifications` already exists, with a unique order constraint. The new
  flow extends it rather than introducing another receipt/payment system.
- `Shipment`, `DeliveryAssignment`, `MobileDelivery`, `MobilePickup` implement the
  real seller → hub → delivery rider process, OTP, private photo, GPS and COD.
- `mobile_rider_pay` is the existing server job-credit ledger. No payout ledger
  existed; `rider_payouts` now records admin decisions against those exact credits.
- `notifications` and `MobilePush` remain the notification infrastructure.

## Screens and behavior

- Checkout displays the backend-configured QR/account when GCash is selected.
  Next reserves stock, creates native orders, then opens receipt submission.
  Price/fees/discounts come from the existing authoritative quote, rechecked in
  the checkout transaction. One seller order needs one transfer/reference.
- Receipt submission supports gallery selection, preview/replacement, JPG/PNG
  validation up to 5 MB, reference number, and an immutable server order amount.
  Retry identifiers are retained during network failures; uploaded proofs cannot
  be overwritten. Reference claims survive evidence resubmission.
- Submission sets payment `pending_verification` and order
  `pending_payment_verification`. It never verifies payment. Only an approved
  PocketFinds admin can approve/reject, explicitly permit resubmission, record a
  refund, or record payout changes. Buyers and riders cannot invoke those actions.
- Order details show payment status, server submission timestamp/deadline, and
  rejection reason. Payment support uses the configured email. Status updates
  refresh on resume, action completion and the existing foreground polling cycle.
- Native order and shipment model guards in the app API block fulfillment of
  unverified platform orders and changes to their totals/items/ownership. These
  guards must also be registered in Laravel before enabling the future web flow.
- Order history distinguishes payment/preparation/pickup/delivery/completion and
  refund states and shows payment method/status. Tracking displays nine stages
  using recorded backend events; unrecorded stages are not marked complete.
  Existing hub states remain visible as the current status. COD skips verification.
- Rider details expose the assigned rider's name/photo; phone access is limited
  to active delivery stages. Existing buyer OTP and delivery proof controls remain.
- Rider dashboard has an Assigned deliveries entry point, with New, Active,
  Completed and Cancelled/Reassigned sections. Historical reassignment rows do
  not expose the previous buyer's address or contact details.
- New manual assignments require acceptance. Arrival is recorded before pickup;
  new platform orders also enforce this on the server. Existing offer-accepted
  legacy tasks retain their existing pickup compatibility path.
- Existing scanning, OTP, private photo/GPS evidence and duplicate-handover guards
  remain in `MobileDelivery`. Delivery confirmation and problem reporting reuse
  the existing buyer policy. Closing the app never releases funds.
- Earnings show credited pending/payable/approved/released/failed/reversed amounts
  and payout references/release times. Pending totals cover existing credits waiting
  for completion; they are not estimates for assignments with no credit yet.
  Checkout delivery charges and rider job credits are shown as separate amounts.
  Actual payout requires completed-order/dispute eligibility and admin action.

## App API contract

All endpoints below are JSON POST with the existing bearer token. `order_id`,
`shipment_id`, request IDs and submission IDs are UUIDs. Server amounts win.

| Endpoint | Role / inputs |
| --- | --- |
| `market/quote`, `market/checkout` | Existing buyer checkout; Flutter sends `payment_flow: platform`; response adds `platform_payment` on native orders |
| `payments/config` | Approved buyer; official account, QR image data, instructions, upload limit, support email; no private filesystem path |
| `payments/detail` | Buyer-owned `order_id`; payment status, timestamps, deadline, permitted actions |
| `payments/statuses` | Buyer-owned `order_ids`; batched history labels |
| `payments/submit` | Buyer: `order_id`, `submission_id`, `reference`, owned uploaded `path`, `amount` matching the backend total |
| `payments/admin-decision` | Admin only: `order_id`, boolean `approve`, rejection `reason`, optional `can_resubmit` |
| `payments/admin-refund` | Admin only: cancelled/returned paid `order_id`, actual repayment `reference`; never sends money |
| `payments/admin-payout` | Admin only: `rider_id`, `shipment_id`, `leg`, `status`, actual transaction/failure `reference`; amount is read from the job ledger |
| `delivery/assigned-list` | Authenticated rider's current and previous assigned deliveries |
| `delivery/assignment-accept`, `delivery/assignment-reject`, `delivery/arrive` | Assigned rider: `shipment_id`, stable `request_id`; existing assignment and proof rules apply |
| `delivery/pickup`, `start`, `request-otp`, `capture`, `verify-otp` | Existing delivery workflow and required evidence |
| `rider/earnings` | Existing response plus `payout_totals` and `payouts`; rider read-only |

Payout transitions: absent → approved → released or failed; failed/reversed →
approved; released → reversed. References and prior transitions remain in
`platform_payment_audit`. This API never contacts GCash to transfer funds.

Payment and payout events insert native notifications. Existing preparation,
pickup and delivery events continue using the same table/outbox. Rider credit
notifications occur only when the unique job credit is inserted. Assignment and
delivery reminders are worker-driven and limited to one of each per parcel/day.
Set `RIDER_REMINDER_MINUTES` on the server to adjust the reminder threshold.

## Setup — still required on this machine

The MYSQL80 Windows service is stopped. This session could not open/start that
service, so **the new schema has not been installed in local MySQL**.
Start the existing service with the necessary Windows access, then run:

```powershell
php api/install-platform-payments.php
```

This additive installer preserves existing orders/payments and can be rerun.
Install the existing buyer/rider prerequisites from their API setup guides first.

Configure these environment variables for the API process, using the real
PocketFinds account; no account numbers or QR artwork are included in Flutter:

```text
POCKETFINDS_GCASH_ENABLED=true
POCKETFINDS_GCASH_ACCOUNT_NAME=<official registered name>
POCKETFINDS_GCASH_ACCOUNT_NUMBER=<official account number>
POCKETFINDS_GCASH_QR_PATH=<path relative to Laravel's private local storage disk>
POCKETFINDS_SUPPORT_EMAIL=<actual support email>
POCKETFINDS_GCASH_VERIFICATION_HOURS=24
POCKETFINDS_GCASH_PAYMENT_HOURS=1
```

The QR must be an actual JPG/PNG file, up to 5 MB, placed by the backend owner.
GCash checkout fails closed if the official configuration/schema is unavailable.
No fake QR, account, payment, delivery or payout is substituted.

Run the existing buyer/rider workers for expiry, reminders, completion policy and
configured push. The buyer worker now expires only *unsubmitted* platform payment
windows, restoring reserved inventory through the native order method. Receipts
under admin review are not automatically rejected at the expected review deadline.
Cancelled orders with submitted evidence can still be verified for admin refund
reconciliation; approval never reopens their fulfillment.

Real push still requires Firebase client/server configuration. The default app
support action opens the phone email client; delivery of that email is not tested.

## Verification and outstanding acceptance checks

`php api/tests/platform-payments.php` uses an isolated in-memory SQLite database,
real Laravel services and native models. It does not contact MySQL or GCash.
41 checks cover ownership, amount/MIME validation, pending-vs-paid separation,
retry/deduplication, review/resubmission, fulfillment guards, reference reuse,
admin-only payouts, completion eligibility, release records, audit, assignment
authorization, acceptance/arrival ordering and retry consistency.

Static analysis reports no issues. A debug Android APK was built successfully;
the build warns that the existing firebase_core plugin still uses Kotlin Gradle
Plugin, which is a future Flutter compatibility concern, not a build failure.

Flutter regression suite: 73 tests passed, including payment display/request
contracts and existing buyer/rider/auth/storefront regressions. These do not prove
that a gallery plugin, camera, OTP email, push, or live payment worked on a phone.

Still unverified: MySQL migration execution/concurrency; live multi-seller checkout
and stock rollback; real admin/payment/payout synchronization; real seller/hub
dispatch; OTP and proof-of-delivery end-to-end; notifications on devices; actual
GCash transfers/refunds. Re-run the existing MySQL buyer, integration, rider and
rider-requirements suites after starting MySQL. No live transfer/refund/payout was
performed. Web dashboard integration is deliberately deferred at the user's request.
