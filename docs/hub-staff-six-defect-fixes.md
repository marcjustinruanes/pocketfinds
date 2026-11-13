# Hub Staff: six confirmed defect corrections

12 November 2026. Follow-up to the [Hub Staff functional audit](hub-staff-functional-audit.md).

**All six scoped findings are corrected and verified in the tested scenarios.** Final results: **1,344 executions across 51 relevant suites: 1,317 PASS, 0 FAIL, 0 ERROR, 27 pre-existing SKIPPED; 15,608 assertions**. Actual Chromium verification: **9 PASS, 0 FAIL**. All seven new disposable MySQL races and four existing MySQL verifiers passed.

No new feature/module, database migration, production data update or historical repair was performed. The original audit remains a historical record; this report records its six corrections, not blanket production acceptance of every previously unexecuted function.

## Corrections

| Finding | Implemented correction | Verification |
|---|---|---|
| HS-F01: private proof hub scope | Keep fresh approval/role/company checks. Hub Staff must also have an operational same-company hub and the parcel must fall within the existing `LogisticsAccess::shipments()` route scope. Reject unrelated, foreign, closed and revoked Staff access with 404. Preserve authorized Admin, order-participant, origin, destination and intermediate-route hub access. | Focused backend tests and real browser requests: permitted Staff/Admin 200; unrelated and foreign Staff 404. |
| HS-F04: attempts and exhaustion | Move the existing guarded failure recording into `RiderDeliveryOffers::recordFailure()`, called by Rider and logistics status handlers under their existing operational locks. Validate current authorization, attempt token, order/assignment state and existing custody/reassignment restrictions; increment once and record history/notifications atomically. Share the existing refused/three-attempt exhaustion predicate with retry validation and monitor/scanner guidance. | Counts 1/2/3; fourth retry 422; same/delayed token 409; conflicting Staff/Rider failures count once; completed delivery cannot be overwritten. Audit failure rolls back counter/history/notifications. |
| HS-F02: maintenance replay | The existing form submits the intended `is_available` value. The action saves that value under shared hub/user/vehicle locks rather than inverting current state. Identical retries are harmless in either direction. | Actual form plus repeated requests; both-direction backend tests; real MySQL duplicate-maintenance and duplicate-available races. |
| HS-F03: vehicle company boundary | Both the list and update require the actor's company and assigned hub. Mutation rechecks approved Staff, operational hub and platform approval under locks. | Inconsistent synthetic foreign-company/own-hub-pointer record is hidden and returns 404; wrong hub/pending vehicle denied; revoked actor denied; audit rollback preserved. |
| HS-F06: monitor actions | Display status forms and delivery-assignment links only for company Admin or Staff at the actual destination hub, using the existing province-aware match. Keep parcel-route visibility and backend enforcement. Omit already-exhausted retry actions. | Origin sees the parcel without a mutation form; destination Staff and Admin retain authorized forms. Wrong-hub backend write still 403. |
| HS-F05: alert links | Staff pickup alerts point to existing My Hub and explicitly say approval is awaiting Logistics Admin. Admin retains its Pickup Requests review link. Notification preferences remain respected. | Actual Staff alert click opens authorized dashboard; Admin link remains; backend tests cover preferences. |

Failure recording retains rider custody, assignment evidence and existing compensation snapshots. It does not mark parcels returned, fabricate receipt, restore stock, collect COD or settle money. Existing Staff authority to record supported pre-collection failure remains; that path does not invent an out-for-delivery timestamp. Ordinary rider eligibility and the combined three-job limit remain in the existing retry/offer checks. Four-wheel transfer rules are unchanged.

## Request and response consistency

Staff failure forms and scanner requests now submit the **existing shipment attempt token**. Missing/malformed tokens return 422; a stale/tampered attempt returns 409. This prevents a delayed failure submission from consuming a later retry, including when timestamps fall in the same second because the counter participates in the token.

The scanner's real positive action exposed another part of HS-F04: HTTP 302 after a fetch PATCH repeated PATCH against the scan page, producing 405 even though the failure had saved. Successful logistics status updates now redirect with **HTTP 303**, which follows with GET. Actual scanner failure **and retry** now refresh the visible status correctly. HTML forms retain the redirect workflow. The browser verifies persisted state, current token and duplicate rejection, not just navigation.

The vehicle form now requires an explicit boolean target; a request without it is rejected rather than toggling unpredictably. Distinct opposing commands remain serialized writes of their intended target; no new vehicle approval policy was introduced.

## Modified files

Application files:

- [DeliveryProofController.php](../app/Http/Controllers/DeliveryProofController.php): Staff hub/parcel proof authorization.
- [LogisticsController.php](../app/Http/Controllers/LogisticsController.php): shared failure call, token/scanner metadata, exhaustion guidance, safe status redirect, scoped vehicle list and locked explicit availability update.
- [RiderController.php](../app/Http/Controllers/RiderController.php): delegate the existing failure mutation to the shared guard; Rider dashboard/templates unchanged.
- [RiderDeliveryOffers.php](../app/Services/RiderDeliveryOffers.php): shared guarded failure recording and exhaustion predicate.
- [parcel-scan.js](../public/js/parcel-scan.js): include the current attempt token when marking failure.
- [monitor.blade.php](../resources/views/logistics/monitor.blade.php): authorized controls, attempt token and exhaustion alignment.
- [hub-vehicles.blade.php](../resources/views/logistics/hub-vehicles.blade.php): explicit intended availability field.
- [topbar.blade.php](../resources/views/logistics/partials/topbar.blade.php): role-appropriate alert destination/wording.

Verification files:

- [HubStaffDefectCorrectionsTest.php](../tests/Feature/HubStaffDefectCorrectionsTest.php), new focused regression suite covering all six findings, failure/custody/money preservation, authorization, stale/duplicate submissions and rollback.
- [hub-staff-corrections-mysql.php](../tests/Support/hub-staff-corrections-mysql.php), new disposable real-concurrency verifier.
- [ReverseLogisticsCorrectionsTest.php](../tests/Feature/ReverseLogisticsCorrectionsTest.php), valid current failure token fixture and strict HTTP 303 retry expectation, retaining state/stale-token assertions.
- [RemainingLogisticsAuditTest.php](../tests/Feature/RemainingLogisticsAuditTest.php), valid current attempt tokens for existing positive Staff/Admin failure submissions.
- `docs/hub-staff-six-defect-fixes.md`, this report.

Private runners, browser harnesses, synthetic SQLite data, screenshots, raw outputs and detailed results remain under `storage/app/private/hub-staff-six-fixes/` for reproducibility. They are not application modules or deployment routes.

**Migrations: none.** Snapshot comparison against 794 pre-existing protected files identifies only the eight application files and two existing tests listed above as changed. No financial calculations/settings, existing migrations, configuration, transfer implementation, or unrelated module files changed. See [scope-check.json](../storage/app/private/hub-staff-six-fixes/scope-check.json). Earlier workspace changes were preserved.

## Tests and regressions

The three primary target suites total 101 passing executions in their final per-suite results. The new 33-case suite includes inherited operational recovery checks as well as the new focused cases; totals are executions, not distinct business functions.

Covered corrections include:

- Authorized proof participants/Admin/route hubs; unrelated/same-named-other-province/foreign/closed/suspended Staff denial.
- Staff pre-collection and dispatched failures, exact attempt counts, third-failure exhaustion, retained custody, no premature return or collection, unchanged compensation snapshots.
- Current/missing/tampered/stale attempt tokens; delayed duplicate after retry; authoritative current assignment requirement.
- Failure audit rollback and notification/history duplicate prevention.
- Explicit maintenance in both directions and repeated requests; company/hub/platform/approval guards; failed-audit rollback.
- Authorized monitor forms versus retained route visibility; Staff versus Admin alert links and preference settings.

Broader regressions covered pickup/delivery offers, barangay routes, reassignment, transfer custody/partial receipts, recovery/RTS, COD/cash desk, treasury/Seller and rider payouts, Staff payroll/attendance, reconciliation, commissions, GCash, private documents/media, hiring and account security. No assertions were removed, skipped or loosened to obtain passes.

