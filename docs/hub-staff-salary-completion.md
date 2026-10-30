# Hub Staff fixed-salary payroll completion

Workspace update, 30 October 2026: search/filter pagination, bounded bulk previews and preparation, focused payment navigation and masked payroll CSV now extend this original implementation. See [Hub Staff payroll workspace](hub-staff-payroll-workspace.md) for the current files, validation and pending index-only migration. The earlier single-Staff implementation history below is retained.

Deployment update, 30 October 2026: after the reported salary-page 503, applied only `2026_10_30_000001_add_hub_staff_salary` and `2026_10_30_000002_add_staff_salary_payment_date` to the configured database. Both completed successfully. The installation check now returns true and compiled views were cleared. The four unrelated 29 October migrations remain pending. The original implementation-stage migration statements below describe the earlier state; no salary rates, funding or payments were created during this fix.

30 October 2026. Continued the existing StaffSalary implementation rather than replacing it. No configured business records or actual GCash transfers were created. No migration was applied to the configured database.

## Existing progress retained

The project already contained StaffSalary, StaffSalaryController, four salary tables, Admin and Staff pages, routes/sidebar entries, protected proof access, a separate staff_payroll treasury bucket, and a 29-test salary suite. The existing prepare → verify → record actual payment → acknowledge structure, private evidence storage, financial audit events, locking and unique period/payment keys were retained.

## Completed and corrected

- Second installment is due on the 30th, or February's last day if earlier; coverage still runs through month end.
- Integer division assigns the odd remaining centavo to the second installment. With an unchanged PHP 300.01 monthly snapshot, installments are PHP 150.00 and PHP 150.01.
- Generation accepts current or elapsed periods starting with the period in which the first salary was configured. Earlier periods and future periods are rejected. No scheduler automatically generates obligations or marks them paid.
- Late generation selects the historical rate at period start, with the initial rate used in the initial enablement period. A later revision does not replace earlier generated amounts. Rate revisions saved during a period apply from the next period. Employment dates and partial-period proration are not implemented.
- Staff registers GCash details in the existing My Account page. Server-side confirmation and the existing Philippine mobile validation are required. Destination rows remain append-only; payout instructions retain their original destination ID. Staff-facing account numbers are masked; Admin sees the destination needed for manual payment.
- Actual external payment date is required separately from paid_at, the recording timestamp. Future dates and dates before preparation are rejected. Exact amount, explicit sent confirmation, reference, notes and private proof remain mandatory.
- Staff status and company are checked again before preparation, verification and payment. Staff salary reads, acknowledgements and proof access respect ownership and company.
- Outstanding/paid totals, unpaid due dates, scheduled next pay date, account IDs, assigned hubs and clear instruction statuses are displayed. A cancelled unsent instruction can be prepared again without creating a second payout row; audit history remains.
- Reconciliation recognizes Staff funding and payment sources, the separate bucket, reservations and Staff payout history; Staff money is excluded from COD conservation and Rider balances.
- Fixed the existing Admin Blade rendering error. Added salary-specific guidance, confirmation dialogs and responsive account-form spacing without restoring the removed horizontal finance menu.

## Pages and routes

| User | Page / actions |
| --- | --- |
| Logistics Admin | GET `/logistics/finance/staff`, named `logistics.finance.staff` |
| Logistics Admin | POST `/logistics/finance/staff/configure`, `/periods`, `/funding`, `/prepare`, `/{id}/action`, `/{id}/pay` |
| Hub Staff | GET `/logistics/my-salary`, named `logistics.my-salary` |
| Hub Staff | POST `/logistics/my-salary/destination`, `/{id}/acknowledge` |
| Hub Staff | Existing My Account → Salary GCash section |
| Authorized recipient / company Admin | Existing protected `finance.evidence` route |

Admin actions remain under logistics.admin middleware. Staff cannot configure salary, verify funding or mark salary paid. Other roles retain the existing login redirect or permission denial behavior.

## Complete flow

