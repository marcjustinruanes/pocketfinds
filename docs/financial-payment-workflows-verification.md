# Financial and payment workflows — verification only

Subsequent approved change: [GCash-only electronic payments](gcash-only-payment-channels.md). The verification below records the implementation before bank support was disabled for new operations.

Reporting follow-up: [Seller and Platform Admin reporting correction](financial-reporting-correction.md) resolves the legacy commission/Net Profit defect described below without changing payments or historical records.

29 October 2026. Current Laravel source, routes, middleware, models, financial schema definitions, Blade workspaces and existing tests were inspected directly. No application/test source, migration, financial rule, role or business record was changed. Outputs are this report and private verification artifacts. Tests use synthetic accounts and evidence; no real cash, bank or GCash transfer was made.

## Assessment

**The normal manual COD, buyer-to-Seller GCash, logistics shipping payment, Seller settlement and Rider payroll processes are implemented and passed the selected automated checks. They do not need rebuilding.** The result is conditional on the required schema and truthful external-payment/custody attestations.

An unqualified “complete and ready” conclusion would be inaccurate:

1. **Deployment readiness is blocked:** read-only `php artisan migrate:status` shows the four existing migrations 000006–000009 are Pending on the configured database. Tests install their required schema in isolation. RTS custody is unavailable without 000007; writes using the new activity/investigation columns need 000008/000009; the founder database unique constraint needs 000006. The core finance migrations through Phase 6 and operational recovery 000005 are marked Ran. No pending migration was applied in this review. No live account request was used to demonstrate a schema failure.
2. **A confirmed reporting defect remains:** legacy Seller Reports calculates commission-based “Net Profit,” including completed order shipping in its base. It does not represent verified money received or the approved COD Seller payout and disagrees with the no-commission settlement policy. This is a misleading report, not a reproduced deduction from the payout ledger.
3. **Exception handling is deliberately partial:** cross-hub reverse dispatch, partial/damaged RTS restocking, corrective financial reversals, bank-statement matching and externally sent transfers that cannot be recorded remain manual/design boundaries. No automatic refund or deduction is inferred.

“Pass” below means the inspected behavior and executed automated evidence passed in a properly migrated isolated application. It does not certify a live bank receipt, real document authenticity, all deployments, or every possible request interleaving.

## Feature results

