# Order to hub flow review — 11 November 2026

Reviewed application code and the current imported MySQL database. Database checks were read-only; no orders were advanced or payments submitted.

## Flow

| Step | Actor | Result / gate |
| --- | --- | --- |
| Checkout | Buyer | Server validates cart, address, quote hash, payment method and checkout UUID. COD creates one `placed` order per seller and deducts stock immediately in the commerce checkout. GCash reserves stock for one hour and uses the payment workflow before order placement. |
| Accept | Seller | `placed` → `confirmed`. Commerce orders already reserved stock; legacy orders deduct stock here. |
| Pack | Seller | `confirmed` → `preparing`. |
| Request pickup | Seller | Select a company with operational hubs at both ends; create shipment and shipping obligation; order → `ready_for_pickup`. Approved carrier rate must match the order shipping snapshot. GCash shipments require the carrier receiving account. |
| Approve | Logistics company admin | Verify GCash shipping settlement where applicable, then approve pickup. Shipment remains `ready_for_pickup`; an expiring pickup offer is attempted. |
| Accept and collect | Pickup rider, then seller | Rider accepts the offer and confirms the matching tracking number. Seller confirms handover second. Both confirmations advance order and shipment to `picked_up`. |
| Origin receipt | Approved staff assigned to origin hub | Scan/receive parcel. Same-location shipments become `sorted`; shipments needing a transfer become `at_sorting_center` and get hub legs. Company admins cannot attest physical receipt in place of assigned hub staff. |
| Transfer request | Origin hub staff | Select eligible parcels and destination hub, producing a pending batch. |
| Transfer decision / dispatch | Company admin | Approve batch separately from dispatch; assign eligible rider/vehicle. Shipment becomes `hub_transfer`. |
| Transfer receipt | Transfer rider, destination staff | Rider confirms pickup first; destination staff receive parcels, including partial batch receipt. Intermediate hops repeat. Final receipt advances order and shipment to `sorted`. |
| Last mile | Delivery rider | Assigned rider moves through `assigned_to_rider` → `out_for_delivery` → `delivered`; delivery requires proof and buyer OTP, plus COD recording when applicable. Buyer receipt or timed completion completes the order. |

## Current blockers and findings

1. **No approved carrier shipping rates.** `logistics_shipping_rates` has no active entries. New shipment creation calls `LogisticsShipping::createObligation()`, which refuses handoff without a matching approved destination-region rate. Thus new seller handoffs cannot currently complete even on covered routes. `logistics_shipping_accounts` is also empty, blocking positive-value GCash shipping once rates are configured.
2. **Coverage is enabled without receiving staff.** Of 87 operational hubs, 85 have no approved logistics hub staff assigned. Only Cavinti (`logisticstest`) and Liliw (`PocketFinds Logistics`) have an approved hub staff member. Physical hub receipt requires that staff assignment, so marking a hub operational does not make it capable of receiving parcels.
3. **Three existing pending orders have no carrier covering both locations.** `PF-D5K1HQNV7AMX` and `PF-20260919000223-YNTOH` are placed; `PF-20260919000222-AJPI4` is preparing. Their seller handoff cannot select a company with the required origin and destination coverage. Checkout preflight currently warns about absent coverage/rates and allows checkout in these cases; the hard rejection occurs at seller preparation.
4. **A picked-up legacy shipment has no routing ownership.** `PF-SHIP-260905-KVJAQP` has null company, origin and destination hub/province. Company-scoped logistics queries cannot process it normally. It requires a deliberate legacy-data recovery with the actual carrier and locations established.
5. **Same municipality name across different provinces produces no route.** `Shipment::needsHubTransfer()` correctly considers provinces, but `LogisticsHub::buildRoute()` returns an empty route when municipality names match. Read-only reproduction: Santa Cruz, Laguna → Santa Cruz, Ilocos Sur returns `[]`. Such a shipment can reach `at_sorting_center` without a leg available for transfer. See `app/Models/LogisticsHub.php:108` and `app/Models/Shipment.php:182`.

## Database consistency

- All listed application migrations are recorded as run.
- 16 orders: 4 placed, 2 preparing, 1 picked up, 1 out for delivery, 2 completed, 1 returned, 5 cancelled.
- 5 shipments and 1 completed hub-transfer batch.
- No mismatched order/shipment statuses, allowing completed orders whose shipment remains delivered.
- No missing shipment for an active shipping-stage order.
- No existing `at_sorting_center` / `hub_transfer` shipments without hub legs.
- Shipping obligations are empty; historical shipments do not establish that the new financial handoff path is configured.