| Relevant suite | Executions | PASS | FAIL | ERROR | Prior SKIPPED |
|---|---:|---:|---:|---:|---:|
| HubStaffDefectCorrectionsTest | 33 | 33 | 0 | 0 | 0 |
| LogisticsAdminDefectCorrectionsTest | 59 | 59 | 0 | 0 | 0 |
| NavigationTest | 7 | 7 | 0 | 0 | 0 |
| LogisticsAuthorizationTest | 37 | 37 | 0 | 0 | 0 |
| FulfillmentWorkflowTest | 7 | 7 | 0 | 0 | 0 |
| LogisticsFinanceFoundationTest | 10 | 10 | 0 | 0 | 0 |
| LogisticsShippingTest | 23 | 23 | 0 | 0 | 0 |
| LogisticsCashDeskTest | 25 | 25 | 0 | 0 | 0 |
| LogisticsReconciliationTest | 19 | 19 | 0 | 0 | 0 |
| CodCollectionTest | 14 | 14 | 0 | 0 | 0 |
| CodSellerPayoutTest | 23 | 23 | 0 | 0 | 0 |
| RiderCompensationTest | 25 | 25 | 0 | 0 | 0 |
| RiderWorkbookTest | 47 | 20 | 0 | 0 | 27 |
| OperationalRecoveryTest | 18 | 18 | 0 | 0 | 0 |
| OperationalAuditTest | 21 | 21 | 0 | 0 | 0 |
| ParcelCustodyTest | 27 | 27 | 0 | 0 | 0 |
| HubTransferWorkflowTest | 25 | 25 | 0 | 0 | 0 |
| HubTransferMigrationTest | 4 | 4 | 0 | 0 | 0 |
| RiderAssignmentWorkflowTest | 9 | 9 | 0 | 0 | 0 |
| RiderBarangayCoverageTest | 12 | 12 | 0 | 0 | 0 |
| ReverseLogisticsCorrectionsTest | 41 | 41 | 0 | 0 | 0 |
| ReverseLogisticsTransferHoldTest | 26 | 26 | 0 | 0 | 0 |
| RemainingLogisticsAuditTest | 27 | 27 | 0 | 0 | 0 |
| RtsWorkflowTest | 30 | 30 | 0 | 0 | 0 |
| IssueInvestigationTest | 21 | 21 | 0 | 0 | 0 |
| PhoneScannerTest | 9 | 9 | 0 | 0 | 0 |
| MobileScannerTest | 9 | 9 | 0 | 0 | 0 |
| HubHiringTest | 25 | 25 | 0 | 0 | 0 |
| HubHiringMigrationTest | 1 | 1 | 0 | 0 | 0 |
| PersonnelOnboardingTest | 13 | 13 | 0 | 0 | 0 |
| RiderActivationTest | 14 | 14 | 0 | 0 | 0 |
| HubAddressTest | 12 | 12 | 0 | 0 | 0 |
| CompanyOwnershipTest | 16 | 16 | 0 | 0 | 0 |
| StaffSalaryTest | 38 | 38 | 0 | 0 | 0 |
| StaffPayrollWorkspaceTest | 52 | 52 | 0 | 0 | 0 |
| HubSalaryAttendanceTest | 54 | 54 | 0 | 0 | 0 |
| StaffCashNotificationsTest | 27 | 27 | 0 | 0 | 0 |
| CommissionWorkflowFixTest | 56 | 56 | 0 | 0 | 0 |
| CommissionMessagingTest | 65 | 65 | 0 | 0 | 0 |
| CommissionAccountTest | 65 | 65 | 0 | 0 | 0 |
| CommissionEnrollmentTest | 32 | 32 | 0 | 0 | 0 |
| CommissionAutomaticReconciliationTest | 50 | 50 | 0 | 0 | 0 |
| CommissionStagingEndToEndTest | 58 | 58 | 0 | 0 | 0 |
| GcashPurchaseTest | 74 | 74 | 0 | 0 | 0 |
| PaymentReportingTest | 24 | 24 | 0 | 0 | 0 |
| SensitiveDocumentsTest | 4 | 4 | 0 | 0 | 0 |
| MediaLifecycleTest | 12 | 12 | 0 | 0 | 0 |
| ApplicationCorrectionTest | 9 | 9 | 0 | 0 | 0 |
| ApplicationRejectionTest | 5 | 5 | 0 | 0 | 0 |
| PasswordResetTest | 8 | 8 | 0 | 0 | 0 |
| RegistrationValidationTest | 22 | 22 | 0 | 0 | 0 |

Every outcome is in [per-test-results.csv](../storage/app/private/hub-staff-six-fixes/per-test-results.csv); [final-results.json](../storage/app/private/hub-staff-six-fixes/final-results.json) contains totals and suite results. The 27 existing workbook skips remain explicitly unexecuted. No new skips were added.

## Browser verification

Real Chromium 156.0.8078.4 used the Laravel HTTP kernel at `127.0.0.1:18797`, a separate synthetic SQLite database, private disks/sessions/cache, and separate origin/destination/unrelated/foreign Staff, Admin and rider accounts. External requests were blocked; mail/queue and geographic responses were test-only.

| Browser scenario | Result |
|---|---|
| HS-F01 private proof preserves permitted actors and denies unrelated/foreign hubs | PASS |
| HS-F02 actual maintenance form and repeated request idempotency | PASS |
| HS-F03 vehicle list and mutation require company plus hub | PASS |
| HS-F06 monitor action authorization preserves visibility and valid permissions | PASS |
| HS-F04 actual failure/retry forms exhaustion and delayed duplicate protection | PASS |
| HS-F04 scanner lookup matches exhaustion guard | PASS |
| HS-F05 actual Staff notification link opens an authorized page | PASS |
| Mobile maintenance and monitor layout sanity | PASS |
| HS-F04 actual scanner Mark Failed sends attempt token and counts once | PASS |

Evidence: [browser-results.json](../storage/app/private/hub-staff-six-fixes/browser-results.json), private browser scripts and mobile screenshots. The scanner test additionally exercises both the real prompt/JavaScript failure action and its retry, verifying HTTP 303, refreshed status and unchanged retry counter. No physical scanner or phone acceptance is claimed. The temporary QA server has been stopped.