| Feature | Implemented | Correctness | Actual current workflow / problem | Correction necessary? |
| --- | --- | --- | --- | --- |
| COD checkout and preparation | Yes | Pass | Commerce quote uses current products/address/shipping/vouchers, verifies quote hash, saves a retry-safe checkout and order, and allocates stock. Seller confirms/prepares, then chooses a covered company. | No defect reproduced in tested flow. |
| Pickup, hub receipt and transfer | Yes | Pass | Assigned approved rider plus Seller confirm pickup; actual assigned approved Hub Staff receives. Transfers use dispatched batches, rider pickup and member receipts. Admin cannot attest physical receipt. | No financial bypass reproduced. |
| COD collection at delivery | Yes | Pass | Assigned approved final rider records the exact final order total with explicit handover confirmation. Collection, delivery/assignment timestamps, history and earnings commit together. | No defect reproduced; requires installed finance schema. |
| Remittance and counted cash | Yes | Pass | Submission reserves selected outstanding collections; only Staff at the recorded hub counts/allocates physical cash. Shortages remain outstanding; overages stay unallocated. | No defect reproduced. |
| Hub/treasury custody | Yes | Pass | Authorization does not move cash. Staff release creates transit; actual destination/treasury receipt records counted money and differences. Qualified COD and unallocated overages stay distinct. | No defect reproduced. |
| COD Seller eligibility | Yes | Pass | Delivered COD, approved gross shipping obligation, full Staff-verified collection, buyer confirmation or 168 hours, and return/dispute protections are required. Proceeds are collected COD less gross logistics shipping; additions require separate funding. | No defect reproduced. |
| Seller payout/payment/acknowledgement | Yes | Pass | Admin prepares/reserves, verifies instruction, then records exact actual external payment with reference/private evidence; Seller separately acknowledges. Holds and eligibility are rechecked at payment. | No defect reproduced. Actual bank transfer remains unverified here. |
| Buyer GCash paid directly to Seller | Yes | Pass | Seller supplies receiving instructions; buyer submits private proof; only the owning eligible Seller verifies exact amount/reference. Verified purchase produces one linked order. | No defect reproduced. Proof alone is not payment. |
| Seller GCash shipping payment | Yes | Pass | Carrier-approved gross obligation and receiving instructions are snapshotted. Seller submits full gross payment; assigned company Admin verifies exact receipt. Positive new GCash shipping gates pickup until verified. | No defect reproduced. Old policy-0 shipments intentionally remain legacy review cases. |
| Avoid duplicate Seller payout for GCash | Yes | Pass | COD payout eligibility rejects GCash orders. Buyer money is already paid directly to Seller; logistics shipping is a separate receipt. | No defect reproduced; isolated GCash order created zero COD collections and zero Seller payouts. |
| Pickup / whole-batch / delivery earnings | Yes | Pass | Default rates are PHP 25 / 150 / 40; immutable assignment/batch snapshots govern actual entitlement. Pickup needs two-sided handover and origin receipt; batch needs every member/leg received; final delivery needs matching completion evidence and exact COD where applicable. | No defect reproduced in executed live flows. Historical ambiguous evidence still needs review. |
| Rider payroll/payment/acknowledgement | Yes | Pass | Only evidenced `rider_payroll` contributions fund payouts. Admin reserves/verifies/pays one full earned snapshot with exact amount, sent confirmation, reference and proof; recipient acknowledges separately. | No defect reproduced. No implicit use of Seller COD or GCash shipping receipts. |
| Company/role/private-proof controls | Yes | Pass | Fresh actor checks and company/source/recipient scopes supplement route middleware. Hub Staff handles its hub; Admin handles company treasury/payment/reports; recipients see their own records. | No new role/permission expansion needed. Deployment must include the pending ownership control. |
| Reconciliation and duplicate protection | Yes | Pass | Integer centavos, source locks, unique constraints/references, revisions and audit transactions prevent tested duplicate movements. Current snapshot separates funds, reservations, transit, obligations, earnings and payments. | No numerical defect reproduced. Not an independent bank-balance verification. |
| Cancellation, failed delivery, returns and disputes | Partial | Pass within implemented boundary | Pre-shipment cancellation releases stock safely; failure alone creates no collection/refund. Minimal RTS requires owning Seller full sellable receipt. Active returns/disputes block COD payout; later paid exceptions remain visible without clawback. | Install existing RTS schema before use. Broader corrections/reverse routing need approved policy; do not silently automate them. |
| Lost/damaged financial handling | Partial | Pass for non-mutation; claim settlement not implemented | Issues records investigation/evidence/outcomes without changing balances or earnings. A generic issue does not automatically create a financial dispute/hold or refund. | Manual investigation must deliberately use existing dispute/hold tools when appropriate. Any automatic linkage/liability policy requires approval. |
| Seller legacy financial report | Yes | **Fail** | Completed-order value minus configured commission is labelled “Net Profit / After fees”; it is independent of actual Seller payout, costs, refunds and funds verification. | **Yes:** align labels/calculation with approved financial sources in a separately approved fix. Do not add commission deductions to make ledgers match it. |
| Real external cash/bank/GCash transfer | Yes, as manual recording | **Not Tested** | The application records authenticated assertions and evidence; it neither initiates nor independently verifies provider movement. | No provider integration is implied by the approved manual design. Actual reconciliation remains an operational responsibility. |
| Configured-environment end-to-end operation | Partial rollout | **Not Tested — blocked by pending schema** | Four already-delivered migrations remain Pending. No real accounts were used to exercise them. | Complete the existing reviewed rollout, then repeat acceptance on that environment. This is not a request for a new migration or feature. |

## Actual money paths and source map

### COD

`Buyer checkout → Seller preparation → shipment/approved gross shipping → pickup/Staff receipt → assigned Rider delivery + exact collection → Rider submission → recorded-hub Staff physical count → authorized treasury transfer/Staff release → Admin actual treasury receipt → eligible Seller instruction → actual recorded payout → Seller acknowledgement`.