## Recommended order of work

1. Publish carrier rates matching the intended seller shipping policy and configure carrier GCash receiving accounts.
2. Staff the hubs used by actual routes; reconcile operational coverage with staffing and rider availability.
3. Repair the same-name cross-province route builder and verify that route with a regression test.
4. Recover the legacy shipment and resolve uncovered pending orders using verified business details.
5. Run a fresh checkout-to-hub scenario against an isolated copy of the imported database after configuration is complete.

## Follow-up: how hub staff assign parcels to riders

### Direct assignment

- **Seller pickup:** Hub Dashboard → Pickup Requests → select rider → Assign. The request must be approved by the company admin, originate at the staff member's hub and have no pickup rider. `LogisticsController::assignPickupRider()` assigns the rider and writes an accepted pickup assignment immediately. Parcel stays `ready_for_pickup` until rider receipt and seller handover confirmations.
- **Buyer delivery:** Operations → Delivery Assignments → select courier → Assign. Parcel must be `sorted` at the destination hub. `assignCourier()` assigns the rider, updates order/shipment to `assigned_to_rider`, records acceptance immediately and sends a notification. This path does not ask the rider to accept a separate offer or scan the parcel before assignment.
- Direct pickup/delivery checks require an approved, online, same-company two-wheel rider assigned to the matching parcel hub, with fewer than three combined active pickup/delivery jobs. Company-vehicle riders also need an available matching vehicle at their operational hub. Pending reassignment blocks a new assignment. Conditional writes prevent taking a parcel that has already moved on; operational middleware wraps writes and audit logging in a transaction.
- **Hub transfers:** Hub staff request a batch. Company admin approves and dispatches it to an approved four-wheel rider at the origin hub. Hub staff cannot use the transfer dispatch endpoint themselves. Rider confirms physical pickup separately.

### Findings in the assignment path

1. **Automatic offers hide the manual delivery assignment control.** `resources/views/logistics/assign.blade.php:37` first displays the courier linked to any delivery assignment, before checking for a sorted parcel. Automatic offers already contain a courier while the parcel is still sorted and `shipments.courier_id` is null. Thus an offered, declined or expired assignment with an existing courier record hides the select/Assign form. The underlying manual endpoint permits assignment, but the page does not expose it in that state. The displayed courier can also be mistaken for a confirmed assignment.
2. **Automatic offers do not use the same hub/vehicle eligibility rules as direct assignment.** `RiderDeliveryOffers::eligible()` filters company, approval, online status, two-wheel vehicle and active job count, but does not require assignment to the parcel hub, an operational hub, verified vehicle ownership or an available company vehicle. Municipality affects ordering only. Pickup acceptance subsequently applies the stricter hub check, so an offer can go to a rider who cannot accept it. Delivery acceptance checks the offer and company but does not reapply the stricter hub/vehicle rules, allowing a delivery claim that manual assignment would reject. This is established from the code; no live claim was made.
3. **Self-accepted delivery uses a weaker job-limit check.** `RiderController::acceptRequest()` counts active deliveries only, whereas manual assignment and offer creation count active pickups plus deliveries. A stale offer can therefore be accepted after other pickup work has brought the rider to the combined limit. Delivery acceptance also lacks the rider row lock used by direct assignment, leaving concurrent claims of distinct parcels insufficiently serialized for the capacity limit.
4. **The page description is stale.** It says first-come-first-served self-acceptance; current implementation sends a single 60-second offer, with a maximum of three failed offers, and requires the offered rider to accept. Offer expiration/retry is driven by application calls such as rider page visits and availability changes.

Recommended fixes: keep an explicit manual assignment action for unclaimed sorted parcels regardless of offer history; reuse a single hub/vehicle eligibility check across offers, manual assignment and acceptance; lock the rider and recheck combined capacity when accepting. Separate the displayed offered rider from the confirmed assigned rider.

## Automated verification

Ran `php artisan test --filter='FulfillmentWorkflowTest|HubTransferWorkflowTest|LogisticsAuthorizationTest|LogisticsShippingTest|BuyerCommerceTest|ParcelCustodyTest'` against the configured isolated SQLite test fixtures: **137 passed, 2 failed, 2,656 assertions**. The two failures are registration fixtures in `FulfillmentWorkflowTest`: current validation requires middle name, PDF resume and NBI clearance, while these fixtures omit the required fields and send an image resume. Both requests stop at 422 validation. This is not a successful full test run. Pest also reported permission denied writing its vendor test-run-history file.

This review does not establish browser, scanner-hardware or real-payment behavior. No application behavior or live business records were changed.
