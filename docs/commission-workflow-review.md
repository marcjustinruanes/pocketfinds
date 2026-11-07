# Commission workflow review — 7 November 2026

Latest remediation: [commission-remediation.md](commission-remediation.md) records the completed proof cleanup, restored checkout safeguards, rider workflow fixes and passing assessed regression selection. Collection remains disabled; hosted/operational and legacy timestamp acceptance gates remain.

Production-readiness follow-up: [the final assessment](commission-production-readiness.md) identifies a new proof-file rollback cleanup defect, a separately reproduced adjacent shipping regression, unresolved wider test failures and production configuration/operations gates. The four original workflow findings remain fixed; their resolution does not constitute activation approval.

**Final follow-up: all four findings below have been corrected. Collection remains disabled.** The original findings and evidence are retained below as the initial review record; the completed fixes and repeated review are recorded here.

## Completed fixes and repeated review

1. Added an independent, audited Seller receipt recovery workflow. An unverified recovery claim does not take ownership of a reference. Platform Admin must verify the actual amount, receiving account, rightful Seller and ownership evidence. A prior ordinary claim must be rejected and entirely uncollected. Its original record and proof remain unchanged. A separate verified-receipt registry makes the reference and proof unique at verification, including concurrent competing recoveries. References belonging to unrelated financial transactions remain blocked. Verified recovery money starts as unapplied Seller credit; it can only be allocated to that Seller's eligible GCash commission or actually repaid to their recorded GCash destination with private proof. Later product refunds include recovery allocations in commission reversal credit calculations.
2. Added “Already sent a payment?” independently of obligations, filters, pagination and outstanding balance. A link beside the ordinary payment workflow leads directly to it. Seller, Platform Admin and Logistics histories retain their role scopes; Logistics cannot view or decide Seller recovery receipts. Recovery receipts, decisions, allocations and repayments have their own history and filtered CSV.
3. Added actual COD buyer-refund funding reconciliation and residual payout snapshots for enrolled orders. Logistics documents the existing recorded product refund, buyer destination, reference, actual proof and responsible payer. Company-funded refunds debit COD once. Seller-funded refunds are reimbursed in the Seller's residual payout. Platform-funded refunds reserve COD, require an actual reimbursement transfer into a separate refund-transit bucket, and remain blocked until Platform Admin verifies actual receipt at the frozen destination. These reimbursements are not commission revenue. Only a matching, verified refund snapshot can release the original refund hold. Existing delivery, full collection, dispute, authorization, treasury funding, payout verification, proof and acknowledgement safeguards still apply. Original collections and order prices remain unchanged; a paid order cannot receive a second residual payout.
4. Added separate created-date and status filters to payment and credit histories and CSVs, applied before the 10,000-row limit. Recovery CSVs use the same controls. Date order and allowed statuses are validated, role scopes remain mandatory and CSV formula protection remains in force.

Example checked through Laravel HTTP: original product funds PHP 1,000 and shipping PHP 100; actual refund of the PHP 600 line leaves PHP 400 product funds and PHP 40 commission. A company-funded refund leaves a PHP 360 Seller transfer, PHP 100 shipping and PHP 40 protected commission. If the Seller already paid the PHP 600 buyer refund, their transfer is PHP 960 including reimbursement; their economic net is still PHP 360. Platform-funded refund reimbursement requires a separate actual-send and receipt-verification workflow before the PHP 360 payout.

### Final checks

| Selection | Result |
| --- | --- |
| New fixes and actual role HTTP workflows | 11 tests / 217 assertions passed |
| Commission, COD, cash desk, reconciliation, payroll and attendance regression selection | 218 executions / 1,469 assertions; 217 passed, one existing offline-rider fixture failure |
| Checkout and payment reporting integration | Seven tests / 90 assertions; six passed, one existing offline-rider fixture failure |
| Baseline reproduction | Both failing cases independently reproduced using the preserved original application class map; no working source was replaced |
| Isolated MySQL | Eight groups passed, including the new schema and competing recovery receipt verification |
| Browser | Six actual-controller response previews at 1440px/390px passed: zero page overflow, unlabelled visible commission inputs or writes; cancelled confirmations remained safe |
| Export capacity | 10,001-row synthetic payment and credit histories rejected unfiltered exports and successfully exported only matching date/status rows |
| Syntax and whitespace | Changed PHP and JavaScript syntax checks and git diff whitespace check passed |