Verified collection alone does not make electronic funds available. COD Seller proceeds require qualified treasury receipts. Buyer confirmation or the 168-hour period is separate from Staff verification; both the eligibility and cash requirements apply. Platform shipping voucher claims are not treated as treasury funds. Shipping retention is part of existing COD money, not another cash receipt.

Relevant files/functions:

- `app/Http/Controllers/CommerceController.php::quote/checkout`; `app/Services/BuyerCommerce.php::quote/allocate`; `app/Http/Controllers/BuyerController.php::checkout` delegates to commerce when installed.
- `app/Http/Controllers/SellerController.php::confirmOrder/startPreparing/readyForPickup/confirmPickup`.
- `app/Http/Controllers/LogisticsController.php::approveRequest/assignPickupRider/receiveAtSortingCenter/assignCourier/updateStatus`; `LogisticsAccess::receivingStaff`; `HubTransferWorkflow::dispatch/confirmPickup/receive`.
- `app/Http/Controllers/RiderController.php::advance`; `app/Services/CodCollection.php::isCod/record/assertRecorded`.
- `app/Services/LogisticsCashDesk.php::submit/submitRecovery/balances/count/custody/authorizeTransfer/transfer/resolve`; `LogisticsCashDeskController` actions.
- `app/Services/LogisticsTreasury.php::qualifiedHub/transfer/release/receipt/balance/available`; `SellerPayoutController` treasury actions.
- `app/Services/CodSellerPayouts.php::eligibility/prepare/action/recheck/pay/acknowledge`; `SellerPayoutController` payout actions.

### GCash

`Buyer purchase reservation → Seller receiving instructions → private buyer proof → Seller actual-receipt verification → one order → Seller preparation/carrier selection → gross logistics shipping obligation → Seller private shipping proof → company Admin actual-receipt verification → pickup/delivery`.

Buyer GCash verification creates no logistics Seller payable. The Seller advances the full gross logistics shipping, including where platform-funded shipping vouchers create a separate unverified reimbursement claim. An approved zero charge needs no fabricated payment. A carrier/rate mismatch holds handoff without silently repricing the buyer. Expired/released payments enter manual resolution when money may have been sent.

Relevant files/functions:

- `app/Models/GcashPurchase.php`; `app/Services/GcashWorkflow.php::create/instructions/proof/decide/place/reference/releaseOrder`; `GcashPurchaseController::instructions/proof/decide/place/receipt` and the existing protected receipt route.
- `app/Services/LogisticsShipping.php::preflight/createObligation/gate/submit/review/reserveReference`; `LogisticsShippingController` configuration, seller submission/review and proof actions.
- `app/Models/Shipment.php` creation/pickup gates and protected financial basis; `app/Models/Order.php` financial basis and stock-release/cancellation logic.
- `app/Http/Controllers/CommerceController.php::returnAction` records Seller return receipt and separate Platform Admin actual-refund reference. This is distinct from failed-delivery RTS; neither path automatically executes a refund.

### Rider compensation and payroll

`Prospective rate snapshot → authoritative completed work → earned snapshot → separately verified payroll contribution → reserved payout → verified instruction → exact recorded actual transfer → recipient acknowledgement`.

PHP 25 pickup is earned at actual origin receipt after both pickup confirmations. PHP 150 belongs to the whole received transfer batch, never each parcel. PHP 40 delivery does not wait for COD remittance, but COD must have been exactly collected. Current configuration may change future snapshots; it must not rewrite an earlier entitlement. Cancelled/reassigned unearned snapshots cannot generate earnings. Paid state settles liability; acknowledgement does not debit again. Later shortage/return does not automatically deduct earned pay.

Relevant files/functions: `LogisticsFinance::settings/configure/snapshotAssignment/snapshotTransfer/event`; `RiderCompensation::completion/recognize/shipmentCompleted/batchCompleted/funding/available/prepare/action/pay/acknowledge`; `RiderPayoutController`; `AssignmentWork` operational classification is distinct from financial recognition.