## Disposable MySQL concurrency

The new verifier observed actual MySQL lock waits in all seven races:

1. Staff versus Staff failure: one success, one 409, one counted attempt/history.
2. Staff versus Rider failure: one success, one 409, one counted attempt/history.
3. Rider versus Staff failure: the same protection with reversed order.
4. OTP/proof-completed delivery versus Staff failure: delivery retained, failure 409, no attempt increment.
5. Two maintenance requests: both succeed, final maintenance state retained.
6. Two availability requests: both succeed, final available state retained.
7. Maintenance versus company-vehicle rider acceptance: maintenance saved; ineligible acceptance 422 with no ownership change.

The existing ordinary-assignment, reassignment, reverse-logistics and hub-transfer MySQL verifiers also passed. They cover combined capacity, duplicate acceptance/confirmation, delivery/failure conflicts, retries, dispatch/receipt/discrepancy races, partial/final transfer progress, notification deduplication/links and fourteen transfer role/page checks.

All verifiers enforce local/name-guarded disposable schemas, copy definitions only, and remove their temporary databases. No live customer/financial rows were copied or modified. Raw outputs: [new corrections](../storage/app/private/hub-staff-six-fixes/mysql-corrections.txt), [assignment](../storage/app/private/hub-staff-six-fixes/mysql-assignment.txt), [reassignment](../storage/app/private/hub-staff-six-fixes/mysql-reassignment.txt), [reverse logistics](../storage/app/private/hub-staff-six-fixes/mysql-reverse.txt), [transfers](../storage/app/private/hub-staff-six-fixes/mysql-transfers.txt).

## Commands and verification history

```powershell
python storage/app/private/hub-staff-six-fixes/run_suites.py
# Each full suite, forced to an isolated database:
$env:APP_ENV='testing'; $env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; $env:DB_URL=''
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/<Suite>.php --log-junit <private-output.xml>
python storage/app/private/hub-staff-six-fixes/final_reruns.py

# Private wrapper explicitly selects synthetic SQLite/disks/session/cache:
php -d memory_limit=512M -S 127.0.0.1:18797 -t public storage/app/private/hub-staff-six-fixes/browser-router.php
python storage/app/private/hub-staff-six-fixes/browser_verify.py
python storage/app/private/hub-staff-six-fixes/browser_scanner.py

$env:APP_ENV='local'; $env:DB_CONNECTION='mysql'; $env:DB_URL=''
php tests/Support/hub-staff-corrections-mysql.php
php tests/Support/rider-assignment-mysql.php
php tests/Support/reassignment-mysql.php
php tests/Support/reverse-logistics-mysql.php
php database/verify_hub_transfers.php
```

Full commands and exit history are in [suite-progress.json](../storage/app/private/hub-staff-six-fixes/suite-progress.json). Suites ran sequentially to avoid fake-storage interference. The 512 MB setting is process-only, not a configuration change. No final suite filter or skip flag was used. PHP syntax checks and scoped `git diff --check` passed.

Initial MySQL harness runs exposed incomplete fixture dependencies/required vehicle fields; the verifier was corrected without changing application validation. Scanner verification exposed and drove the HTTP 303 correction described above. After that correction, one existing regression's exact 302 expectation failed; it was updated to **strict 303**, retaining its persisted retry state and stale-token assertions, then the full 41-case suite passed. The prior failure is retained in private diagnostics. Final totals use each suite's latest completed result, not superseded diagnostic attempts.

The five affected authorization/recovery/status suites were rerun after the response correction. Final new and reverse-logistics MySQL races and actual scanner failure/retry were also verified after it. Browser execution required approved Windows subprocess escalation; requests remained limited to the private loopback server.

## Remaining limits and deployment

- No unresolved failure remains in the six corrected findings in the final tested scenarios.
- Old failure counters/custody records and inconsistent vehicle links were **not** repaired or backfilled. Existing legacy exceptions still need authorized operational review without fabricated evidence.
- Refresh open Staff monitor/scanner/vehicle pages after deployment so they submit current attempt tokens and explicit availability fields. Old/malformed requests are rejected safely. Deploy controller/service/view/JavaScript changes together and follow the existing asset/view-cache deployment process; no migrations or new settings are required.
- The original audit's unexecuted browser combinations, physical cameras/USB/native scanners, SMTP/CDN/hosted HTTPS/provider checks, accessibility/copy observations and business-policy restrictions remain separate acceptance items. Real payments and cash handling were not performed.
- The 27 prior skipped workbook cases remain pending. These corrections do not certify all 141 inventoried functions or replace production/manual acceptance.

**Scope result: six findings corrected, focused/regression/browser/MySQL verification passed, and production records and financial rules preserved.**