The two existing failures are `LogisticsReconciliationTest::test_complete_buyer_gcash_to_delivery_preserves_direct_seller_payment_and_company_receipt` and `PaymentReportingTest::test_reporting_direct_gcash_and_platform_claims_are_separate_from_logistics_receipts`. Both encounter “This rider is offline and cannot receive a new job.” This task did not weaken the rider availability check. The previously recorded wider baseline failures remain outside this fix; no whole-repository-green claim is made.

### Backup and installation

The initial complete backup restored and verified 120 tables / 997 rows. Concurrent checkout, inventory, chat and GCash activity was subsequently detected in the configured database; those changes were preserved, not overwritten. A fresh complete mysqldump backup restored and verified 120 tables / 1,064 rows at `storage/app/private/database-backups/commission-complete-20261107-112834` before installation.

Applied only `2026_11_08_000002_complete_commission_workflows`, batch 111. This additive migration creates seven tables: `commission_verified_receipts`, `commission_recoveries`, `commission_recovery_events`, `commission_recovery_allocations`, `commission_recovery_refunds`, `cod_refund_reconciliations` and `cod_residual_settlements`. It performs no business-data backfill or update and refuses destructive rollback. Post-install comparison matched every original table and all 1,064 rows against the fresh backup, excluding only the new migration-history entry. All seven new tables and all eight original commission tables remain empty. Enrollment remains false and activation timestamp null. Compiled views were cleared; no receiving account, real proof, refund, payout or commission was submitted.

### Remaining operational limits

Do not activate collection yet. The baseline delivery failures and earlier wider release blockers remain. Historical/un-enrolled COD orders, already paid payouts and refunds exceeding their original available product funds continue to require a separate funding/corrective-payment review; the new path does not invent funding, reverse an actual transfer or silently pay an old gross entitlement. Existing whole-line return quantities and separate direct-GCash platform subsidy reimbursement remain unchanged. External GCash delivery, hosted authenticated sessions, production scheduler execution and production browser security acceptance are not certified by synthetic tests.

Implementation files added: `CommissionReceipts`, `CommissionRecovery`, `CodRefundSettlement`, the additive migration, recovery/refund/filter Blade partials and `tests/Feature/CommissionWorkflowFixTest.php`. Integration changes cover `CommissionController`, commission routes/views, `CommissionLedger`, `CommissionPayments`, `CommissionReporting`, `CodSellerPayouts`, `LogisticsTreasury`, `LogisticsReconciliation`, Seller payout presentation and existing commission/MySQL test setup.

Evidence: private `commission-fixes-new.xml/txt`, `commission-fixes-regression.xml/txt`, `commission-fixes-integration.xml/txt`, `commission-fixes-baseline*.txt`, `commission-fixes-mysql.txt`, `commission-fixes-browser.txt`, `commission-fixes-preservation.json`, backup manifests, concurrent-difference metadata and synthetic screenshots under `commission-ui`.

## Initial findings (resolved above)

### 1. High priority: a rejected reference claim can block the actual payer

`CommissionPayments::submit()` reserves the transaction reference globally before receipt verification (`app/Services/CommissionPayments.php:137`). Rejection changes the payment status but leaves that reservation and the payment reference in place (`app/Services/CommissionPayments.php:161`). `LogisticsShipping::reserveReference()` refuses reuse by another payment (`app/Services/LogisticsShipping.php:198`).

Reproduced through HTTP: Seller A submitted reference COMM001; Platform Admin rejected it because it belonged to Seller B; Seller B then submitted its actual proof with COMM001 and received a reference validation error. No extra payment or collection was created, but there is no application workflow for assigning the actual receipt to its rightful Seller. Verifying A's record or allocating A's credit to B would also be inappropriate.

Required correction: separate unverified claims from authoritative verified receipts, or provide a controlled, audited reference-dispute/reassignment process. Preserve rejected evidence and maintain one verified allocation source per actual transfer; do not simply remove duplicate-reference protection.

### 2. High priority: Seller proof submission disappears when the available debt is zero

The Seller form is conditional on an available GCash obligation on the current page (`resources/views/commissions/index.blade.php:67`). A full refund, another pending receipt, a restrictive filter or pagination can hide the form even when the Seller has already sent money and still needs to record proof.

Reproduced through HTTP: an earned commission was fully reversed after a hypothetical external send but before proof submission. The rendered Seller page contained no payment submission form. Posting the original valid payment payload directly still worked, and verification correctly produced unapplied Seller credit. The backend supports the accounting case; the UI prevents a Seller from reaching it after reloading the page.

Required correction: provide an explicit “Already sent a payment?” submission/recovery flow independent of current collectible balance. It must remain scoped to the Seller and preserve excess verified money as unapplied credit, without creating a new fee or requiring a fabricated debt.