### Relationships, audit and UI

The additive financial schema ties collection to unique order/shipment, remittance allocations to collection/handover, signed custody to hub/handover, shipping receipt to unique obligation, Seller payout to order/collection/destination/account and Rider payout to a unique compensation/destination/account. Restrictive FKs, source uniqueness, earned-source uniqueness and shared GCash reference reservations complement transaction locks. Treasury movements and payout ledger entries represent the same payment from different perspectives; reconciliation does not add both as debits.

`LogisticsReconciliation::snapshot/build` uses one MySQL repeatable-read snapshot and company-scoped sources. `LogisticsReconciliationController::export` rechecks company, audits access and produces bounded formula-safe private CSV. Finance evidence uses `SellerPayoutController::evidence`, shipping uses its protected proof endpoint, and buyer proof uses participant authorization. `MediaLifecycle` handles private upload rollback/reference retention.

Routes were checked in `routes/web.php`: company finance routes are inside Logistics Admin middleware; own-hub Cash Desk operations remain under logistics guards plus service Staff checks; rider/seller recipient actions remain role-scoped, CSRF-protected and throttled. UI inspected: `logistics/cash-desk.blade.php`, `logistics/seller-payouts.blade.php`, `logistics/rider-payouts.blade.php`, `logistics/reconciliation.blade.php`, `seller/shipping.blade.php`, `seller/orders.blade.php`, `rider/show.blade.php`, and shared finance fields. Rider `/profit` delegates to the current earnings workspace; Earned, Paid and Acknowledgement are separate labels. HTTP page/form-render checks ran; no fresh device/browser visual acceptance was performed in this review.

## Executed tests

### Existing regression

**372 test executions / 4,526 assertions passed; zero failures, errors or skips in this selected run.** Some classes reuse fixture parents; do not interpret the execution total as 372 distinct business scenarios.

Suites: LogisticsFinanceFoundation, CodCollection, LogisticsCashDesk, LogisticsShipping, CodSellerPayout, RiderCompensation, LogisticsReconciliation, GcashPurchase, FulfillmentWorkflow, BuyerCommerce, OperationalRecovery, LogisticsAuthorization, HubTransferWorkflow, SensitiveDocuments, RtsWorkflow and IssueInvestigation.

Coverage includes exact/forged/duplicate amounts, wrong companies/roles/recipients, revoked actors, partial/short/over counts, reservation and cash transit, vouchers, holds, cancellations, returns/disputes, private evidence, actual source earnings, failed audit rollback and page controls. This is not a full-repository run or the original manual workbook.

### Additional isolated end-to-end verification

A private disposable helper reused `LogisticsReconciliationTest` fixtures and existing application actions in two separate in-memory SQLite processes. It is not product functionality or a changed tracked test. Each process also contains the original unrelated synthetic COD fixture; the figures below explicitly select the newly created order.

| Observed result | COD | GCash |
| --- | ---: | ---: |
| Buyer final order total | PHP 1,100 | PHP 1,080, including PHP 20 platform shipping voucher |
| Final order status | Completed | Completed |
| New order COD collections | 1 | 0 |
| New order Seller logistics payouts | 1, PHP 1,000, acknowledged | 0; buyer already paid Seller |
| Logistics shipping | PHP 100 retained from qualified COD | PHP 100 separately verified receipt |
| New order pickup + final-delivery earnings | PHP 25 + PHP 40 | PHP 25 + PHP 40 |
| New order Rider actual-payment records | PHP 65 across two snapshots, both acknowledged | PHP 65 across two snapshots, both acknowledged |
| Separately funded payroll | PHP 100 contribution; PHP 35 remains | PHP 100 contribution; PHP 35 remains |
| Cash conservation residual | Zero | Zero |

The COD check starts at actual commerce checkout and uses Seller confirmation/preparation, Staff/rider handovers, delivery/collection, buyer confirmation, remittance/count, treasury receipt, payout and recipient acknowledgement. The GCash check reuses the existing full buyer-proof/Seller verification/shipping gate/pickup/delivery test, then pays and acknowledges its two newly earned Rider snapshots. Neither flow includes a transfer leg; whole-transfer PHP 150 and partial-batch denial are tested separately in the selected suite and MySQL runner.

