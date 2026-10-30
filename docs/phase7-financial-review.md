# Phase 7 financial reconciliation review

Implemented on 29 October 2026. No new account role or existing role-permission change was introduced. The separate Logistics Admin / Hub Staff / Finance Role Security Review has not started.

## Delivered

Logistics Admin now has **Finance > Reconciliation & Reports**. The company-scoped, read-only workspace covers COD collections and allocations, pending rider remittances, physical hub custody and qualified COD cash, hub and treasury transfers, company treasury buckets/reservations, verified GCash shipping receipts, gross shipping entitlement, voucher funding/claims, seller settlements, earned rider compensation, payroll funding and payouts. Existing Cash Desk, settlement and payroll pages handle actions; the report links back to those workflows.

Balances and review entries show the current committed snapshot across all recorded history. The Manila date range selects activity rows; it does not reconstruct a historical balance. Individual lifecycle rows show their latest available receipt/dispatch/verification/payment date or creation date, while audit history lists individual actions. CSV exports include company, snapshot time, record timezone, date range and units. They are private/no-store, formula-safe, limited to 20,000 rows and audited. They omit account numbers, proof paths and raw audit details. More than 20,000 audit events prompts a narrower activity range rather than silently producing a partial export.

## Accounting boundaries and reconciliation basis

| Category | Authoritative reporting source | Treatment |
| --- | --- | --- |
| Buyer COD | Recorded COD transaction | Count once; never treat another remittance/deposit as a new buyer payment. |
| Verified COD | Allocations on physically counted remittances | Distinct from outstanding rider cash and submitted reservations. |
| Hub cash | Signed cash custody movements | Includes actual overages. Qualified COD is shown separately; hub cash cannot directly fund electronic payouts. |
| Transit | Confirmed release movement awaiting destination/treasury count | Authorization alone is not cash in transit. Receipt removes transit and records actual differences. |
| Treasury | Signed account movements, separated into COD, seller adjustment, rider payroll and unallocated buckets | Payout ledger entries are mirrors, not another debit. Reservations reduce availability, not the recorded balance. |
| GCash shipping | Verified shipping ledger receipts | Historical manually verified inflow. No receiving-provider balance or unrecorded external spending is inferred. It is not spendable treasury/payroll funding. |
| Shipping | Gross approved obligation; COD retention on actual seller payout | Operational receipt/settlement view. No new accrual, tax or net-profit policy is introduced. Retained COD shipping remains part of COD treasury, not extra cash. |
| Voucher funding | Explicit shipping snapshot funders | Seller funding and platform claims stay separate. Claims remain unverified/unpaid; no deduction from gross logistics entitlement. |
| Seller exposure | Known COD collection less approved gross shipping and recorded product payout | Conditional exposure is distinct from currently eligible proceeds, reserved instructions, separately funded additions and actual payment. Missing shipping basis remains unknown, not an invented payable. |
| Rider expense/liability | Earned immutable snapshots less actual payouts | Pending rates are not expense. Payment settles the liability without recording the earning twice. COD shortages never offset compensation. |
| Contributions | Verified, evidenced treasury funding | Funding is not revenue. Payroll and seller-addition funds cannot cross-fund one another. |

The counted-cash conservation check is:

`counted remittances + actual transfer differences - seller COD disbursements = hub cash + released cash in transit + COD treasury + unallocated treasury`

Transfer differences preserve actual shortages/overages. A zero equation residual is not evidence that a shortage, disputed payment, bank balance or historical record is resolved. Open and documented/resolved discrepancies remain visible. Payout mirrors, funding bucket mismatches, missing source/evidence, over-allocation, negative/over-reserved balances, incomplete earnings evidence, missing obligations, legacy funding classifications, active returns/disputes and platform claims are separate review entries.

## Review fixes

