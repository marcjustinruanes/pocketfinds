# Hub Staff payroll workspace

30 October 2026. Extends the existing StaffSalary implementation. No configured business salaries, destinations, obligations, funding or payments were created or changed during development. No migration was applied to the configured database.

## Workspace

Logistics Admin → Finance → Hub Staff Salaries now opens a selected-period worklist, not hundreds of salary cards.

- Server-side name/full-name/account-ID search, hub, year/month/half, status and attention filters.
- Stable surname/given-name/account-ID ordering; 25 employees per page. Duplicate names retain their account IDs and hubs.
- Current monthly rate, historical period basis when different, installment, due date, obligation/payment status, acknowledgement and next actions.
- Labels for missing salary, missing/invalid or ambiguous GCash, suspension, ineligible periods, insufficient funding, overdue generated obligations and holds. Funding labels mean no single supported account can cover that individual obligation; group-specific shortfall is calculated in preview.
- Suspended Staff remain visible alongside their obligations; new generation/preparation/payment still require approved same-company Staff. Generation does not require GCash. Preparation does.
- Configuration remains expandable per employee. Full salary history is a separate focused, paginated view. Saving a rate never changes an existing obligation.
- Focused payout links work independently of worklist/history pagination. Individual verification, hold/release/cancellation, actual transfer recording, private evidence and audit history remain there.
- GET filters, page context and validation return locations are retained. Selection is explicitly limited to the displayed page, with a labelled select-page control and live count; selection resets across pages/filters.
- The previously removed finance banners and section shortcut bars were not restored.

Summaries follow the selected period and Staff filters. Unpaid totals include generated obligations only; missing generation is reported as a count, not invented debt. Due-date labels do not attest that payment occurred. Coverage remains 1–15 and 16–month end; payment dates remain the 15th and 30th, or earlier month end in Manila time.

## Bulk generation and preparation

1. Select up to 25 explicit Staff IDs on a page and choose generation or preparation. Preparation also requires a company GCash treasury account.
2. Preview lists Staff identity/hub, amount, masked receiving destination/name, ready/existing/skipped state and reasons. Preview itself creates no salary obligation or reservation. Existing operational request auditing remains in effect.
3. Generation reuses the historical salary selection rules shared with StaffSalary. Existing periods are returned without duplicate creation. Suspended Staff, missing rates and unsupported periods are excluded.
4. Preparation preview totals ready items against separately verified Staff payroll funds and shows shortfall. If the complete eligible selection is underfunded, execution refuses the selection without preparing a subset. Admin must deliberately reduce the selection or add verified Staff funds.
5. A server-encrypted preview binds actor, company, selected IDs, period, salary/obligation/destination identifiers, amounts, instruction revisions and treasury account. It expires after 15 minutes. Confirmation is required.
6. Execution refreshes authorization, locks the funding account, checks existing instructions using current locking reads, and revalidates individual Staff, historical rate/amount, obligation and receiving destination. A competing successful request is reported as existing, not charged/reserved again.
7. The selection is one bounded outer transaction, with a savepoint for each individual operation. Expected per-item failures roll back that item and return an explicit failed result; successful items remain. Audit failure rolls back its associated item. A fatal error can roll back the bounded request. Deadlock/lock-timeout errors are not disguised as item success.
8. Results distinguish created, prepared, existing, skipped and failed. Retry uses the same individual uniqueness/reservation guards. No new aggregate financial record is created.

The bulk path does not change salary rates, verify all instructions, pay recipients or acknowledge on their behalf. Payment remains individual: exact amount, actual transfer date, reference, private proof and explicit sent confirmation. GCash movement is performed outside PocketFinds.

GCash destinations have second-precision saved timestamps. When multiple latest rows share the timestamp, the new bulk path does not guess which is newest. It asks Staff to confirm fresh details in My Account. Existing payout instructions retain their original immutable destination ID.

## Performance and export

The controller no longer loads all company salary history or historical obligations into PHP for the Admin workspace. Employee/period data is joined and filtered in SQL before pagination; summaries use SQL aggregation. Salary history and audit records are loaded only on their focused paginated pages. There are no destination lookups inside a payout-rendering loop. Funding history is also paginated. The Staff-facing My Salary workflow remains separate.

CSV export uses the selected period and filters, up to 1,000 Staff, without building the entire reconciliation snapshot. It includes Staff ID/name, hub, Staff status, configured and snapshotted monthly amounts, installment, year/month/half, due date, payment status, acknowledgement timestamp and masked GCash number. Prepared, verified and paid rows use their immutable instruction destination; unprepared rows use the current Staff destination. Later account changes cannot redirect the exported receiving details for an existing instruction. Monetary columns are integer centavos. Not-generated installments are estimates, identified by their status. CSV values use the existing spreadsheet-formula protection. Exports require fresh company Admin authorization, are audited and returned private/no-store. Full account numbers, private proof paths and files are excluded.

## Files and routes

Modified:

- `app/Services/StaffSalary.php`: shared historical period terms for previews and generation; current locking reads inside transactions; explicit company check on an existing period.
- `app/Http/Controllers/StaffSalaryController.php`: paginated worklist, focused history/payment, previews/execution and export.
- `routes/web.php`: Admin-only routes below; existing individual action routes retained.
- `resources/views/logistics/staff-salary.blade.php`: compact worklist and selection.
- `public/js/logistics-finance-feedback.js`: bulk confirmation messages.
- `resources/views/logistics/partials/finance-receipt-fields.blade.php`: optional Staff-specific confirmation wording and retention of text inputs after validation; other callers keep their defaults.
- `tests/Support/cash-desk-mysql.php`: bulk concurrency and shortfall checks, plus focused `--staff-only` mode.

Added:

- `app/Services/StaffPayrollWorkspace.php`.
- `resources/views/logistics/staff-salary-detail.blade.php`, `staff-salary-history.blade.php`, `staff-salary-preview.blade.php`.
- `resources/views/logistics/partials/staff-salary-payment.blade.php`, `staff-payroll-assets.blade.php`, `staff-payroll-filters-hidden.blade.php`, `staff-payroll-funding.blade.php`.
- `public/css/staff-payroll.css`, `public/js/staff-payroll.js`.
- `tests/Feature/StaffPayrollWorkspaceTest.php` and the index migration below.

New routes, all in the existing Logistics Admin group:

| Method | Path under `/logistics/finance/staff` | Named suffix under `logistics.finance.staff` |
| --- | --- | --- |
| GET | `/export` | `.export` |
| GET/POST | `/bulk/preview` | `.bulk.preview` |
| POST | `/bulk/execute` | `.bulk.execute` |
| GET | `/salaries/{staff}` | `.history` |
| GET | `/payouts/{id}` | `.show` |

Existing `/my-salary`, Staff receiving-account registration and acknowledgement routes remain unchanged. No bulk Mark as Paid endpoint was added.

## Migration and deployment

Added `2026_10_30_000003_index_staff_payroll_workspace.php`, containing indexes only:

- Users: business/company name, logistics role, status, ID — matches the employee restriction.
- Salary rates: Staff ID, company, revision — matches current/historical correlated rate selection.
- Destinations: Staff ID, saved timestamp, ID — matches recent destination and ambiguity checks.

Existing Staff/period and payout/period unique constraints remain authoritative. No new money columns or financial tables were introduced. The index DDL was exercised in synthetic SQLite and isolated MySQL databases. Its deployment lock/duration on real data was not benchmarked.

**The new migration remains pending on the configured database.** It was not applied, as requested. The workspace does not require the indexes to exist to render. Deployment should include the controller, services, views and assets together and clear compiled views. Applying the index migration requires explicit approval and the normal database rollout process. The unrelated pending migrations were not applied.

## Validation

- Combined suite: **187 tests / 2,690 assertions passed**, zero failures/errors. StaffPayrollWorkspaceTest includes all 38 existing StaffSalaryTest cases plus 14 new cases; also ran LogisticsReconciliationTest, RiderCompensationTest, CodSellerPayoutTest, LogisticsCashDeskTest, NavigationTest and LogisticsAuthorizationTest.
- Fixtures include 100 and 500 additional synthetic Staff across hubs, duplicate names, suspension, missing rates/accounts, historical periods, shortfall, stale previews, destination changes, foreign-company IDs and invalid selections.
- A year of 499 configured Staff produces **11,976 synthetic historical obligations**; selected-period totals remain correct and only 25 employees render. This is a functional scale fixture and bounded-query check, not a production throughput benchmark.
- Trigger-induced audit failures validate per-item rollback and explicit partial results for both generation and preparation; retries create only the missing records and do not duplicate reservations.
- Full isolated MySQL runner: **28 PASS groups**. Includes competing bulk generation/preparation, underfunded-group rejection, replay protection and unchanged COD/Rider funding balances. A repeatable-read/current-read race found during development was corrected and the focused and full runs passed afterward. Only a schema copy and synthetic data were used; the random test database was dropped.
- Focused MySQL bulk checks passed again after the final receiving-destination query change. Export regression confirms an updated Staff account does not replace the existing instruction's masked destination.
- Chrome at 1440px/390px: worklist with 500 synthetic Staff, focused payment/history and preview pages; no page overflow or unlabelled visible fields; visible controls/checkbox labels at least 44px. Page selection submits exactly 25 IDs through the associated form. Cancelling generation/payment confirmation prevents submission without leaving the form busy.
- PHP/JavaScript syntax and diff whitespace checks passed. A final focused workspace suite was also run after removing two unused batched read queries.

Private artifacts: `storage/app/private/payroll-workspace-regression.xml/json`, `payroll-workspace-final-focus.json`, `payroll-workspace-mysql.txt`, `payroll-workspace-mysql-focused.txt`, `payroll-workspace-browser.txt`; synthetic browser previews and screenshots under `storage/app/private/logistics-ui-review`.

## Remaining limits

Bulk work is deliberately capped at 25 explicit Staff per confirmation, with route throttles and no background payroll job. No cross-page invisible selection or automatic transfer is provided. Actual payments still require individual evidence. The existing employment/proration and historical corrective-payment limitations remain. Real GCash delivery, deployment migration locks, production-volume latency and comprehensive screen-reader acceptance were not tested. No whole-repository-green claim is made.
