# Hub salary defaults and Staff attendance

Deployment update after explicit approval: applied only migrations `2026_10_30_000003_index_staff_payroll_workspace` (batch 108) and `2026_10_30_000004_add_hub_salary_and_staff_attendance` (batch 109) to the configured database. Both completed successfully; compiled views were cleared. StaffSalary, HubStaffSalary and StaffAttendance installation checks now pass. Read-only controller/view rendering returned HTTP 200 for Admin Attendance, Hub Staff Salaries and My Attendance. No receipt, salary configuration, attendance or payment action was submitted; the four new hub/attendance tables remain empty. The unrelated 29 October migrations 000006-000009 remain pending. Statements below about pending migrations describe the earlier implementation state.

30 October 2026. Extends the existing StaffSalary and StaffPayrollWorkspace architecture. No configured business salary, attendance, funding or payment records were created or changed. No migration was applied to the configured database.

## Salary configuration and inheritance

Hub Staff Salaries includes expandable Hub salary defaults: company-owned hub identity, approved Staff count, monthly default, effective period, configuration and paginated revision history. Existing individual configuration and focused payout/history pages remain available. Existing finance shortcut bars were not restored.

- Hub defaults and revisions take effect at the next Manila payroll period boundary. They do not generate obligations or move money.
- Approval through the existing HubHiring account-save transaction records the applicable hub default. An already configured upcoming revision is retained as well. There is no separate approval or onboarding workflow.
- Existing individual salary rows remain individual overrides. Hub-default changes do not replace them. Admin can explicitly choose Use hub default, with confirmation, for the next period.
- Initial individual configuration can enable the current period; subsequent revisions apply next period. Existing historical selection and legacy initial-period behavior are retained.
- An approved Staff transfer records the old/new hub audit event. Inherited rates change from the next period; earlier rates and generated obligations remain intact. An individual override stays an override until deliberately changed.
- An unconfigured destination hub records an explicit missing-rate timeline marker. Its zero storage sentinel is not a zero salary obligation: period generation rejects it, the UI labels Missing Salary Configuration, and installment/snapshot exports remain blank. This prevents an older hub rate from silently carrying into an unconfigured hub.
- Rates, source hub, source hub revision and effective period remain historical records. Only the existing superseded marker is updated. Existing paid and unpaid obligation amounts are never recalculated.

The existing 1-15 and 16-month-end coverage, 15th and 30th/earlier-month-end due dates, Asia/Manila calculations, integer centavos and second-installment odd-centavo rule remain unchanged. Different prospective monthly rates can legitimately apply to different periods; no retroactive monthly equalization is performed.

## Attendance workflow

1. An approved Hub Staff member opens My Attendance. The server checks fresh role, company and operational assigned hub. Time In always uses server time and ignores submitted account IDs or clock values.
2. One active shift is permitted per Staff member. Transactions, Staff locks and a nullable unique active-Staff key protect duplicate submissions. Time Out requires an active shift and records its original server timestamp once.
3. Overnight shifts retain the Time In business date and hub. Open intervals display Working, not continuous payable hours. Admin can explicitly flag an open shift Missing Time Out with a reason; an actual Time Out closes it and clears that flag.
4. Staff can propose a correction for an existing shift or a forgotten Time In. They provide the affected PHT date, proposed times and reason. Only one pending request per issue is allowed.
5. Company Admin reviews original, current and proposed timestamps, then approves or rejects with a required reason. Approval checks the record revision, competing records, overlapping shifts, active-shift constraints, and leave/absence conflicts. A stale request must be rejected and resubmitted. Original clock values remain unchanged; corrected values, before/after evidence, actor and reason are audited.
6. Admin can document approved leave or a confirmed absence. Absence requires explicit attestation that an actual scheduled workday was verified, cannot be future-dated, and cannot replace leave or a recorded shift. Voiding a documented day mark requires a reason and preserves its audit record.

There is no work-schedule engine. No Record never automatically becomes Absent. Rest days are not inferred. Missing Time Out is a deliberate review flag rather than an arbitrary elapsed-hours rule. An open shift must be closed or corrected before recording conflicting work or day marks.

Timestamps use the existing application storage timezone (UTC in the reviewed configuration); display, proposed correction input and business dates use Asia/Manila. Ordinary Staff cannot directly edit timestamps, decide corrections, manage other Staff, or export company attendance.

## Admin workspace and payroll relationship

Hub Staff Attendance sits under Personnel & Resources; My Attendance sits under Staff Operations. Admin has company-wide hub/date/status/Staff filters, name/full-name/account-ID search, stable server-side pagination (25 attendance rows), a separately paginated correction queue (20), recorded duration, audit details and a private audited CSV export. Date ranges are bounded to 94 calendar days; exports are limited to 10,000 rows and protect spreadsheet formulas.

