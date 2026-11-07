# PocketFinds seller commission implementation

Production-readiness remediation is documented in [commission-remediation.md](commission-remediation.md). The assessed code/test failures are resolved; enrollment remains disabled and no historical migration/conversion was applied by that remediation.

Final workflow fixes: rejected-reference recovery, independent already-sent Seller proof submission, audited residual COD settlement after recorded product refunds, and filtered payment/credit CSVs are implemented. Additive migration `2026_11_08_000002_complete_commission_workflows` is installed as batch 111; its seven new tables are empty. Collection remains disabled. See [the repeated workflow review](commission-workflow-review.md) for accounting details, final validation, preservation evidence and remaining baseline release blockers. Earlier sections below describe the original installation and its historical validation state.

Final workflow review: collection must remain disabled. Three completion gaps were reproduced in Seller receipt submission/reference recovery and refunded COD settlement. See [the end-to-end review](commission-workflow-review.md) for findings, current evidence and the limits of hosted-site verification. No production financial logic or historical transactions were changed during that review.

7 November 2026. Commission enrollment is disabled by default. This change does not authorize activation or retroactive fees. No receiving account, real payment, commission obligation or payout is invented by the migration.

## Policy and historical protection

The immutable policy identifier is `seller-net-products-v1`, at 1,000 basis points (10%). The basis is actual snapshotted item selling price multiplied by quantity, less seller-funded merchandise voucher discounts. Sale/bundle prices already reflected in item prices are not discounted twice. Shipping and shipping vouchers never enter the basis. Platform-funded merchandise discounts do not reduce it. Unknown merchandise voucher funding rejects a new enrolled snapshot instead of guessing.

All amounts use integer centavos. The total fee rounds half up; discounts and fees are allocated across original item lines by stable largest remainder. Large proportional allocations use exact integer arithmetic without an overflowing multiplication or floating point. Each line preserves its original item snapshot and product reference. Partial refunds currently follow the existing return workflow's selected whole item lines, including their quantities; this does not introduce a new partial-quantity return engine.

`COMMISSION_ENABLED=false` and an absent `COMMISSION_ACTIVATION_AT` prevent enrollment. Both an explicit enabled flag and an effective activation timestamp are required. Order creation captures new COD obligations; GCash purchase creation captures prepaid obligations before money is sent. Later order placement only links the existing purchase obligation. Historical purchases do not become chargeable merely because they are placed after activation. There is no backfill command. Disabling enrollment does not disable servicing existing obligations.

The old `settings.commission_rate` and legacy `commissions` rows remain intact and clearly labelled as historical. They do not control the fixed policy or prove actual fee collection. Application-level financial snapshot edits are refused after enrollment.

## Ledger and lifecycle

The additive migration is `2026_11_08_000001_add_platform_commission_ledger.php`. It creates eight tables:

- `commission_obligations`: immutable source, rate, policy, effective timestamp, seller, collection method and financial totals; earning, reversal and withholding state.
- `commission_lines`: original item identity, quantity, selling value, allocated seller discount, basis and fee.
- `commission_events`: append-only snapshot, earning, review, payment, reversal, credit and reminder evidence with unique event keys.
- `commission_accounts`: append-only revisions of the platform GCash receiving instructions.
- `commission_payments`: seller receipts and reserved company remittance batches, evidence, revisions, verification and unapplied amounts.
- `commission_allocations`: requested reservations and actual verified allocations to obligations.
- `commission_cod_settlements`: exact deductions attached to existing seller payout instructions.
- `commission_credits`: seller commission repayments, responsible payer, reason, evidence and frozen receiving destination.

Foreign keys restrict destructive deletion; unique source, payout, submission, reference and event identifiers protect retries. Destructive rollback is refused. Existing business tables and their historical rows are not rewritten.

Provisional charges do not create collectible debt. Earning requires delivered/completed orders, a reliable delivery timestamp, buyer confirmation or the existing 168-hour period, verified buyer GCash where applicable, and no unresolved dispute/return restriction. `commissions:reconcile` runs every five minutes, also reconciling bulk-written cancellations and existing recorded refunds. It never enrolls old transactions. GCash due dates are seven days after earning. Overdue in-app reminders are idempotent once per day; no automatic seller suspension was introduced.

## COD settlement and remittance

Existing COD eligibility, full cash verification, company/hub scope, treasury receipt, manual holds, payout review, actual payment proof and acknowledgement remain in force.