### 3. Incomplete existing workflow: partially refunded COD cannot reach residual settlement

The COD eligibility guard rejects every return status other than a sufficiently old rejection, including a completed `refunded` return (`app/Services/CodSellerPayouts.php:35`). Commission reversal does not release that hold.

Reproduced with a synthetic ₱1,000 merchandise order containing ₱600 and ₱400 lines: refunding the ₱600 line correctly left ₱40 commission on the retained line, but COD payout eligibility remained false. This preserves the existing guard and avoids unsafe overpayment; it is not a new commission deduction error. However, the complete remaining-proceeds settlement is not available through the current workflow.

Required correction: add an audited refund/funding reconciliation and residual settlement path that accounts for the actual buyer refund, remaining Seller entitlement, shipping and revised commission. Do not merely allow `refunded` orders through the old gross-payout calculation.

### 4. Lower priority: payment/credit CSVs cannot be narrowed after reaching the cap

`CommissionController::moneyExport()` exports the full role-scoped history and rejects more than 10,000 rows (`app/Http/Controllers/CommissionController.php:110`). Unlike obligation exports, it accepts no date/status filters to narrow the export. This is a code-inspection finding, not a 10,001-row live experiment. Add bounded history filters before this becomes an operational reporting limit.

## Checks performed

| Area | Evidence and result |
| --- | --- |
| Commission and existing COD regression | 68 executions / 527 assertions passed, including inherited legacy cases |
| Actual checkout and payment reports | 6 tests / 79 assertions passed |
| Additional Laravel HTTP review cases | 6 cases / 78 assertions passed: three successful workflow/authorization checks and three characterizations reproducing the gaps above; Platform receiving instructions were published through the HTTP endpoint in the synthetic fixture |
| MySQL | Seven isolated groups passed: schema compatibility, receipt races, refund races, distinct transfer preservation, reporting, distinct receipts after partial reversal, and remittance draft reservation |
| Pages | Seller, Platform Admin and Logistics layouts rendered through actual Laravel controllers; six synthetic Chrome previews at 1440px/390px passed |
| UI safety | No page overflow or unlabelled commission inputs in checked previews; all six cancelled confirmations prevented submission; static server received zero writes |
| Permissions | Seller, Logistics Admin, Buyer, Staff and Rider could not verify platform receipts; another company could not cancel the remittance; suspended Seller access was refused |
| Calculations | Fixed 10%, seller-funded product discounts, excluded shipping, platform subsidy treatment, integer rounding and large integer allocation covered by the regression selection |
| GCash | HTTP submission remained uncollected until actual-receipt attestation; recorded partial product refund created the correct commission credit; HTTP credit repayment preserved its destination and evidence |
| COD | HTTP prepare/verify/net-payout path, draft remittance, actual-send recording and Platform Admin receipt verification passed; withholding/transit did not count as collection |
| Existing records | Preservation check matched 110 original non-runtime tables / 917 rows against the complete backup. Only runtime cache/session differences were reported |

Passing characterization cases **confirm the problems exist**; they are not approval to release. The review test file is private (`storage/app/private/CommissionWorkflowReviewTest.php`), so it does not add tests expecting defective behavior to the normal suite.

## Activation and operational prerequisites

- Configured `commission.enabled` remains false; `commission.activation_at` remains null.
- All eight commission tables are empty, including receiving accounts. No real commission, receipt, credit or payout was submitted.
- `php artisan schedule:list` lists `commissions:reconcile` every five minutes. This proves registration, not that a production scheduler daemon/cron is running.
- Earlier broader-suite evidence contained 29 baseline failures/errors. Those were not repaired or declared green by this review; the full wider selection was not rerun in this turn.
- Direct-GCash platform subsidies still require separate real reimbursement. Commission revenue is not a complete platform profit-and-loss report.

## Limits and evidence

The hosted authenticated `/commissions` page was inaccessible through the web tool, and no live role sessions were available. UI acceptance therefore used the actual local Laravel routes and isolated fixtures, then browser rendering of those responses. No claim is made that the hosted deployment, production login sessions, external GCash receipts, transfers or delivery have been certified. Laravel feature requests use the testing environment; CSRF registration was inspected, not asserted as a production browser security test.

Private artifacts: `commission-review.xml/json`, `commission-review-http.xml/json`, `commission-review-integration.xml/json`, `commission-review-mysql.txt`, `commission-review-pages.json`, `commission-review-browser.txt`, `commission-review-schedule.txt`, `commission-deployment.json`, and screenshots/`browser.json` under `commission-ui`.