- Seller payouts now reserve both funding-provider and recipient-provider reference namespaces, as rider payouts already do. A bank-funded GCash payout cannot reuse a buyer's GCash reference. This adds no transfer, fee or role policy.
- Seller eligibility can explicitly read allocation balances without locking. Money-moving calls still request locks. MySQL report requests use one repeatable-read snapshot, avoiding mixed ledger/balance views while payments commit.
- Export authorization checks the company's identity again immediately before recording export access. Company changes between snapshot generation and export are rejected.
- Treasury reservations are computed independently for each account/bucket, including unallocated funds. Multi-account and overage cases are covered.
- Finance settings copy now describes assignment snapshots as agreed rates rather than reserved cash.
- Both originally documented fixture failures were fixed using controlled geography responses and an isolated notifications schema. The geography fixture retains real hierarchy validation and blocks stray requests. The insights and additional Admin Assistant render fixtures now install their layout dependency. Navigation assertions reflect the already approved Finance/personnel hierarchy and also check the new report/payroll parent groups. Production validation and role permissions remain unchanged.

## Local historical review

A read-only local MySQL inspection found:

- 5 shipments, all using historical policy version 0.
- 0 logistics financial transactions, cash handovers, treasury movements, seller payouts or rider payouts.
- 3 companies with an existing approved company Admin could be reviewed through the scoped service. Their cash-equation residuals were zero; one legacy shipping-basis review entry was reported.
- 4 of the legacy shipments are outside companies with an approved company Admin available for this review. Carrier/company ownership and the appropriate authorized reviewer need manual investigation. No account, role, historical payment, obligation or opening balance was created to cover them.

Zero financial records are not proof that historical sellers/riders were unpaid or that real-world cash is zero. Those facts are not recorded in this ledger. The local inspection is not a production financial audit. Private aggregate evidence is in `storage/app/private/logistics-ui-review/phase7-local-audit.json`.

## Verification

- Targeted finance, fulfillment, GCash, logistics, geography, media, commerce, insights, Admin Assistant and navigation suites: **343 tests / 4,332 assertions passed**.
- Final Phase 7 suite: **19 tests / 227 assertions passed**, including the final export-company recheck. These overlap the targeted run; totals must not be added.
- The broader repository run examined **509 tests: 458 passed, 22 failures, 2 errors and 27 skipped**, with 5,678 assertions. This run preceded the last two report-specific cases; the final targeted checks cover those additions. No broader failure belongs to the finance/reconciliation suites.
- Complete COD custody-to-treasury-to-seller payout and independently funded rider payroll reconciled without duplicate cash. Complete buyer GCash creation/proof/verification/order handoff, shipping prepayment gate, pickup, origin hub receipt, delivery and buyer confirmation passed.
- Seller- and platform-funded COD vouchers preserve the full gross shipping entitlement and cash-only seller proceeds. GCash vouchers preserve gross shipping receipts and separate platform claims. Platform claims never become treasury funding.
- Partial remittances, pending reservations, overdue cash, shortages/overages, inter-hub transit, treasury differences, late returns/refunds/disputes after payment, holds, cancellation, duplicate payments, missing ledger/movement evidence, legacy records, multi-account reservations, CSV injection/privacy and missing migration handling are covered.
- Real multi-process MySQL races pass for cash reservation/count/release/receipt, insufficient hub custody, shipping submission/verification/reference ownership, treasury receipts, seller reservations/payment/disputes, rider assignment and whole-transfer earning recognition, payroll funding/reservations/payment, holds and acknowledgement retries.
- The Phase 7 race deliberately pauses a report after it reads the transaction ledger, commits a real isolated payout on another connection, then resumes the report. The first report retains the pre-payment view, the next report sees Paid, and cash reconciliation stays coherent. Operational writes are not blocked by report reads. The runner creates and removes only its random schema-only test database and private fixture files.
- Three browser fixture views (overview, manual review and treasury history) passed at 1440px and 390px: no document overflow, correctly labelled controls at least 44px high, correct Finance group/active link, Dashboard first, and light-theme checks. Desktop/mobile screenshots were inspected. Wide history tables scroll within their cards.
- No Phase 7 schema change was needed: existing additive migrations provide the required sources. A missing Phase 6 schema yields 503 instead of fabricating data or backfilling history.

### Remaining repository failures

`HubHiringTest` still supplies older registration/managed-account fixtures (missing required region and symbol-containing passwords). Its downstream lookups cannot find rejected fixture accounts. No production validation was weakened to make them pass.