Existing `product_cents` remains the gross product-proceeds reservation. For an enrolled payout:

`actual Seller transfer = product_cents + separately funded adjustment - commission`

The gross product proceeds remain reserved until actual payout. Payment debits the gross COD proceeds and transfers the withheld commission into the protected `commission` treasury bucket. The payment transaction mirror records only the actual net Seller transfer. The commission ledger records withholding, not collection. Shipping remains separate. New commissioned COD payouts with platform discounts require the corresponding independently funded positive adjustment, rather than silently making the Seller fund the platform discount.

Logistics prepares a company-scoped remittance before sending money. A draft reserves selected obligations from one treasury account without moving money. An unsent draft may be cancelled with attestation and a reason. Recording the actual external transfer requires the exact amount, reference and proof, moving funds from `commission` to `comm_transit`. Only Platform Admin verification records commission collected and clears transit. A receipt dispute retains transit and its reservations; it never restores money that was actually sent. Logistics cannot bypass the reserved batch through the seller payment endpoint.

Company reconciliation checks commission and transit buckets against withholding, remittances and credit repayments. Existing cash-custody conservation and payroll funding rules remain intact.

Mutation paths lock source orders before commission obligations and payment records. Allocation, reservation, protected-balance and refund-credit decisions use current locking reads, avoiding an older InnoDB repeatable-read snapshot after waiting for another transaction. A dedicated MySQL race verifies two different receipts after a partial reversal; combined allocations cannot exceed the remaining commission and excess actual receipts remain Seller credit.

## Seller GCash commission payments

Buyer GCash continues to go directly to the Seller. Its verification does not collect platform commission and is not changed by this feature. Sellers send commission separately to a published platform GCash account, submit private proof/reference and select their obligations. Actual transfer claims remain recorded if another payment or refund changed the balance; only the remaining unreserved debt is reserved. Distinct actual excess transfers become verified unapplied Seller credit rather than duplicated commission.

Platform Admin may flag a receipt for review, reject an unverified seller receipt, or verify its exact received amount/reference with explicit receipt attestation. Rejected GCash submissions release reservations; receipt history remains. A later verified actual receipt can only allocate up to the remaining debt. Verified unapplied credit may be allocated to another eligible obligation of the same Seller, or actually repaid using that Seller's recorded GCash destination. Company funds and Seller credit are never mixed.

## Refunds and cancellations

Order cancellation reverses the original line allocations once. Existing recorded product refunds reverse only selected lines. Shipping-only changes do not reverse product commission. A second request or a full refund following a partial refund cannot reverse a line twice.

- Before collection, reversal reduces the debt.
- After GCash collection, excess collected commission becomes a platform-funded Seller credit.
- After COD withholding but before remittance, the excess becomes a Logistics-funded Seller credit.
- If the COD remittance is already in transit, credit repayment waits for receipt reconciliation. Platform receipt verification transfers the relevant credit responsibility from Logistics to Platform Admin, with audit evidence.
- Credit repayments require the exact amount, actual-send attestation, unique reference, private proof and a matching recorded Seller GCash destination. Buyer product refunds remain separate transactions.

Existing COD return/refund payout holds are preserved. This feature does not bypass a legacy funding exception or automatically pay residual COD proceeds after a refund. Platform discount reimbursement is not fabricated; the pre-existing unverified shipping-claim path remains separate. Direct GCash platform subsidies still require separate real reimbursement outside commission collection.

## Workspaces, reports and authorization

`/commissions` serves role-scoped Seller, Logistics Admin and Platform Admin workspaces. Seller statements distinguish provisional charges, due/held debt, COD withholding, verified allocations, unapplied credit and repayment history. Logistics sees only its COD remittances and related credits. Platform Admin publishes receiving instruction revisions, verifies receipts, allocates credits and records platform credit repayments.

Statements have stable pagination (25 obligations, separate 20-row payment and credit histories), seller/method/date/outstanding/overdue filters, line calculations and activity details. Private CSV statement/payment/credit exports protect spreadsheet formulas and enforce a 10,000-row limit. New writes use CSRF, throttling, fresh role/ownership checks, row locks, transaction rollback, confirmation dialogs and per-form busy protection. Evidence is private with restrictive response headers. Mobile tables scroll within their own region.

