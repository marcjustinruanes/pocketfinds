# Mobile buyer flow aligned with the website

Website source files are unchanged. Changes are in the Flutter app and its
app-local PHP API, using the same PocketFinds accounts, records and native
Laravel buyer rules.

The mobile flow is:

1. Browse/search products and shops; choose an in-stock product option and quantity.
2. Add to cart or Buy Now. Selected option IDs, prices and stock match the native
   legacy options and matrix variants. Cart totals use the selected option price.
3. Checkout: choose a saved delivery address, payment method, eligible voucher
   and optional note to the seller. Saved addresses support adding, editing,
   setting a default and deleting a non-default address, up to the website's limit
   of ten. Profile addresses are seeded once as the initial default.
4. COD creates an order after server validation. A changed price updates the
   cart and requires the buyer to review it before retrying. A network retry
   reuses its checkout identifier, preserving the committed address snapshot.
5. GCash reserves a purchase first. Open its seller conversation for instructions,
   submit the receipt, wait for seller verification, then place the verified
   order. Reservation expiry and seller decisions remain controlled by the
   existing GCash workflow. No buyer-side auto-approval is added.
6. Orders use All, To Ship, In Transit, Out for Delivery, Delivered, Completed and
   Cancelled tabs. Delivered and Completed remain distinct.
7. Before pickup, eligible orders can be cancelled using the website's predefined
   reasons. Native cancellation/restocking rules run on the server. Retries do
   not repeat cancellation history or stock effects.
8. After handover, the buyer confirms receipt. Completed orders can receive one
   website-style order rating and optional comment (1,000 characters maximum).
   Buy Again re-adds eligible products at their current prices and stock, supports
   current matrix variant IDs, skips removed options/unavailable shops, and caps
   quantities to current inventory. It never recreates or pays an old order.
   Its request identifier prevents duplicate quantities on network retry.

Existing mobile buyer features remain available. This aligns the core purchase
and order-management journey; it does not claim every website screen or account
tool has been ported. Physical-device camera, maps, Google consent and actual recipient mail
delivery still need their respective device/external configuration checks.

## Buyer reliability improvements

- GCash payment instructions appear directly on the saved purchase screen as
  soon as the seller provides them. Messaging is optional once instructions
  exist. A receipt upload remains available for the native `held` reservation
  state. Payment verification creates an existing in-app notification; tapping
  it reopens that purchase, and the payment screen refreshes automatically.
  The buyer explicitly continues order placement rather than starting checkout
  again. Seller verification is still required; the app does not invent a
  payment destination or auto-approve receipts. No OS push service was added.
- Expired purchases with money sent can be reported for reconciliation using
  the existing GCash workflow. Cancelling a prepaid order releases its inventory
  under native rules and marks payment for manual resolution. It does not
  record an automatic refund or promise that a bank/e-wallet reversal occurred.
  The buyer is directed to the seller for actual repayment/reconciliation.
- Cancellation checks both locked order and parcel state, including pickup
  timestamps, so a stale pre-pickup order status cannot bypass a parcel pickup.
  Unavailable cancellation is disabled with a reason in the mobile UI.
- Order Tracking shows real persisted events and timestamps with labels such as
  Order Placed, Seller Processing, Ready for Pickup, Picked Up, Out for Delivery,
  Delivered and Completed. Hub, failure, cancellation and return events remain
  distinct. Stages that have no event are not fabricated.
- Missing/damaged parcel reports create cases in the existing `logistics_issues`
  and `logistics_issue_events` tables, notify the seller and relevant logistics
  team, and show case status/resolution in the app. Report retries reuse their
  identifier and cannot create duplicate cases. Open buyer cases pause receipt
  confirmation in the mobile API until staff resolve them. Reporting does not
  mark an order received or issue a refund. Existing staff case handling is
  reused. Delivered/completed orders also expose Contact Seller, including
  orders without a linked logistics route that cannot open a logistics case.
  Website source is unchanged, and independent website confirmation or
  automatic completion rules remain owned by the website team.

No new database tables, external payment service, or website edits were needed
for these improvements. `api/BuyerOrderSupport.php` contains the additional
parcel-state checks, case handling and current-inventory Buy Again adapter.

## Implementation

- `api/Buyer.php`: approved-buyer-only API adapter for native address management,
  cancellation, order reviews and Buy Again; ownership and current status checks.
- `api/Commerce.php`: saved address ownership, buyer notes, price review and stable
  checkout retries. No new schema migration is required for these buyer changes.
- `api/Queries.php`, `api/Gcash.php`, `api/router.php`: distinct order states,
  seller information for GCash messages, and buyer routes.
- `lib/services/buyer_workflow_service.dart`: address/order actions and safe retry IDs.
- `lib/screens/buyer/saved_addresses_screen.dart`, shared address form: native
  address-book flow using the existing PSGC picker.
- `lib/screens/buyer/buyer_order_actions.dart`: cancellation, ratings and Buy Again.
- Buyer checkout, order detail/list, profile and GCash purchase screens connect
  those actions to the backend. GCash purchase data refreshes periodically.
- Product/cart models, services and detail screens preserve native option
  identifiers and selected prices rather than sending display labels as IDs.

API requests are authenticated POSTs under `/buyer/`: `addresses`,
`address-create`, `address-update`, `address-default`, `address-delete`, `order`,
`cancel`, `review`, and `buy-again`. Order actions require an owned UUID
`order_id`; Buy Again also requires a UUID `request_id`. Saved address mutations
use their owned integer ID. The native website controllers supply address,
cancellation and review validation; no credentials are copied into Flutter.

## Validation

```powershell
php api/tests/buyer.php
php api/tests/integration.php
flutter analyze
flutter test
flutter build apk --debug
```

The 36 buyer API checks cover ownership, default addresses, cancellation limits
and retries, completed-order reviews, duplicate review rejection, Buy Again
retries, separate Delivered/Completed queries, selected addresses and buyer notes,
address edits after checkout, and price refresh without reserving stock.
The 41 integration checks cover COD, GCash reservations and verified purchase
resumption, prepaid cancellation/manual reconciliation, buyer receipt,
reviews/rewards, messages and private media. All database fixtures roll back;
automated tests do not send real email or payments.

The full Flutter suite passed 54 tests, including native option IDs, selected
cart prices, separate order tabs, payment notification classification, tracking
labels, and issue/Buy Again request reuse after a lost response. Buyer API checks
also cover missing/damaged parcel cases, duplicate reports, confirmation blocked
by an open case, staff resolution followed by explicit receipt confirmation,
stale order status after pickup, current matrix prices and removed options.
Flutter analysis passed with no issues, PHP syntax checks passed, and the updated
Android debug APK built successfully. The 42 rider regression checks also passed.
