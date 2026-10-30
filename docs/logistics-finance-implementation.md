# Logistics finance implementation

## Approved policy

- PHP 25 pickup, PHP 150 per hub-transfer batch/trip, PHP 40 final delivery.
- Company administrators configure rates prospectively; assignment snapshots do not change.
- Delivered COD requires an exact recorded collection, independently of later hub cash verification.
- Hub Staff receives cash only for their assigned hub; Logistics Admin reconciles and pays recipients.
- Seller payout eligibility requires verified funds and buyer confirmation or 168 hours after delivery, with dispute protection.
- Shipping vouchers have explicit funders. Shipping entitlement is validated against approved company rates.
- Platform commissions, automatic failed-delivery charges, salary deductions and payout offsets are excluded.
- Financial corrections use audited reversals; recipient acknowledgements and payout holds remain explicit.

## Phase 1: financial foundation

Implemented:

- Additive migration for company settings, compensation snapshots, and append-only application audit events.
- Admin-only Finance settings page using existing logistics middleware, navigation and styling.
- Integer centavo parsing, revision checks for stale settings, source-row locks and database uniqueness.
- Pickup/final-delivery snapshots on assignment creation or rider reassignment.
- One transfer snapshot at batch dispatch; parcel-level transfer assignments do not generate compensation.
- Unearned snapshots are cancelled on reassignment; previous snapshots remain available for audit.
- Historical delivered assignments are not backfilled or labelled paid.

This phase intentionally does not mark compensation earned or paid. It does not collect, verify, transfer, or settle money. The existing rider earnings report is not yet replaced.

## Phase 2: COD collection and delivery confirmation

Implemented:

- Additive shared financial-transaction ledger with one COD collection per order and shipment.
- Assigned delivery riders confirm the exact final order total and cash handover when marking Delivered.
- Collection, delivery status, assignment status and audit history commit together; an audit failure rolls the operation back.
- Hub cash verification remains pending; delivery does not establish remittance, seller payout, or rider payment.
- Retry keys, source-row locks, unique database constraints, authoritative amounts and current role checks prevent duplicate or forged collections.
- The remittance hub uses the assignment compensation snapshot where available, with a validated destination-hub fallback for existing assignments.
- Logistics status updates and normal model updates cannot bypass collection for a new delivery transition.
- Historical delivered orders can retain the existing completion flow without inventing financial records.
- The rider detail page includes collection fields and pending-verification status; the existing company Finance page lists recorded COD collections.
- The rider detail grid now keeps wide tables scrollable on phone screens.

Verification: 167 targeted tests passed, including COD, financial foundation, logistics authorization, hub transfers, address, existing GCash and fulfillment tests. MySQL additive migration installed locally. Browser checks cover 1440px and 390px fixture previews. These are not live cash transactions or a substitute for multi-connection concurrency verification.

## Phase 3: rider remittances and Hub Staff Cash Desk

Implemented:

- Rider Cash Remittances and one shared Cash Desk for Hub Staff / Logistics Admin, using existing role middleware and navigation.
- Collection selection by positive PHP amount, grouped by the collection's recorded hub. Partial remittances are supported. Submissions reserve their selected amounts but do not establish physical cash receipt.
- Riders can withdraw an uncounted submission; withdrawal releases its reservation without changing the collection, parcel or cash custody.
- Only approved Hub Staff assigned to the recorded operational hub may count and verify remittances. Logistics Admin cannot impersonate a cash receiver.
- Staff enters the physical count and explicit amounts allocated to each collection. Allocations cannot exceed the submitted amounts and must total the counted cash up to the expected total.
- Shortages require notes and leave the unreceived balance outstanding for a later remittance. Overages require notes, increase physical custody, and remain unallocated to orders.
- Collections independently show pending, partial or verified cash status. Delivery records and assignment compensation are not changed by remittance operations.
- Hub custody is derived from append-only receipt/release movements. Transfer authorization does not move cash; origin staff confirms exact authorized release, then destination staff records actual receipt. Cash in transit is excluded from both hubs' on-hand custody.
- Logistics Admin authorizes company-internal hub cash transfers and documents discrepancy investigations/resolutions. Transfer shortages and overages preserve the actual destination count and raise a discrepancy.
- Admin resolution records the decision and actor/time without silently changing counted cash, forgiving an outstanding balance, refunding overages or deducting earnings. Discrepancy history remains visible after resolution.
- Company-configurable cutoff defaults to 23:59 Asia/Manila, with revision protection. The collection's Manila calendar date determines its deadline; a collection recorded after that day's configured cutoff is overdue immediately. Cutoff changes recalculate the displayed flags. No automatic penalties or operational blocks.
- Unique submission keys and movement constraints, integer centavos, fresh role checks, collection/hub row locks, current locking balance reads, atomic audit writes and transaction retries protect against duplicate allocation and custody movements.
- Responsive cards, labelled 44px controls, restrained pink buttons, scrollable tables and private/no-store financial pages.