`RiderWorkbookTest` retains older registration expectations: a rejected multi-word given name, geography calls without controlled provider fixtures, and outdated field/route assumptions. `TC-RID-029` expects `/register/rider` to be absent while the current public registration route returns 200; its protected logistics-page login check succeeds. This is an expectation requiring separate review, not evidence of a new finance authorization bypass.

Failures:

- `HubHiringTest::test_staff_requires_interview_and_records_each_decision`
- `HubHiringTest::test_staff_early_rejection_requires_reason_and_preserves_reapplication`
- `HubHiringTest::test_switches_are_independent_and_audited_without_changing_legacy_value`
- `HubHiringTest::test_staff_switch_does_not_block_rider_or_change_other_settings`
- `HubHiringTest::test_only_approved_assigned_role_consumes_slots_and_zero_blocks_intake`
- `HubHiringTest::test_reapplication_preserves_account_id_rejection_and_attempt_history`
- `HubHiringTest::test_history_failure_rolls_back_account_and_uploaded_files`
- `HubHiringTest::test_staff_fallback_ownership_and_vehicle_rules_remain_enforced`
- `HubHiringTest::test_shared_registration_preserves_rejected_buyer_records_without_hiring_restrictions`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-001"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-003"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-005"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-006"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-007"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-010"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-012"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-013"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-014"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-015"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-028"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-029"`
- `RiderWorkbookTest::test_workbook_case with data set "TC-RID-032"`

Errors:

- `HubHiringTest::test_generic_staff_creation_and_edits_cannot_establish_recruitment_states`
- `HubHiringTest::test_management_creation_status_changes_and_moves_enforce_capacity`

27 workbook cases remain explicitly skipped. This implementation does not claim those phone/UI workbook scenarios were executed.

## Changed application and test files

- `app/Services/LogisticsReconciliation.php`
- `app/Http/Controllers/LogisticsReconciliationController.php`
- `resources/views/logistics/reconciliation.blade.php`
- `resources/views/logistics/finance.blade.php`
- `resources/views/logistics/partials/sidebar.blade.php`
- `public/css/logistics-cash-desk.css`
- `routes/web.php`
- `app/Services/LogisticsCashDesk.php`
- `app/Services/CodSellerPayouts.php`
- `tests/Feature/LogisticsReconciliationTest.php`
- `tests/Feature/BuyerCommerceTest.php`
- `tests/Feature/CommerceInsightsTest.php`
- `tests/Feature/AdminAssistantTest.php`
- `tests/Feature/NavigationTest.php`
- `tests/Support/cash-desk-mysql.php`
- `docs/logistics-finance-implementation.md`
- `docs/phase7-financial-review.md`

Local verification helpers, JSON results, HTML fixtures and screenshots are under `storage/app/private/logistics-ui-review/`; they are private generated artifacts, not new product pages.

## Security concerns and limits

- Actual bank/GCash receipt/payment is manually attested. A database transaction cannot prove or atomically execute an external transfer. External transfers blocked by a new hold/dispute remain exception cases; do not cancel a sent transfer as unsent.
- The existing approved same-Admin preparation/verification policy remains. No new Finance role, separation-of-duties requirement or Hub Staff permission was introduced. Hub Staff continues to use only its assigned-hub Cash Desk; company reports and exports require the existing approved Logistics Admin authorization.
- Historical records, ambiguous transfers, unallocated overages and missing rate/completion evidence need authorized manual review. No automatic balance correction, refund, write-off, offset, claim funding or reversal was introduced.
- This is operational reconciliation from recorded sources, not bank-statement matching, statutory accounts, tax reporting, closing-period accounting, profit calculation or provider-balance verification. GCash shipping receipts cannot be assumed available for payroll. Any revenue-to-payroll transfer still needs an approved policy.
- Current company ledgers are read into one snapshot; this has not been production-volume load tested. The 20,000-row export cap and 366-day activity window bound exports, while current balances/review cases remain all-history. Large-ledger database aggregation/persistent reporting and historical closing snapshots remain future work.
- The remaining hiring/workbook failures and skips prevent a claim that the complete repository is green. Financial checks and scoped export protections passed, but the separate requested Role Security Review is still pending.
