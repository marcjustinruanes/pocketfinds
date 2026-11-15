# Website to mobile rider flow review

Reviewed 2026-11-15 against the local website at ../pocketfinds, the app embedded website revision f4158032a1f568bd238e1390ddd93dd440f1c086, mobile PHP adapters, and Flutter rider screens. This is a source and integration-test review, not a completed live order across independent web and mobile deployments. No application code was changed for this review.

## Follow-up implementation status

After approval of this review, the following findings were addressed locally:

- Local website CodSellerPayouts and CommissionLedger now require explicit buyer
  confirmation rather than the seven-day timeout. The embedded mobile policy keeps
  the same stronger gate. Deployment to any hosted website remains necessary.
- Mobile LogisticsFinance now follows the local website: final deliveries earn PHP
  10; no new pickup or transfer compensation snapshots are generated. Existing
  earned/paid records are retained.
- Existing website cash permissions are retained deliberately: assigned hub staff
  count physical remittances and company logistics admin handles distribution.
  Rider self-confirmation is denied and verified by integration tests.
- Mobile completion validates GPS coordinates and saves them with server time in
  delivery_verifications. Additive migration 2026_11_15_000001 is installed by
  scripts/install-rider-website-schema.php; deploy it with the mobile backend.
  Coordinates are device-reported evidence, not proof against location spoofing.
- Transfer legs now expose the assigned batch parcel manifest and receiving counts.
  The website retains transfer approval, dispatch and destination receiving controls.
- Rider task/request/active cards now explicitly label shipping amounts.

Revalidation: 67 backend compatibility checks and nine Flutter tests passed.
Backend tests include native seller confirmation after mobile pickup, native hub
receipt/sorting, native buyer Order Received, actual partial/full hub cash counting,
self-confirmation denial, GPS persistence and the payment policy gates. Fixtures
are rolled back. No real customer order, bank payment or production deployment was
performed. The original findings below describe the state at initial review.

Remaining launch work: a real checkout and seller preparation through separately
running interfaces, same-hub and cross-hub jobs, prepaid delivery, failed/returned
jobs, and actual logistics payouts. There is still no frozen final-delivery run
manifest, automatic bank payout, refund-cash module or rider attendance module.

## Overall conclusion

The normal parcel lifecycle has corresponding rider app features and uses the native orders, shipments, delivery_assignments, notifications, COD collection, compensation and payout records. It is not yet a fully aligned, production-verified integration. One MySQL connection does not align different service revisions or financial policies.

## Process and responsibility

| Stage | Website responsibility | Rider mobile counterpart | Assessment |
| --- | --- | --- | --- |
| Buyer checkout | Validate address, stock, COD/GCash and order placement | Assignment appears later, not during checkout | Correct separation; buyer checkout is not a rider action |
| Seller confirms and prepares | Confirm order, prepare parcel, choose eligible logistics and mark ready | Seller pickup list | Present; pickup remains hidden until logistics approval |
| Logistics approves and assigns pickup | Approve request and issue pickup offer | Accept/decline seller pickup | Present; same native assignment service |
| Seller physical handover | Seller confirms after rider receipt | Arrived at seller, scan/manual tracking, physical receipt confirmation | Present; independent confirmations retained |
| Origin hub receipt and sorting | Hub staff record receipt; sort or request hub transfer | Pickup stage and hub-handoff instructions | Present; hub receipt intentionally is not a rider button |
| Hub-to-hub route | Request/approve transfer batch, assign rider, receiving hub scans receipt | Hub Transfers and physical pickup confirmation | Partial UI parity: app presents legs/parcels; full website batch manifest and progress are not exposed |
| Final delivery assignment | Offer sorted parcel to eligible destination rider | Assigned deliveries, accept/decline | Present; company, hub and rider scope remain enforced |
| Final parcel pickup | Record arrival and verify exact parcel barcode | Scan/manual barcode and verification checklist | Present; no frozen whole-run manifest gate |
| Buyer delivery handover | Native OTP, photographic proof and exact COD collection | Completion screen and proof upload | Present; separate from buyer Order Received |
| Failed attempt / return | Logistics decides subsequent action; physical receipts remain independent | Record failure, view instruction/status and history | Partial: independent deployment status and retry/return workflow parity still needs testing |
| Buyer Order Received | Buyer completes the order and records buyer confirmation | Rider sees completed order | Present; this is correctly a buyer action |
| COD remittance | Hub counts and confirms physical cash | Per-collection submit, submitted/confirmed/outstanding balances | Present; operational website service reserves submitted cash without clearing it |
| Seller and owner distribution | Logistics treasury and commission workflow | Read-only rider explanation; not a rider transfer control | Financial gate differs between deployments; see blockers |
| Rider compensation and payout | Recognize delivery, fund/prepare/verify/pay, actual payment evidence | PHP 10 new delivery earnings, daily grouped totals, receipts/acknowledgement | Present as records and manual payout workflow; not automatic daily money transfer |
| Messages and notifications | Shared recipients, orders and messages | Messages, notifications, linked jobs | Present; delivery of push alerts still depends on configured workers and device setup |