Changed files:

- `database/migrations/2026_10_29_000001_add_logistics_cash_desk.php`: additive cutoff, cash handovers, collection allocations and custody movements; restrictive foreign keys; rollback cannot erase financial history.
- `app/Services/LogisticsCashDesk.php`: permissions, submission/reservation, counting, partial verification, withdrawal, custody, transfers, resolution, cutoff and audit safeguards.
- `app/Http/Controllers/LogisticsCashDeskController.php`: scoped page data and actions.
- `routes/web.php`, `resources/views/logistics/partials/sidebar.blade.php`, `resources/views/rider/partials/sidebar.blade.php`: role-specific entry points and throttled POST actions with CSRF protection.
- `resources/views/logistics/cash-desk.blade.php`, `public/css/logistics-cash-desk.css`: shared responsive workspace and audit history.
- `tests/Feature/LogisticsCashDeskTest.php`: isolated feature/security cases.
- `tests/Support/cash-desk-mysql.php`: opt-in real multi-process MySQL concurrency checks, using a randomly named temporary database cloned from schema only and removed afterwards. Run with `php tests/Support/cash-desk-mysql.php --run`; requires permission to create/drop its isolated database.

Verification:

- Final targeted regression run: 192 tests / 2,621 assertions passed across Phase 3, COD collections, finance foundation, logistics authorization, hub transfers, address APIs, existing GCash and fulfillment workflows.
- Phase 3 contributes 23 tests / 121 assertions, covering exact/partial remittances, shortages, overages, explicit multi-collection allocation, withdrawals, forged inputs, revoked approval, wrong hubs/companies, duplicates, transfers, cutoff, overdue submission without earnings deductions, role-page rendering, form-error handling and audit failure rollback.
- The additive migration was installed on local MySQL. No live cash receipts or historical remittances were invented.
- Real MySQL multi-process checks passed for concurrent submissions, competing counts by different Hub Staff, duplicate release/receipt, and competing transfers by different staff with insufficient combined custody. Audit and cash movements occur once.
- MySQL testing exposed stale balance reads under repeatable-read isolation; current locking reads fixed the duplicate reservation, and the race now returns one success and one conflict.
- Browser fixture previews checked for Rider, Hub Staff and Admin at 1440px and 390px: no document overflow, all visible controls associated with labels, control heights at least 44px.

Limitations / Phase 4 handoff:

- Cash verification is a staff-attested physical process, not a bank/provider confirmation. Transfer evidence is a required written custody reference; no bank integration or file-upload evidence was added.
- No write-offs, overage refunds, salary deductions, payout offsets, opening cash balances or silent edits to final counts. Corrective/reversal tooling is not implemented in this phase; an incorrect finalized entry requires an audited follow-up design, never direct deletion or overwrite.
- Cash transfers currently cover authorized hub-to-hub movement inside the same company. Bank deposits, external treasury transfers and payouts are not inferred or implemented.
- Overdue flags are calculated when viewing the Cash Desk; no scheduled notifications are sent.
- Existing rider earnings and seller reports retain their legacy behavior until their planned phases. Verified COD allocations are funds evidence, not payout approval or shipping revenue.
- Phase 3 provides verified COD allocations and custody movements for subsequent phases; its cash workflow remains separate from shipping settlements.

## Phase 4: approved shipping rates and GCash shipping settlement

Additional decisions approved before coding:

- Company-owned destination-region rates use the existing first-kilogram / additional-started-kilogram formula. Strict matching against the original gross shipping charge is required for new shipment handoff; missing rates and mismatches require review, never automatic buyer repricing.
- Seller-issued shipping vouchers are seller-funded; platform-issued shipping vouchers are platform-funded. Sellers advance the full gross shipping fee for GCash shipments. Platform-funded portions are recorded separately as unverified reimbursement claims, without automatic reimbursement or a reduction in logistics entitlement.

Implemented:

- Logistics Admin publishes approved regional rate revisions and the company GCash receiving account from the existing Finance page. Revision checks prevent stale updates. Existing obligation rates and receiving instructions are immutable snapshots.
- New shipment creation atomically creates one obligation per order/shipment after checking carrier coverage, snapshot weights, destination-region rate and exact gross shipping amount. A missing or mismatched rate rolls back the handoff and leaves the order preparing.
- Existing shipments receive policy version 0 and are not backfilled, repriced or newly payment-gated. New application-created shipments use version 1; carrier, route, order, policy version and the associated order's financial basis cannot be silently changed afterward.
- Checkout still uses the existing seller regional/flat shipping calculation and selects the carrier during preparation. Before buyer commitment, if every eligible carrier has an approved regional rate and none matches, checkout reports the conflict. If carrier rates are incomplete, checkout displays a warning and retains its existing calculation; strict validation still runs at actual carrier selection. No carrier is guessed and no buyer total is changed.
- New commerce voucher snapshots explicitly include issuer funding. Identifiable older commerce shipping vouchers resolve funding from their approved issuer ownership and snapshot it into the obligation. Unidentifiable legacy vouchers or missing order weights hold new shipment handoff for review instead of inventing funding or weight.
- Sellers access Shipping Payments through their existing orders/navigation, see the snapshotted receiving instructions, and submit the exact gross amount with a transaction reference and private JPG/PNG/WebP/PDF proof, up to 5 MB.
- Only the assigned company's approved Logistics Admin can verify actual receipt. Verification requires the exact amount, matching reference and written evidence; rejection preserves the attempt and allows a new reference/proof submission.
- A verified shipping payment creates one entry in the existing financial transaction ledger. A unique obligation key prevents double settlement. Proof upload alone does not clear the pickup gate.
- Pickup approval checks the obligation in both the controller and normal shipment updates. The requests page links to shipping review and disables approval for an unpaid new GCash obligation. COD obligations do not require GCash prepayment; explicitly approved zero shipping charges do not invent a payment.
- Shared GCash reference registry reserves existing and future buyer references as well as shipping references, preventing reuse across payment purposes. Submission keys and receipt hashes also prevent duplicate shipping submissions.
- Financial operations follow existing operational locks, use current locking reads, database uniqueness and transaction retries, and write audit events atomically. Existing media lifecycle handling now cleans up rolled-back shipping proofs and protects referenced private proofs.
- Seller lists, payment submission, admin review and Finance configuration reuse the existing layouts and restrained pink styling. Proof access is participant-scoped, private/no-store, nosniff and sandboxed.

Changed files:

- `database/migrations/2026_10_29_000002_add_logistics_shipping_settlement.php`: additive shipment policy marker, account/rate revisions, obligations, payment attempts, shared references and unique settlement linkage.
- `app/Services/LogisticsShipping.php`: rate validation, preflight, obligation snapshots, funding claims, private proof submission, reference ownership, verification and pickup gating.
- `app/Http/Controllers/LogisticsShippingController.php`: seller list/detail, admin configuration/review and protected proof download.
- `app/Services/BuyerCommerce.php`, `app/Services/GcashWorkflow.php`: early conflict checks, explicit funding snapshots, legacy GCash weight/address snapshots and shared reference reservation.
- `app/Services/MediaLifecycle.php`: private shipping-proof cleanup and reference protection.
- `app/Models/Shipment.php`, `app/Models/Order.php`: atomic new obligations, historical compatibility, pickup gate and protected financial basis. The existing UUID creating callback was changed to return no value so later creation validators can run.
- `app/Http/Controllers/SellerController.php`, `app/Http/Controllers/LogisticsController.php`, `app/Http/Controllers/LogisticsFinanceController.php`: integration with existing orders, requests and Finance.
- `routes/web.php`, `resources/views/seller/partials/sidebar.blade.php`, `resources/views/seller/orders.blade.php`, `resources/views/logistics/requests.blade.php`, `resources/views/logistics/finance.blade.php`: authorized navigation and entry points.
- `resources/views/seller/shipping.blade.php`, `resources/views/logistics/shipping-show.blade.php`, `resources/views/logistics/partials/shipping-finance.blade.php`, `resources/views/logistics/partials/shipping-rate-form.blade.php`, `public/css/logistics-cash-desk.css`: consistent payment/configuration UI and responsive seller header.
- `tests/Feature/LogisticsShippingTest.php`: 23 Phase 4 feature/security/end-to-end cases.
- `tests/Support/cash-desk-mysql.php`: extended isolated MySQL runner covering both Phase 3 custody and Phase 4 shipping submission, competing admin verification and shared reference races. Requires both phases' migrations.

Verification:

- Final Phase 4 suite: 23 tests / 140 assertions passed, including a real application workflow from commerce quote and buyer GCash verification through order handoff and separate logistics shipping verification, cross-company access restrictions, cancelled-shipment protection and stale configuration conflicts.
- Targeted finance / fulfillment / GCash / logistics / media regression run: 225 tests / 2,861 assertions passed.
- After the final request-page and validation refinements, the shipping/authorization suites passed together (57 tests / 1,224 assertions), followed by the expanded final 23-case Phase 4 suite. These suite totals overlap and should not be added together.
- Broader run including commerce insights and account/profile cases: 252 of 254 tests passed before the final six Phase 4 cases were added. Two unrelated fixture issues remain: `BuyerCommerceTest::test_all_other_roles_enforce_profile_and_password_rules` calls the address API without a controlled geography fixture; `CommerceInsightsTest::test_order_investigation_requires_admin_and_view_escapes_listing_text` renders an admin layout without a notifications table. These tests do not install Phase 4's schema. No product behavior was weakened to suppress them.
- Additive migration installed on local MySQL. Real multi-process MySQL checks passed: one shipping submission wins, competing Logistics Admin reviews create one settlement, and competing GCash reference reservations have one owner. Phase 3 custody races still pass. The runner removes its isolated database/storage and does not create live financial transactions.
- Four browser fixture views checked at 1440px and 390px: seller submission, seller list, admin review and Finance. No document overflow; visible form fields have associated labels and are at least 44px high. Seller header clipping found during visual inspection was corrected.
- The large combined PHPUnit run uses `php -d memory_limit=512M`; the default 128 MB CLI limit was exhausted when bootstrapping the expanded suite. Application configuration was not changed.