Reporting separates net earned commission, reversals, due from Sellers, due from Logistics, held balances, actual platform receipts, unapplied credit and repayments. Seller payout reports subtract the linked deduction. Withholding and shipping receipts are not platform collections. The existing revenue reports are not converted into a complete profit-and-loss system; operating expenses, taxes and payment-provider accounting remain outside this feature.

## Files

New implementation: `config/commission.php`; the migration above; `CommissionLedger`, `CommissionPayments`, `CommissionReporting`; `CommissionController`; `resources/views/commissions/*`; `public/css/commissions.css`; `public/js/commissions.js`.

Integration: `Order`, `GcashPurchase`, `CodSellerPayouts`, `PaymentReporting`, `LogisticsReconciliation`, `SellerPayoutController`, the recorded-refund handler in `CommerceController`, web/console routes, Seller/Admin/Logistics navigation, payout/report views and the legacy settings/commission explanation.

An existing null OTP-lock formatting error in `RiderController` was fixed so ordinary delivery confirmation works with an unlocked OTP. Test fixtures were brought up to the existing delivery OTP/proof requirements, use explicit fixture compensation rates and the configured timestamp timezone. Production rider compensation rates were not changed.

Validation sources: `tests/Feature/CommissionTest.php`; two active commission checkout cases in `BuyerCommerceTest`; existing COD/cash-desk/salary/attendance suites; `tests/Support/commission-mysql.php`; and `tests/browser/commission-workspace-check.mjs`.

## Backup, rollout and release gate

Before implementation a private table/data backup was restored and every value checked (112 tables / 1,026 rows). A second complete `mysqldump --single-transaction --routines --events --triggers --hex-blob` backup was restored and verified (112 tables / 989 rows). The only intervening difference was expiration/removal of 37 runtime sessions; all other table hashes matched. The complete backup is in `storage/app/private/database-backups/commission-complete-20261107-072339`, with its SHA-256 and verification manifest. No credentials remain in the temporary MySQL client configuration.

The migration must be applied by its exact path; unrelated pending migrations must not be run as part of this feature. Enrollment stays disabled. After a fully green release gate and authorized business acceptance, activation requires an explicit effective timestamp with timezone, a verified platform receiving account, trained reviewers and a running scheduler. Deploy source, views and assets together. Keep the immutable 10% policy in existing obligations when enrollment is subsequently disabled.

Private validation artifacts are under `storage/app/private/commission-*.xml/json/txt` and `storage/app/private/commission-ui`. Synthetic MySQL databases copy schema only and are dropped after their checks. Browser previews use synthetic HTML through a static server that refuses writes and never connects to Laravel or a database.

The wider regression selection has pre-existing failures, reproduced using an isolated class map of the original application classes without replacing the working source. They include old UI/navigation expectations, pickup/assignment fixtures and unrelated personnel/review errors. No whole-repository-green or live-activation claim is made. Exact final results and migration installation status are recorded in the validation addendum below.

## Installation status

Applied only `2026_11_08_000001_add_platform_commission_ledger` to the configured `pocketfinds` database, batch 110. All eight new tables remain empty. Post-install verification matched every pre-existing table and all 989 original rows from the complete backup, excluding only the newly added migration-history entry. No runtime differences were present in that comparison. Other pending migrations were not applied.

`commission.enabled` is false and `commission.activation_at` is null. No live commission policy was activated and no receiving account or real financial action was submitted. Live activation remains blocked by the wider baseline regression failures.

## Validation addendum

The broader selection ran 271 tests: 242 passed, with 27 failures and two errors. All 29 failing test names reproduced with the original application classes. The baseline additionally encountered one fixture filesystem cleanup error; the final core suites run sequentially to avoid shared fake-disk interference. `commission-baseline-comparison.json` records no failures unique to the commission implementation in that selection.

The core selection covers commission, existing COD seller payouts, cash desk, payroll workspace and salary/attendance. Two additional tests exercise actual COD and GCash commerce checkout with active commission, vouchers and retries. The final counts are recorded in `commission-final-core.json` and `commission-checkout.json`; they are summarized in the completion response.

Final core result: **199 executions / 1,244 assertions passed**, zero failures/errors. This includes inherited legacy cases, not 199 distinct new commission scenarios. Final checkout/report integration result: **6 tests / 79 assertions passed**, covering two actual checkout paths plus COD reporting, date/seller isolation, Admin exports and missing-source handling. The seven isolated MySQL groups and all six browser previews passed. `git diff --check`, changed PHP syntax checks and JavaScript syntax checks passed.