1. Admin configures an approved company Staff member's monthly rate in Hub Staff Salaries.
2. Staff confirms their own GCash receiving details in My Account.
3. Admin generates the applicable period; its amount and salary-row reference become immutable obligations.
4. Admin records a separately received Staff payroll contribution with reference, confirmation and private evidence. This records external funding; it does not move money.
5. Admin prepares an instruction, reserving available Staff funds and selecting the Staff-provided destination. Admin reviews and verifies it. A documented hold blocks payment; cancelling an unsent instruction releases its reservation and retains the unpaid period.
6. Admin manually sends GCash outside PocketFinds, then records the exact transfer amount, actual transfer date, reference, proof and confirmation. One Staff payroll debit is recorded. Preparation and verification never send or record payment themselves.
7. Staff reviews My Salary and acknowledges actual receipt. Acknowledgement does not create another payment.

## Files

Existing work completed: `app/Services/StaffSalary.php`, `app/Http/Controllers/StaffSalaryController.php`, `resources/views/logistics/staff-salary.blade.php`, `resources/views/logistics/my-salary.blade.php`, `tests/Feature/StaffSalaryTest.php`.

Integration updated: `app/Services/LogisticsReconciliation.php`, `app/Http/Controllers/LogisticsController.php`, `app/Http/Controllers/SellerPayoutController.php`, `resources/views/logistics/account.blade.php`, `resources/views/logistics/partials/finance-navigation.blade.php`, `public/css/logistics-account.css`, `public/js/logistics-finance-feedback.js`, `tests/Feature/NavigationTest.php`, `tests/Support/cash-desk-mysql.php`.

Added: `resources/views/logistics/partials/staff-salary-account.blade.php`, `resources/views/logistics/partials/staff-salary-summary.blade.php`, this report, and the payment-date migration below. Existing routes and salary sidebar entries were reused.

## Migrations and deployment

- Existing pending `2026_10_30_000001_add_hub_staff_salary.php`: salary history, period obligations, immutable destination rows and payout instructions.
- Added pending `2026_10_30_000002_add_staff_salary_payment_date.php`: nullable payment_date for historical compatibility; new payments require it. No old date is fabricated.
- Read-only migrate:status also reported 29 October migrations 000006–000009 pending. Review those existing changes and back up/validate migration rollout before running migrations on a deployed database. Salary pages require both Staff migrations; Account hides salary settings until installed.
- Deploy views, assets and service/controller changes together; clear compiled views after deployment. Configure real salary rates and verify real receiving accounts only through authorized users after migration rollout.

## Validation

- Final combined run: **173 tests / 2,519 assertions passed**, zero failures/errors, across StaffSalaryTest, LogisticsReconciliationTest, RiderCompensationTest, CodSellerPayoutTest, LogisticsCashDeskTest, NavigationTest and LogisticsAuthorizationTest. Synthetic SQLite only. Includes 38 salary tests covering calculations, history, pre-enablement/future rejection, destination confirmation/snapshots, payment dates, reservations, cancellations, ownership, suspended recipient checks, private proof and audit rollback.
- Subsequent Account spacing change: own/other Staff Account HTTP test passed again.
- Isolated MySQL runner: **27 PASS groups**, including concurrent Staff period generation, payout reservation, actual payment and acknowledgement; one Staff debit, one acknowledgement event, COD/Rider funds unchanged. Runner copied schema only into a random test database, applied migrations there and dropped that test database afterward.
- Chrome checks of Admin salary, Staff My Salary and Account at 1440px and 390px: no page overflow, no unlabelled visible form fields, visible controls at least 44px high (checkbox labels included). Cancelling payment, acknowledgement and destination confirmation prevented submission and left forms usable. Synthetic HTTP-rendered pages; no live account or provider used.
- PHP syntax, JavaScript syntax and diff whitespace checks passed. This is selected regression coverage, not a whole-repository-green claim.

Private verification artifacts: `storage/app/private/staff-salary-complete.xml/json`, `staff-salary-mysql.txt`, `staff-salary-browser.txt`, `staff-salary-account-check.json`, and synthetic previews/screenshots under `storage/app/private/logistics-ui-review`.

## Boundaries

No attendance, deductions, overtime, bonuses, penalties, automatic transfers, automatic period generation, employment termination workflow or historical corrective-payment workflow was added. Configure and generate the initial period deliberately: the initial period receives the full fixed installment; no partial-period employment policy is inferred. Existing obligations survive later suspension or company changes, while new payment attempts require an approved same-company recipient. Such exceptions need manual policy review; they are not silently forgiven or redirected.

The tests establish source behavior and isolated database concurrency. They do not certify actual GCash delivery, production migration success, or real payroll accuracy before business configuration and acceptance testing.