Limitations / Phase 5 handoff:

- Company administrators must publish their actual approved rates and authorized GCash receiving account before new GCash handoffs can proceed. No rates, accounts, paid flags or historical obligations were fabricated.
- Carrier-specific prepayment validation remains inherently limited because this checkout chooses the carrier after buyer payment. The existing checkout architecture and buyer totals are preserved.
- Receiving-account snapshots are not retargeted when an account changes. Cancelled shipments with money already sent, incorrect finalized records, ambiguous old voucher/weight data and reused rejected references require financial exception review; no automated refund, write-off, reference release or corrective reversal UI was added.
- Payment verification is manual confirmation against the company's actual GCash records, not a GCash provider API integration. Proofs and written verification evidence are retained privately.
- Platform claims remain unverified and unpaid. COD shipping obligations remain unsettled until reconciliation/settlement phases; they are not marked paid by parcel delivery or a GCash proof.
- Phase 4 provides approved shipping snapshots and verified shipping receipts for settlement/reconciliation. It does not independently approve seller payouts.

## Phase 5: COD seller settlements and payouts

Additional rules approved before implementation:

- Require the full COD collection to be Hub Staff verified before preparing a seller payout. Cash-only seller proceeds equal the recorded buyer COD total minus the snapshotted gross shipping entitlement. Insufficient cash to cover shipping is held for review, never converted into a negative payout.
- Hub Staff confirms release to an authorized company treasury account. Logistics Admin confirms actual treasury receipt/deposit with private evidence before electronic seller payouts can use those funds. Cash still at hubs, in transit, unallocated overages and unverified voucher claims cannot fund payouts.
- Positive adjustments require separately verified company contributions and documented approval. No negative adjustments, deductions, offsets or commissions. The same Logistics Admin may prepare, verify and record payment, with each action audited.

Implemented:

- Additive company treasury accounts, seller-owned receiving-account revisions, signed treasury movements, seller payout records, private evidence and linkage to the existing transaction ledger. Accounts are append-only so existing instructions are preserved.
- Hub-to-treasury authorizations reuse the existing cash handover records and Cash Desk. Assigned Hub Staff confirms exact authorized release using available verified COD cash; company receipt is a separate Admin action with a reference, private proof and actual-receipt confirmation.
- Separate treasury buckets for COD funds, positive-adjustment contributions and unallocated overages. Hub cash decreases only at release; treasury funds increase only at confirmed receipt. Shortages credit only actual received funds; overages remain visible but unavailable for payouts.
- Existing hub-to-hub transfers can explicitly record their verified COD portion at dispatch and actual receipt. Those amounts cannot exceed qualified source cash, the released COD portion or actual counted cash. This preserves funding provenance through hubs without treating unverified cash as COD funds.
- Historical hub transfers with no verified COD classification are not retroactively treated as qualified receipts. Unknown outgoing legacy cash is conservatively accounted for when deriving qualified source custody; it requires financial review rather than a fabricated opening balance.
- Eligibility checks exact recorded COD versus authoritative verified allocations, a company-matched approved shipping snapshot, delivered/completed parcel/order state, and a reliable delivery timestamp.
- Eligibility uses explicit buyer confirmation or 168 hours from delivery. Buyer confirmation now records a dedicated timestamp; existing buyer-authored completion history can also supply confirmation. Automatic completion alone is not treated as buyer confirmation. The existing parcel completion workflow is preserved.
- Active order complaints block payouts. Pending/approved/escalated/received/refunded returns block them too; rejected returns remain protected during the existing three-day appeal window. Refunded orders require financial exception review instead of an automatic payout reduction.
- Admin prepares one payout per order/collection and reserves the exact product proceeds plus any approved, separately funded positive addition. A second order cannot reserve the same treasury balance. Seller receiving accounts must belong to the actual seller and cannot be silently replaced by Admin input.
- Verify, hold, release-hold, cancel-unsent and record-actual-payment actions use revision checks and audit history. Eligibility, disputes, holds and funds are rechecked before verification and actual-payment recording. Cancelling an unsent instruction releases reservations; paid records cannot be cancelled or overwritten.
- Actual payout requires the exact total, transaction reference, private payment evidence and explicit sent confirmation. Treasury debits, financial ledger entry, shipping retention, paid status and audit event commit together. COD shipping is marked `cod_retained` on actual payout without a second cash collection or change to delivery status.
- Seller acknowledgement is independently recorded, recipient-scoped and retry-safe. It does not create a payment or debit funds again.
- Complaint writes linked to an order now take the shared order lock, preventing an already-committed dispute from being missed during concurrent payout recording. Existing return writes already use that order lock.
- Shared references protect GCash across payment purposes; bank references are namespaced by institution. Receipt hashes and unique source keys prevent duplicate evidence, treasury credits and payout debits. Media lifecycle cleanup includes private finance evidence and removes rolled-back uploads.
- Admin: Finance > Seller Settlements, containing eligibility, payout history, treasury accounts, receipts and separately funded additions. Seller: Orders > COD Payouts, containing receiving accounts, payout records, private payment proof and acknowledgement. Hub Staff remains in Operations > Cash Desk and cannot verify treasury receipt or seller payouts.
- Responsive cards, section links, clearly separated payout stages, explicit automatic/manual holds, labelled controls, private evidence links and read-only deposit/contribution history.