A subsequent preservation check still matched every non-session table (111 original tables / 988 rows); the sole later difference was the remaining runtime session expiring/changing. All eight new commission tables were still empty. Enrollment remained false with no activation timestamp. No business record was changed by migration or validation.

The isolated MySQL runner passes seven groups: actual-schema migration and purchase/order earning; duplicate receipt verification; duplicate partial refunds; competing distinct Seller transfers; reporting reconciliation; two distinct receipts after a partial refund under a delayed concurrent update; and COD remittance draft reservation. Only synthetic databases and private synthetic receipt files are used.

Headless Chrome checked Seller, Platform Admin and Logistics commission workspaces at 1440px and 390px. All six previews had no page overflow or unlabelled commission inputs. Cancelled confirmations prevented submission without busy-locking forms; the static server received zero writes. Screenshots and `browser.json` are private. PHP and JavaScript syntax checks passed.

## Full file manifest

### Commission UI consistency refinement

Seller follow-up: Commission Statements now sits directly below Dashboard and above Order Management. The seller page uses seller-specific headings and balance labels, hides the redundant Seller/company filters, and shows a helpful empty state instead of an unusable payment form when no GCash obligations on the displayed page are available. Existing role scopes, collection rules and activation settings remain unchanged. Rendering/authorization checks passed (2 tests / 29 assertions), sidebar order was verified from rendered HTML, and the six desktop/mobile browser checks passed.

Aligned the commission workspace with the existing finance pages: shared theme colors, compact KPI cards, separate receipt totals, card headers and export actions, responsive filter grid with Reset, status badges, readable empty states and expandable financial-action forms. The current platform receiving account is shown above its update form. Payment and credit records now use dedicated presentation partials (`payment-record.blade.php`, `credit-record.blade.php`). Asset URLs include modification timestamps, and malformed remittance currency output was corrected. Commission calculations, services, routes, permissions and database records are unchanged by this refinement.

Validation: two presentation/authorization cases passed (29 assertions), three financial-action/ownership regressions passed (27 assertions), and six synthetic Chrome previews at 1440px/390px passed with no page overflow, unlabelled commission inputs or writes. Cancelled confirmations still prevented submission without busy-locking forms.

Added:

- `app/Http/Controllers/CommissionController.php`
- `app/Services/CommissionLedger.php`
- `app/Services/CommissionPayments.php`
- `app/Services/CommissionReporting.php`
- `config/commission.php`
- `database/migrations/2026_11_08_000001_add_platform_commission_ledger.php`
- `docs/platform-commissions.md`
- `public/css/commissions.css`
- `public/js/commissions.js`
- `resources/views/commissions/destination.blade.php`
- `resources/views/commissions/index.blade.php`
- `resources/views/commissions/receipt-fields.blade.php`
- `tests/Feature/CommissionTest.php`
- `tests/Support/commission-mysql.php`
- `tests/browser/commission-workspace-check.mjs`

Modified:

- `app/Http/Controllers/CommerceController.php`
- `app/Http/Controllers/RiderController.php`
- `app/Http/Controllers/SellerPayoutController.php`
- `app/Models/GcashPurchase.php`
- `app/Models/Order.php`
- `app/Services/CodSellerPayouts.php`
- `app/Services/LogisticsReconciliation.php`
- `app/Services/PaymentReporting.php`
- `resources/views/admin/commission.blade.php`
- `resources/views/admin/partials/sidebar.blade.php`
- `resources/views/admin/reports-sales-pdf.blade.php`
- `resources/views/admin/reports.blade.php`
- `resources/views/admin/settings.blade.php`
- `resources/views/logistics/partials/finance-navigation.blade.php`
- `resources/views/logistics/partials/sidebar.blade.php`
- `resources/views/logistics/seller-payouts.blade.php`
- `resources/views/reports/payment-summary.blade.php`
- `resources/views/seller/partials/sidebar.blade.php`
- `resources/views/seller/reports.blade.php`
- `routes/console.php`
- `routes/web.php`
- `tests/Feature/BuyerCommerceTest.php`
- `tests/Feature/CodSellerPayoutTest.php`
- `tests/Feature/LogisticsReconciliationTest.php`
- `tests/Feature/RiderCompensationTest.php`
- `tests/Support/WorkflowSchema.php`