## Integration blockers and limits

1. The local website CodSellerPayouts still permits full verified COD to become eligible after 168 hours without explicit buyer confirmation. The mobile override requires explicit buyer confirmation. Both deployed entry points need the same approved policy. CommissionLedger's earning condition also needs consistent deployment.
2. Local website FinancialPolicy uses PHP 10 delivery compensation and zero default pickup/transfer compensation. The mobile LogisticsFinance override still has PHP 30 pickup and PHP 150 transfer defaults inherited from the embedded revision. These must be reconciled before enabling new company settings. Historical earned/paid amounts should not be silently rewritten.
3. Daily grouping in the mobile app is reporting of earned/paid/unpaid money. Actual daily funding, verification and payment are still logistics tasks. No automatic payout scheduler or bank transfer was implemented.
4. The mobile cash desk delegates confirmation to the website service, which allows assigned Hub Staff to count cash. Company Logistics Admin handles treasury distribution. If the company admin must personally count cash, that permission change is still required; changing the UI wording is insufficient.
5. Hub transfer support exists but mobile does not expose the full website batch manifest workflow. Final delivery checklist verifies individual parcels; there is no frozen final-delivery run manifest.
6. The app collects latitude/longitude in delivery completion, but RiderWebsiteDelivery forwards native OTP/photo/COD fields without persisting those coordinates in the shared delivery record. GPS proof must not be claimed as shared evidence yet.
7. Rider task cards display a shipment delivery_fee amount without a clear rider-pay label. The assigned-delivery screen explicitly labels order delivery fee. Verify and distinguish shipping revenue from the rider's PHP 10 reward throughout all task screens.
8. Refund-cash assignments and rider QR attendance are not exposed by the app integration. Add them only if the deployed website requires those rider workflows. They do not replace COD remittance or availability.
9. Production requires a reachable HTTPS API_BASE_URL, shared schema migrations and policy/service version, consistent identity and encryption settings for shared OTP/files, private proof storage accessible to the serving backend, and configured notification/maintenance workers. Phone code should never connect directly to MySQL.

## Validation performed

Re-ran api/tests/rider-website-compatibility.php: 57 checks passed. They cover native offers/acceptance, shared buyer OTP and proof, exact COD and duplicate protection, failed attempts, seller pickup arrival, website compensation, COD submission ownership/idempotency, daily PHP 10 recognition, explicit buyer confirmation and commission excluding shipping. Database fixtures are rolled back.

The test does not execute a complete new website checkout, seller preparation, origin receiving, cross-hub transfer, actual hub cash count, real seller/admin distribution and actual daily rider payment as one connected live scenario. Before launch, exercise one same-hub COD order, one cross-hub COD order, one prepaid order, and one failed/returned delivery through both interfaces. Confirm identities, stock, statuses, balances, receipts and no duplicate earnings after refresh/retry.

## Source map

- Website SellerController: readyForPickup and confirmPickup.
- Website LogisticsController: approveRequest, assignPickupRider, receiveAtSortingCenter, transfer approvals/receiving and assignCourier.
- Website RiderController: pickup receipt, availability, delivery advance, OTP and COD.
- Website BuyerController: checkout and explicit receipt completion.
- Mobile api/Pickup.php, api/Assignments.php, api/Delivery.php and api/RiderWebsiteDelivery.php.
- Mobile api/RiderWebModule.php for COD, earnings, transfer and payout adapters.
- Mobile api/BuyerWebsiteRuntime.php for pinned service mappings.
- Mobile integration/laravel/app/Services for the current financial overrides.
- Flutter lib/screens/rider for actual screens and controls.