Changed files:

- `database/migrations/2026_10_29_000003_add_cod_seller_payouts.php`: additive buyer confirmation timestamp, treasury accounts/movements, payout destinations, payout reservations/payment records, evidence and restricted foreign-key links.
- `app/Services/LogisticsTreasury.php`: company/recipient accounts, qualified COD custody, hub-to-treasury release/receipt, contribution verification, evidence, references and available balances.
- `app/Services/CodSellerPayouts.php`: eligibility, reservations, review, holds/cancellation, actual payout and acknowledgement.
- `app/Http/Controllers/SellerPayoutController.php`: role-scoped pages/actions and protected evidence delivery.
- `app/Services/LogisticsCashDesk.php`, `app/Http/Controllers/LogisticsCashDeskController.php`, `resources/views/logistics/cash-desk.blade.php`: treasury handovers, transit totals and verified COD transfer classification.
- `app/Http/Controllers/BuyerController.php`, `app/Models/Complaint.php`: explicit confirmation and serialized order-dispute writes.
- `app/Services/MediaLifecycle.php`: transactional private finance-evidence cleanup/reference protection.
- `routes/web.php`, `resources/views/logistics/partials/sidebar.blade.php`, `resources/views/seller/partials/sidebar.blade.php`: throttled, CSRF-protected actions and correct sidebar hierarchy.
- `resources/views/logistics/seller-payouts.blade.php`, `resources/views/logistics/partials/finance-account-fields.blade.php`, `resources/views/logistics/partials/finance-receipt-fields.blade.php`, `public/css/logistics-cash-desk.css`: shared account/payment forms, role-aware workflow UI and responsive styling.
- `tests/Feature/CodSellerPayoutTest.php`: 23 Phase 5 financial/security/UI cases.
- `tests/Support/cash-desk-mysql.php`: extended real MySQL multi-process tests through Phase 5.
- `tests/Feature/GcashPurchaseTest.php`, `tests/Feature/LogisticsAuthorizationTest.php`: update the existing navigation assertion and isolate throttling only in the exhaustive role-enumeration tests. Production authorization and rate limits are unchanged.

Verification:

- Targeted financial, fulfillment, GCash, logistics, address and media suites: 250 tests / 3,098 assertions passed.
- Final Phase 5 suite after form-rendering checks: 23 tests / 151 assertions passed. These overlap with the regression total; do not add them together.
- Cases include full/partial remittances, explicit confirmation and the exact 168-hour boundary, dispute/return/refund/appeal holds, lack of treasury funds, overages/shortages, qualified inter-hub cash, separately funded positive additions, negative/unsupported adjustments, reservations, duplicate payments, wrong recipients/roles, private evidence, acknowledgement and rollback on receipt/payout audit failure.
- Additive migration installed on local MySQL. No actual company account, opening balance, historical paid flag, treasury credit or live payout was invented.
- Real MySQL races passed for competing treasury receipts, two orders competing for insufficient funds, competing payout verification/payment, acknowledgement retries and payout versus new dispute. Existing COD remittance and shipping-payment races also passed. The runner removes its temporary database and private test files afterward.
- Five fixture views checked at 1440px and 390px, with collapsed sections expanded: Hub Staff release, Admin treasury receipt, verified payout, paid payout and seller acknowledgement. No document overflow, no missing field labels, correct selected sidebar group, and visible text/file/select controls at least 44px high. Checkbox hit areas use their labelled 44px rows.
- Visual inspection caught adjacent Blade directives preventing account partials from rendering. The markup was corrected, actual account/proof fields were checked in Chromium, and feature assertions now check those forms rather than page headings alone.
- The two broader account/address and insights fixture issues recorded in Phase 4 were not changed as part of Phase 5. This is not a claim that every repository test passes.

Limitations / Phase 6 handoff:

- This is manual bank/GCash transfer recording and reconciliation, not a provider integration or an app-triggered transfer. Admin must confirm actual bank receipt/payment and retain evidence. External transfers cannot be made atomic with application database transactions.
- If money was externally sent but a dispute/hold appeared before recording, the reserved instruction stays blocked for financial exception review. Do not cancel it as unsent or infer a refund. Automatic refunds, clawbacks, write-offs and corrective reversals remain unimplemented.
- No unverified or historical funds are backfilled. Old orders lacking a validated shipping obligation, reliable delivery evidence, identifiable COD funding or qualified legacy transfer history require financial review.
- Payouts are one order and one actual transfer at a time; no batch payment, partial seller payout, silent deduction, commission or offset is introduced.
- Treasury accounts have no fabricated opening balance. COD proceeds can use only confirmed qualified COD receipts; adjustments use separately recorded verified contributions. Unallocated overages cannot be reclassified or paid through this workflow.
- Platform voucher claims remain separate, unverified and unpaid. Refunded/returned cases require reconciliation rather than an assumed remaining payable amount.
- Seller acknowledgement is an authenticated recipient statement, not provider confirmation. Paid records remain paid while acknowledgement is pending.
- Ready for Phase 6 using the existing immutable compensation snapshots and this treasury/custody foundation. At the Phase 5 boundary, rider earnings and payouts remained legacy behavior. Phase 6 implementation is recorded below.

## Phase 6: rider compensation, earnings and payouts

Additional rules approved before coding:

- Rider payouts use a separate `rider_payroll` treasury bucket funded by actual, documented and verified company contributions, with reference and private proof. COD seller funds, seller adjustments, shipping receipts and overages cannot fund rider payouts. No implicit transfer of shipping revenue into payroll.
- Completed assignments predating Phase 6 may be recognized only from an existing immutable snapshot with matching authoritative completion evidence and rider identity. Ambiguous/missing records stay excluded for review. Never backfill current rates or historical paid flags.

Implemented:

- Existing compensation snapshots remain the earning basis: company-configured prospective pickup/delivery rates and one transfer rate per dispatched batch. Recognition does not create or reprice snapshots.
- Pickup recognition requires the assigned pickup rider, both seller/rider handover confirmations, pickup timestamp and actual origin-hub receipt timestamp. The existing assignment can remain `accepted`; the financial record independently records authoritative completion at hub receipt.
- Final delivery requires the matching assignment in `delivered`/`completed`, both assignment and shipment delivery evidence, and exact recorded COD collection where applicable. Hub cash verification is not an earnings prerequisite. Rider delivery and Admin delivery paths trigger recognition within their existing operational transaction.
- Transfer recognition requires the non-legacy batch to be completed by receipt, every member received, and each matching leg completed with rider pickup confirmation. Partial receipt does not earn; per-parcel hub-transfer assignments cannot generate compensation. One unique earned source key protects the whole batch/source across rider snapshots.
- Automatic earning recognition is atomic with the existing completion flow and audit event. Explicit Admin review handles eligible earlier snapshots; GET pages do not recognize or invent earnings. Cancelled/reassigned snapshots and incomplete/mismatched sources cannot earn.
- Admin prepares one full payout per positive earned snapshot, using an authorized company treasury account and a rider-provided immutable receiving-account record. Preparation reserves payroll funds; verification, documented hold/release and cancel-unsent actions use revision checks and audit history. Cancel-unsent releases the reservation without removing the earning.
- Actual payment requires the exact snapshot amount, explicit sent confirmation, valid reference and private JPG/PNG/WebP/PDF proof up to 5 MB. Payroll debit, unique shared ledger entry, paid state and audit event commit together. Holds, state, snapshot identity and available verified payroll funds are rechecked under locks. No deductions, penalties, offsets or salary corrections.
- Rider acknowledgement is authenticated, recipient-scoped and retry-safe; it does not create another debit. Earned and paid totals remain separate; unpaid includes held or reserved earnings.
- Existing `/rider/profit` now serves Earnings & Payouts based on compensation, replacing the full-shipping-fee income calculation. Its sidebar entry is Earnings & History > Earnings & Payouts. Admin uses Finance > Rider Earnings & Payouts. Hub Staff operations and Cash Desk permissions are unchanged.
- Shared private evidence/media lifecycle and reference registry are reused. Cross-provider rider payouts reserve both funding and receiving-provider reference namespaces; GCash remains globally protected. Evidence is company/recipient scoped and explicitly private/no-store. Browser verification found that file responses needed `setPrivate()` to retain the intended private cache directive; the shared evidence endpoint now applies it to seller evidence too.
- Responsive finance cards, earnings summaries, horizontally scrollable assignment tables, expandable forms, labelled 44px controls, pink actions and documented audit/payment history reuse the existing design. Duplicate layout/page success messages were removed from the new page.

Changed files:

- `database/migrations/2026_10_29_000004_add_rider_compensation_payouts.php`: additive earned-source uniqueness, append-only rider destinations, one payout per compensation, restricted foreign keys and unique shared-ledger linkage; destructive rollback is rejected.
- `app/Services/RiderCompensation.php`: completion evidence, earning recognition/triggers, company payroll funding, reservations, verification/holds, exact payment and acknowledgement.
- `app/Http/Controllers/RiderPayoutController.php`: scoped Admin/rider workspace and throttled financial actions.
- `app/Http/Controllers/RiderController.php`: delivery recognition and replacement of the legacy Profit calculation while retaining its URL.
- `app/Http/Controllers/LogisticsController.php`: pickup hub-receipt and authorized Admin delivery recognition; Admin delivery synchronizes the matching active delivery assignment's successful delivery evidence.
- `app/Services/HubTransferWorkflow.php`: recognition only when the full batch becomes completed.
- `app/Http/Controllers/SellerPayoutController.php`: private evidence access for the actual rider recipient, plus explicit private cache handling for the shared file response.
- `routes/web.php`, `resources/views/logistics/partials/sidebar.blade.php`, `resources/views/rider/partials/sidebar.blade.php`: role middleware, CSRF/throttling and correct navigation hierarchy.
- `resources/views/logistics/rider-payouts.blade.php`, `resources/views/rider/profit.blade.php`, `public/css/logistics-cash-desk.css`: shared role-aware earnings/payroll UI and replacement of the obsolete shipping-income view.
- `tests/Feature/RiderCompensationTest.php`: earning, funding, payout, security, evidence, UI and rollback cases.
- `tests/Support/cash-desk-mysql.php`: isolated real multi-process Phase 6 races alongside existing Phase 3-5 checks.

Verification:

- Targeted finance/fulfillment/GCash/logistics/address/media regression run: 271 tests / 3,305 assertions passed.
- Final Phase 6 suite: 23 tests / 159 assertions passed, including cross-provider GCash reference protection and atomic delivery/collection/earning rollback cases added after the regression run. Counts overlap; do not add them together.
- Cases include live pickup hub receipt and rollback, exact delivery snapshot earnings with COD still unremitted, existing-rate historical recognition, incomplete/cancelled/wrong-rider exclusion, full versus partial two-parcel batch receipt, funded payroll, exclusion of COD/seller-adjustment funds, exact payout amounts, holds/releases/cancellation, duplicate/stale submissions, reference/evidence/key reuse, wrong company/recipient/role and revoked approval, private proof delivery, acknowledgement, and earning/funding/payment audit failure rollback with file cleanup.
- Additive migration installed on local MySQL. No live funds, destination/account, earning, historical paid record or actual payout was fabricated or backfilled.
- Real MySQL multi-process checks passed for competing assignment recognition, competing two-parcel batch recognition (one PHP 150 trip entitlement), duplicate payroll funding, two earnings competing for insufficient payroll funds, payout verification/payment races, acknowledgement retries, and concurrent hold/payment. Existing Phase 3-5 races also passed. Only randomly named isolated schema-only test databases and private fixture files are created and removed by the runner.
- Browser fixture checks cover verified Admin payment, rider acknowledgement and paid Admin history at 1440px and 390px. No document overflow, no unlabelled visible fields, controls at least 44px and correct active sidebar/expanded parent groups. Light-theme checks passed too; desktop dark and mobile light previews were visually inspected. Tables scroll within their cards on small screens.
- The known broader address/insights fixture issues from Phase 4 remain outside this phase. These targeted results are not a claim that every repository test passes.

Limitations / Phase 7 handoff:

- Payments and funding are manual recording of real bank/GCash transfers, not provider integration or app-triggered disbursement. The Admin must verify the actual external transfer and preserve evidence. An externally sent transfer cannot be atomic with the database; if recording is blocked, retain the reservation for exception review and never cancel it as unsent.
- One snapshot is paid in one full transfer; no batch payout, partial rider payment, write-off, automatic refund/reversal or payroll deduction is added. Zero-rate snapshots may be earned as zero but do not generate a payment.
- A later parcel return/dispute or COD shortage does not automatically erase a completed assignment earning or deduct compensation. Incorrect finalized records require an audited correction design; do not edit financial tables directly.
- Earlier work without a usable original snapshot or reliable matching completion evidence remains excluded. Pending snapshots are reviewed individually through Admin's completion-evidence action; this phase does not run a production-wide backfill or invent missing rates.
- Company contributions are staff-attested verified funding, not a bank feed. Existing shipping receipts/revenue and seller funds are not silently moved into payroll. Any future revenue-to-payroll transfer needs its own approved policy and reconciliation.
- Phase 6 provides separate collection, custody, seller settlement and rider payroll records for Phase 7, documented below.

## Phase 7: reconciliation, reporting and end-to-end verification

Implemented:

- Company-scoped Finance > Reconciliation & Reports, using the existing Logistics Admin authorization. Hub Staff continues its assigned-hub Cash Desk.
- One consistent read-only snapshot for collections/allocations, physical custody, qualified COD, transfers/transit, treasury buckets and reservations, verified shipping receipts, gross entitlement/vouchers, seller exposure/payouts, rider expense/liability and payroll.
- Counted-cash conservation checks and explicit manual review for missing/mismatched evidence, negative/over-reserved balances, legacy records, returns/disputes/cancellations, discrepancies and unverified platform claims. No automatic financial repair or backfill.
- Date-filtered activity, source histories and audit records; current balances are explicitly all-history. Private, formula-safe, audited CSV exports exclude account numbers and proof paths and are capped at 20,000 rows.
- Seller cross-provider reference protection aligned with rider payments; recipient GCash references cannot reuse buyer payment references. No fee or role policy changed.
- Controlled geography and missing-notifications fixture fixes, plus current navigation expectations. No production validation was weakened.

Verification:

- Targeted regression: 343 tests / 4,332 assertions passed. Final Phase 7 suite: 19 tests / 227 assertions passed. Totals overlap.
- Broader repository run: 509 tests, 458 passed, 22 failures, 2 errors, 27 skipped. Remaining failures are in the older HubHiringTest/RiderWorkbookTest suites; full names and causes are in the review report. This run preceded the last two report-specific cases covered by final targeted checks.
- Real MySQL Phase 3-7 races passed, including a report held open while a payout commits on another connection: one coherent pre-payment view followed by a coherent Paid view, without blocking the write.
- Complete COD and buyer-GCash/shipping/delivery workflows, seller/platform vouchers, partial/short/over remittances, returns/disputes/cancellations, holds, duplicate payments, multi-account balances, exports and historical schema handling passed. Browser fixtures checked at 1440px/390px with correct Finance hierarchy, labels, 44px controls and no document overflow.
- Read-only local review: 5 legacy shipments, no financial transactions/handovers/treasury movements/payouts. Three existing approved company Admins were reviewable; four legacy shipments fall outside their company scope and need authorized ownership review. No history was fabricated.

The full changed-file list, reconciliation basis, findings, exact failing tests, security concerns and limits are in [Phase 7 financial review](phase7-financial-review.md). No new schema was required. GCash receiving-provider balances, external bank transfers, accounting recognition/closing policies and corrective reversals remain outside these recorded-source reports. The separate Role Security Review has not started.

## UI and navigation follow-up

- Dashboard / My Hub remains first in each role's sidebar.
- Logistics Admin: Finance > Overview & Settings (shipping payments, approved rates and compensation settings), Finance > Seller Settlements (COD payouts and treasury), Finance > Rider Earnings & Payouts (assignment compensation and separately funded payroll), Finance > Reconciliation & Reports (read-only company review/export), and Finance > Cash Desk (company-wide cash oversight).
- Hub Staff: Operations > Cash Desk, with no Finance settings or shipping-verification link.
- Seller: Orders > Buyer GCash Payments, Shipping Payments and COD Payouts, separating incoming buyer payments, outgoing carrier payments and logistics payouts.
- Rider: Earnings & History > Earnings & Payouts and Cash Remittances, keeping earnings separate from collected COD cash.
- Shipping detail pages link back to their role's payment list / Finance overview. Active links highlight correctly and their groups open on both list and detail routes.
- Existing role-page render checks passed: 2 tests / 23 assertions. Browser checks of seven fixture views at 1440px and 390px confirmed the expected parent groups, Dashboard/My Hub ordering, no document overflow, associated field labels and at least 44px form controls. Permissions and financial logic were not changed by this navigation update.

## Follow-up reviews

Phases 1-7 are implemented with the verification boundaries and remaining issues documented above. The separately requested Logistics Admin, Hub Staff and Finance Role Security Review has not started.

Existing checkout selects the logistics company during seller preparation, after buyer payment. Carrier-specific validation before payment must not guess a carrier or silently change an agreed total. Implement the earliest authoritative checks available in phase 4 and surface unresolved mismatches explicitly.

## Verification boundary

Feature tests use isolated SQLite fixtures. MySQL migration installation, browser fixture rendering and real MySQL multi-process Phase 3-7 race checks are separate checks. These races cover cash reservations/custody, shipping submissions/verification, reference ownership, treasury receipts, seller payout reservations/payment and dispute serialization, rider earning recognition (including a two-parcel batch), payroll funding/reservations, rider verification/payment, acknowledgements and competing holds. They also cover coherent reconciliation reads during payment commits. They are not a production load test, provider/bank-statement verification or statutory financial audit. Remaining suite failures and the pending Role Security Review are documented in the Phase 7 report.

## Settlement UI refinement

- Refined Seller Settlements and COD Payouts with section navigation, prominent payout amounts, separate status/acknowledgement labels, structured financial breakdowns, receiving-account rows and clearer expandable forms. Pink actions and existing financial behavior remain unchanged.
- Confirmed Finance > Seller Settlements and Orders > COD Payouts active navigation and expanded parent groups.
- Verification: 23 payout tests / 151 assertions passed. Five isolated fixture views at 1440px and 390px have no horizontal overflow, no unlabelled controls, and minimum 44px form controls. Mobile seller preview visually inspected. These checks do not represent live financial transactions.