History dates refer to shift start dates. No Record in a filtered range means no non-void entry in that range. The correction queue follows Staff/hub/search/decision filters and deliberately retains older pending requests outside the history date range. Closed recorded intervals may be exported as minutes; open shifts do not accumulate displayed payable hours.

The selected salary period shows recorded shift dates, confirmed absences, approved leave, elapsed calendar dates with no record, open shifts, flagged missing Time Out and pending corrections. Summary queries are restricted to the 25 displayed employees. Review links retain the selected period dates. Calendar gaps are explicitly not scheduled absences, and future calendar dates are excluded from gap counts.

Attendance changes do not call funding, reservation, payment, deduction or acknowledgement services. Attendance warnings do not alter salary amounts or payment eligibility. Staff funding remains in the separate staff_payroll bucket. Existing individual GCash payment amount/date/reference/private proof/confirmation and Staff acknowledgement requirements remain intact. No attendance deductions, overtime, holiday pay, proration, automatic payment or automatic absence generation was added.

Forms use confirmation dialogs, per-form busy protection, success/validation feedback and labelled controls. Tables scroll within their own regions on mobile. Secondary button colors were corrected for the existing dark theme. My Salary and salary histories distinguish missing configuration, individual overrides, hub inheritance and prospective effective periods.

## Files

Added:

- `app/Services/HubStaffSalary.php`
- `app/Services/StaffAttendance.php`
- `app/Http/Controllers/StaffAttendanceController.php`
- `database/migrations/2026_10_30_000004_add_hub_salary_and_staff_attendance.php`
- `resources/views/logistics/partials/hub-salary-settings.blade.php`
- `resources/views/logistics/staff-attendance.blade.php`
- `resources/views/logistics/staff-attendance-detail.blade.php`
- `public/js/staff-attendance.js`
- `tests/Feature/HubSalaryAttendanceTest.php`
- This report.

Modified:

- `app/Services/HubHiring.php`: inheritance inside the existing account transaction.
- `app/Services/StaffSalary.php`: effective-period/source-aware historical salary selection and prospective individual revisions.
- `app/Services/StaffPayrollWorkspace.php`: matching SQL selection, missing-rate filtering and blank unavailable estimates.
- `app/Http/Controllers/StaffSalaryController.php`: hub actions/history, company-scoped defaults and bounded attendance summaries.
- `routes/web.php` and `resources/views/logistics/partials/sidebar.blade.php`.
- `resources/views/logistics/staff-salary.blade.php`, `staff-salary-history.blade.php`, `my-salary.blade.php`.
- `public/css/staff-payroll.css` and `public/js/logistics-finance-feedback.js`.
- `tests/Feature/LogisticsAuthorizationTest.php`: route enumeration uses a valid clock action so middleware authorization is actually exercised.
- `tests/Support/cash-desk-mysql.php`: isolated attendance mode and concurrent action checks.

Reconciliation, COD, Seller settlement and Rider payroll services were not modified by this feature.

## Routes

All paths below start with `/logistics`. Admin routes remain within the existing logistics.admin group; Staff actions additionally enforce fresh Staff ownership in the service.

| User | Method and path | Purpose |
| --- | --- | --- |
| Admin | POST `/finance/staff/hub-salary` | Prospective hub default |
| Admin | POST `/finance/staff/use-hub` | Explicit individual-to-hub selection |
| Admin | GET `/finance/staff/hubs/{hub}` | Hub revision history |
| Admin | GET `/attendance` | Filtered history/correction queue |
| Admin | GET `/attendance/export` | Private attendance CSV |
| Admin | POST `/attendance/marks` | Document leave/absence |
| Admin | POST `/attendance/corrections/{id}` | Decide correction |
| Admin | GET/POST `/attendance/records/{id}` | Audit view / documented review |
| Staff | GET `/my-attendance` | Own attendance |
| Staff | POST `/my-attendance/clock/{action}` | Server Time In/Out |
| Staff | POST `/my-attendance/corrections` | Own correction request |
| Staff | GET `/my-attendance/records/{id}` | Own audit view |

Existing salary configuration, bulk preview/execution, funding, payment and acknowledgement routes are retained. New writes have route throttles and CSRF protection.

## Migration and deployment

Added migration 2026_10_30_000004 creates hub-rate history, attendance, corrections and attendance audit tables, plus additive salary source/effective-period columns. Existing salary rows default to individual overrides. Active-shift and pending-correction unique keys, day-mark uniqueness, foreign keys and period/queue indexes support the mutation/query paths. It does not backfill invented shifts, obligations or salary rates. Destructive rollback is deliberately refused to protect history.