### Fresh MySQL concurrency

The existing `cash-desk-mysql.php --run` passed **26 reported check groups**, including reservation/count/release/receipt, custody overdraft protection, shipping proof/review/reference ownership, treasury credit, Seller reservation/payment/dispute serialization, Rider source/whole-batch recognition, payroll funding/reservation/payment/holds/acknowledgement, recovery and report snapshot concurrent with payment. The existing RTS runner also passed simultaneous Seller receipt with one restock/history event.

Runners clone schema only into randomly named isolated databases, use synthetic users/private files and fake mail/cache, then remove their databases/files. They do not import source customer or payment rows. No public site or provider API was used. External transfer atomicity, true account balances and actual physical money remain unverified.

### Confirmed Seller reporting mismatch

Calling the real `SellerController::reports` on the synthetic completed COD order with the existing default 10% commission produced:

| Field | Legacy report | Actual tested settlement |
| --- | ---: | ---: |
| Revenue/base | PHP 1,100 including shipping | Collected PHP 1,100; gross shipping entitlement PHP 100 |
| Commission | PHP 110 | No commission deducted |
| “Net Profit” | PHP 990 | Seller payout PHP 1,000; no profit/cost calculation exists here |

The GCash/voucher sample also reports PHP 1,100 revenue and PHP 990 net profit while buyer payment is PHP 1,080 and the platform claim is unverified. Source evidence: `SellerController::reports`, `resources/views/seller/reports.blade.php`, `Setting::commissionFraction`. Actual payout services do not use this commission calculation. Minimal recommendation: distinguish order-value analytics from verified proceeds/claims and remove the unapproved profit/fee implication through an approved reporting correction. Do not alter correct payout amounts to match a legacy chart.

## Redundancy and recommendations

- **Keep:** collection, remittance, treasury movement, financial transaction mirror and payout records. They record different lifecycle facts and are not redundant money to sum together.
- **Keep:** buyer GCash payment versus Seller logistics shipping payment; they have different recipients and obligations. No second Seller payment is necessary for GCash.
- **Review legacy reports:** Seller commission/net-profit analytics and Platform Admin commission pages (`AdminController::commission/reports/exportSalesReport/exportCommissionReport`, `Commission` model) remain separate from current settlements. No active commission-writing call was found in the inspected checkout/delivery/payout services. Do not present these pages as the authoritative logistics cash ledger.
- **Compatibility branches:** `BuyerController::checkout` retains an older fallback while current commerce is installed. This is not a second executed checkout in the verified flow. Removing compatibility code is not necessary to fix money handling.
- **Before environment acceptance:** install the already-delivered pending schema through the normal reviewed deployment process, then verify that exact environment. Do not run fresh/reset or manufacture paid/verified data. No action was taken automatically here.
- **Operational exceptions:** a transfer sent externally but blocked by a new hold/dispute must remain an exception with its reservation; never cancel it as unsent. Generic Lost/Damaged Issues does not itself adjudicate liability or clear cash/earnings. Use deliberate dispute/hold review; broader automation needs approval.

## Artifacts and unchanged-source evidence

Private verification outputs under `storage/app/private/logistics-ui-review/`:

- `payment-verification.xml`, `payment-verification-output.json` — selected regression.
- `payment-e2e-cod.json`, `payment-e2e-gcash.json`, `payment-end-to-end-verification.php` — synthetic end-to-end checks and legacy report observations.
- `payment-verification-mysql.txt`, `payment-verification-rts-mysql.txt` — fresh isolated race results.
- `payment-verification-source-before.json`, `payment-verification-source-comparison.json` — **606 application/config/view/schema/test source files hashed, zero changed** during verification.

Only this documentation and private generated artifacts were added. No fix, new migration, financial-policy change or deployment was performed. The normal recorded-money lifecycle passed; production readiness, report consistency and real-world transfers retain the explicit qualifications above.
