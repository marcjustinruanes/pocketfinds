# Rider functions added from the website

The existing native rider app now exposes the three missing website functions:

| App entry | Function |
| --- | --- |
| Tasks → My hub transfers | Assigned in-transit transfer legs, tracking number, origin/destination hub, leg type, physical pickup confirmation, and en-route state |
| Earnings → Payout account & receipts | Register/update the rider's GCash receiving account using the shared website financial service |
| Earnings → Payout account & receipts | View app and website payout records and acknowledge receipt of an actual released/paid payout |

Hub transfer notifications now open My Hub Transfers; rider payout notification
types open the payout screen. Both screens refresh after actions, on resume,
and while visible, using the existing delivery refresh infrastructure.

## Backend integration

`POST /rider/web-module` accepts authenticated actions `transfers`,
`confirm-transfer`, `payouts`, `destination`, and `acknowledge`.
The endpoint runs the pinned website runtime installed for the buyer integration.
The current rider's identity comes from the existing app bearer token.

- Hub confirmations call `HubTransferWorkflow::confirmPickup`. Existing assignment,
  company, shipment state, recovery holds, transactions, notifications and audit
  checks remain in the shared service. Confirming pickup does not mark arrival.
- Receiving accounts call `RiderCompensation::destination`, including the website's
  GCash number validation, rider authorization and financial event history.
- Website payout receipts call `RiderCompensation::acknowledge` and require `paid`.
- Existing app payout receipts call the added `RiderPayouts::acknowledge` and
  require `released`. Acknowledgment only records a server timestamp and audit
  event. It does not change payout status, amount, approval, or transfer money.
- The combined history lists real records from each ledger without creating,
  migrating or recalculating financial entitlements. Each acknowledgment retains
  its source, and all lookups are scoped to the authenticated rider.
- Receiving account submissions and hub confirmations use the existing replay
  journal. Duplicate receipt acknowledgments preserve the first timestamp.

Models/tables reused: `ShipmentHubLeg`, `HubTransferBatch`, `Shipment`,
`User`, `logistics_rider_destinations`, `logistics_rider_payouts`,
`logistics_financial_events`, and the existing `rider_payouts` ledger.

## Schema

`php scripts/install-rider-website-schema.php` installs the website's financial
foundation, transaction, cash desk, treasury/seller-payout dependencies and rider
payout migration, plus nullable `rider_payouts.acknowledged_at`.
The treasury migration has one compatibility guard for the app's existing
`orders.buyer_confirmed_at`; existing timestamps are preserved. No rates,
balances, sample payouts or demo transfers are seeded. Existing app payment
verification and rider payouts remain in place.

A local database backup was saved at the ignored
`.tmp/before-rider-update.sql` before schema installation. The separate website
checkout's source files were not edited. The pinned runtime and its Composer,
environment and storage dependencies must remain available on the backend.

## Verification

- `api/tests/rider-web-module.php`: 21 checks for ownership, roles, account
  validation, transfer confirmation, retries, audit behavior, payout state and
  receipt acknowledgment in both ledgers. Temporary data rolls back completely.
- `test/widgets/rider_web_module_test.dart`: pickup confirmation, pending payout
  controls, receipt acknowledgment payload and account validation at 320 px.
- Existing payment/assignment guard suite: 41 checks passed.
- Existing buyer HTTP suite: 29 checks passed after schema installation.
- Shared buyer GCash checkout and Google identity checks still pass.
- Dart analysis is clean and the Android debug APK builds successfully.

Actual hub dispatch/receipt and admin payout processing need operational staff
records. The app does not fabricate these records or perform money transfers.