Read-only migrate:status confirms salary migrations 000001 and 000002 are installed. Payroll index migration 000003 and this new 000004 remain pending. The unrelated 29 October 000006-000009 migrations remain pending and were not applied. Migration 000004 requires the original salary migration and existing users/hubs; 000003 is an independent performance migration and should remain before 000004 in deployment order.

Proposed only, after explicit approval, backup and staging validation:

```powershell
php artisan migrate --path=database/migrations/2026_10_30_000003_index_staff_payroll_workspace.php --force
php artisan migrate --path=database/migrations/2026_10_30_000004_add_hub_salary_and_staff_attendance.php --force
php artisan view:clear
```

These commands were not executed on the configured database. Deploy services/controllers/views/assets together in a controlled maintenance window. Review real DDL locking and older pending migrations separately. Before 000004 is installed, existing payroll remains usable; new attendance endpoints report that installation is required and hub settings remain hidden. Configure approved real hub rates only through authorized Admin acceptance after rollout.

## Validation

- Combined regression: 260 tests / 3,306 assertions passed, zero errors/failures, across HubSalaryAttendanceTest, StaffPayrollWorkspaceTest, LogisticsReconciliationTest, RiderCompensationTest, CodSellerPayoutTest, LogisticsCashDeskTest, NavigationTest, LogisticsAuthorizationTest and HubHiringTest. The new suite extends the 38 existing salary cases with 15 feature cases; aggregate counts include inherited regression cases.
- Focused salary/workspace/navigation run before the final original-clock-out regression: 110 tests / 825 assertions passed after final view/export refinements.
- Fixtures exercise prospective hub defaults, overrides, historical snapshots, real approval/transfer hooks, missing rates, foreign companies, stale revisions, audit rollback, server clocks, duplicate actions, overnight work, explicit missing-out review, corrections (including reopening a shift without replacing its first original clock-out), overlap rejection, leave/absence, suspended Staff, payroll separation and 500 additional Staff with bounded pagination. Existing workspace regressions also exercise 100/500 Staff and historical obligations.
- Isolated MySQL attendance runner: four PASS groups, covering concurrent hub configuration, Time In/Out, correction request/approval, enhanced workspace SQL and period attendance aggregation. Original evidence remained intact and no attendance operation created obligations or treasury movements.
- Full isolated MySQL finance runner: 28 PASS groups, including existing payroll, reservation, payment, acknowledgement, custody and bulk races. Runners copied schema only into randomly named test databases, used synthetic records, and dropped those databases afterward.
- Local headless Chrome at 1440px/390px checked Admin attendance, My Attendance, salary workspace, attendance audit and hub history: no page overflow or unlabelled visible inputs; checked controls at least 44px high; correct expanded navigation; cancelled confirmations prevented submission without busy-locking forms. These were synthetic HTTP-rendered previews, not live accounts.

PHP/JavaScript syntax checks passed.

Private artifacts: `storage/app/private/hub-attendance-final.xml/json`, `hub-attendance-ui-final.xml/json`, `hub-attendance-mysql.txt`, `hub-attendance-finance-mysql.txt`, `hub-attendance-browser.txt`; synthetic HTML/screenshots under `storage/app/private/logistics-ui-review`.

## Attendance detail UI refinement

Replaced the raw JSON audit presentation with a responsive Staff/hub summary, separate Time In/Out cards, a seconds-accurate recorded duration and a readable activity timeline. Times consistently display in PHT with seconds. Corrected entries retain expandable original clock times and show previous/reviewed values with the recorded reason. Names use company-scoped joins with account-ID fallbacks. Audit data, permissions, financial logic and database schema are unchanged.

Validation: two focused presentation/authorization tests passed (22 assertions), and two original-clock/authorization regressions passed (15 assertions). Synthetic Chrome previews at 1440px and 390px had no page overflow. This refinement changes only StaffAttendanceController, the attendance detail Blade view, scoped payroll CSS, one presentation test and this report. No real attendance record was modified.

## Remaining limits

No schedule/rest-day calendar, attendance-based pay policy, employment-date proration, automatic shift closing, attendance device/location verification or historical corrective-payment workflow was inferred. Admin must verify scheduling evidence before recording absence and investigate open shifts rather than treating them as worked hours. Existing company-transfer/employment exceptions still need policy review.

Production migration locks, production-volume latency, comprehensive screen-reader acceptance and actual GCash delivery were not certified. Attendance page rows represent recorded shifts/day marks or no entry in a selected range, not a generated daily roster. No whole-repository-green claim is made.
