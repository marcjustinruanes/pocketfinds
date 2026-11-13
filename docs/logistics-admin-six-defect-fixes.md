# Logistics Admin: six defect corrections

12 November 2026. Follow-up to the [functional audit](logistics-admin-functional-audit.md).

The six confirmed defects have been corrected. Final verification totals and individual suite results appear below. This report covers the requested corrections; it does not certify previously unexecuted operations or production deployment.

## Fixes

| Finding | Correction | Evidence |
|---|---|---|
| F01: private delivery proof | Reload the authenticated user. Logistics access requires current approval, recognized Admin/Hub Staff role, correct account type, no platform-admin flag, and a nonempty matching company. Preserve existing order participant/approved Staff access and private/no-store headers. Unauthorized access returns 404. | Backend tests cover revoked approval, invalid role, company boundaries, unrelated accounts, unauthenticated requests and permitted participants. Chromium verified approved 200, then suspended/invalid-role 404 in the same session. |
| F02: reassignment | Request and possession confirmation now reuse `RiderDeliveryOffers::canAssign()` and the existing company-rider/vehicle/parcel lock order. Enforce online status, approval, company, operational hub, ownership/available vehicle, leg-specific barangay coverage and fewer than three combined active pickup/delivery jobs. The dropdown uses shared eligibility and retains Staff's own-hub filter. | Both legs and both stages tested, including changed eligibility, full mixed capacity, eligible third slot, unavailable company vehicle, wrong-hub access, stale/duplicate confirmation and rollback. Actual forms and MySQL races passed. |
| F03: inert Search | Remove the nonfunctional topbar Search. Existing page-specific search/filter controls remain. | Actual desktop/mobile page checks: removed control, visible heading, no horizontal mobile overflow at the tested viewport. |
| F04: reply context | Save the selected `data-message-id` and submit it through the existing `reply_to_id` field. Cancel/success clears reply state. Backend conversation authorization remains unchanged. | Actual Reply persisted the original ID. Cancel saved null. Cross-thread reply returned 422 with no inserted message. |
| F05: filename injection | Use `textContent` for selected filenames. Build dynamic image/video previews with DOM elements and property assignment instead of interpolated HTML attributes. | Harmless HTML/onerror filename remained literal, with no injected element/execution. Dynamic image filename containing an attribute/onload marker remained literal alt text, with no handler/execution. Removal still worked. |
| F06: fleet labels | Associate the existing Hub and Vehicle Type labels with unique dropdown IDs. | Chromium verified both labels and working selection. |

Requests still do not change ownership. Seller pickup-possession confirmation and destination Hub Staff delivery-possession confirmation remain mandatory. Cancellation, physical custody, stale revision and financial snapshot protections remain. Four-wheel hub transfers retain their separate existing eligibility/exclusive-work rules; no ordinary three-job limit was added to transfers.

## Files changed

Application files:

- `app/Http/Controllers/DeliveryProofController.php`
- `app/Http/Controllers/LogisticsOperationsController.php`
- `app/Services/AssignmentReassignment.php`
- `app/Services/RiderDeliveryOffers.php`
- `resources/views/logistics/assignment-review.blade.php`
- `resources/views/logistics/partials/topbar.blade.php`
- `resources/views/logistics/messages.blade.php`
- `resources/views/logistics/fleet.blade.php`

Existing tests maintained:

- `tests/Feature/FulfillmentWorkflowTest.php`
- `tests/Feature/CompanyOwnershipTest.php`
- `tests/Feature/RiderCompensationTest.php`
- `tests/Feature/RiderWorkbookTest.php`
- `tests/Feature/OperationalRecoveryTest.php`

Added verification/documentation:

- `tests/Feature/LogisticsAdminDefectCorrectionsTest.php`
- `tests/Support/reassignment-mysql.php`
- `docs/logistics-admin-six-defect-fixes.md`

Private diagnostic artifacts are under `storage/app/private/logistics-admin-six-fixes/`: browser harness, synthetic SQLite data, screenshots, command runners, raw output, JUnit and per-test outcomes. They are not application routes/modules.

**Migrations: none added or changed. No production data, configuration, financial settings, calculations or business policies were changed.** Comparison with the audit's 794-file snapshot identifies exactly the eight application files and five existing test files listed above as changed; see [scope-check.json](../storage/app/private/logistics-admin-six-fixes/scope-check.json). The workspace's prior transfer, assignment and reverse-logistics changes were preserved.

## Existing failed/error tests

The baseline had 11 FAIL and one ERROR. All twelve cases were investigated; final results follow below. Application security/custody/financial validation was not relaxed.

| Baseline case | Root cause and test correction |
|---|---|
| `FulfillmentWorkflowTest::test_public_rider_and_staff_registration_store_documents_and_preserve_pending_roles` | Add required middle name, actual PDF resume, rider NBI image and current verified-email session. Assert the NBI file exists; exact private-file total becomes nine. Pending-role, company and hub assertions remain. |
| `FulfillmentWorkflowTest::test_registration_failure_after_upload_removes_files_and_invalid_hub_never_uploads` | Same valid registration baseline reaches the injected insert failure. Preserve expected 500, absent user, upload rollback and invalid-hub 422 assertions. |
| `RiderCompensationTest::test_whole_transfer_batch_earns_once_and_partial_receipt_earns_nothing` | Give both disposable parcels independent origin receipt witnesses through the existing fixture helper, and explicitly make the transfer rider online. Preserve partial-no-earning, final-once-only, exact configured custom compensation and dedup assertions. No historical production evidence was invented. |
| `RiderWorkbookTest::test_workbook_case`: `TC-RID-001`, `005`, `006`, `010`, `028`, `032` | Add required NBI evidence to the valid managed-rider baseline so the intended registration/boundary/list/access assertions execute. |
| Same method, `TC-RID-012` | Complete NBI baseline. Existing normalization accepts lowercase licences and saves uppercase; retain malformed-format rejection using a malformed separator, and explicitly test lowercase acceptance plus canonical persisted value. No application rule changed. |
| Same method, `TC-RID-014` | Use the existing one-month minimum with `addMonthNoOverflow()`: reject yesterday/today/day before minimum; accept exact minimum and following day. Positive boundaries additionally assert no session errors and a saved pending user. |
| `CompanyOwnershipTest::test_rejected_founder_reapplies_on_same_id_and_preserves_hubs` | Add middle name and fresh verified-email proof for each submission. Preserve same-ID reapplication, pending status, founder reservation and hub preservation assertions. |

`OperationalRecoveryTest` explicitly marks valid fixture riders online with `forceFill`, because availability is intentionally not mass assignable. The new tests separately simulate eligibility changes after a proposal. Original authorization, duplicate, exact-money, snapshot and rollback assertions were preserved.

The workbook's 27 existing skipped cases were not added, suppressed or relabelled PASS. Some activation-related skip descriptions are stale; actual activation is separately exercised by `RiderActivationTest`. The skipped cases remain outside this task's six fixes and need appropriate acceptance execution/maintenance.

Initial diagnostic outputs are retained. They exposed incomplete probe setup and the additional email/online/normalization preconditions above. A combined run exceeded the CLI's default 128 MB allocation; final runs use a process-only 512 MB limit. Suites run sequentially to avoid fake-storage interference. Final totals use each suite's latest completed verification, not superseded attempts. The new suite also repeats inherited recovery tests with route fixtures; counts represent test executions, not unique business functions.

## Final automated results

**1,311 executions: 1,284 PASS, 0 FAIL, 0 ERROR, 27 pre-existing SKIPPED; 15,256 assertions.**

| Suite | Executions | PASS | FAIL | ERROR | Existing SKIPPED |
|---|---:|---:|---:|---:|---:|
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

Every individual outcome and skipped reason is in [per-test-results.csv](../storage/app/private/logistics-admin-six-fixes/per-test-results.csv). Totals are in [final-results.json](../storage/app/private/logistics-admin-six-fixes/final-results.json); commands and exits are in [suite-progress.json](../storage/app/private/logistics-admin-six-fixes/suite-progress.json).

Regressions include normal pickup/delivery assignment, barangay coverage, transfers, reverse custody/recovery, COD collection/cash desk, treasury/Seller payouts, rider compensation, Staff payroll/attendance, commissions, GCash/payment reporting, private media/documents, registration and account security.

## Browser verification

Actual Chromium through Laravel's HTTP kernel, separate synthetic Admin/Hub Staff sessions, and disposable SQLite records. External requests were blocked and PSGC responses mocked. No real recipient/payment or production record was used.

Chromium 156.0.8078.4: 10 PASS, 0 FAIL.

| Scenario | Result |
|---|---|
| F01 private proof rejects revoked sessions | PASS |
| F03 topbar removal and mobile fit | PASS |
| F04 browser reply persists original ID | PASS |
| F04 cancelled reply saves no context | PASS |
| F04 backend thread authorization preserved | PASS |
| F05 selected filename is inert text | PASS |
| F05 dynamic attachment name cannot create handlers | PASS |
| F06 fleet dropdown accessible labels | PASS |
| F02 dropdown and forged request eligibility | PASS |
| F02 actual request and possession confirmation forms | PASS |

Evidence: [browser-results.json](../storage/app/private/logistics-admin-six-fixes/browser-results.json), [browser script](../storage/app/private/logistics-admin-six-fixes/browser_verify.py), and private screenshots. Early harness attempts required clicking the login field before filling and removing nonexistent timestamp columns from a disposable insert; these were harness corrections. The temporary server has been stopped.

## Real MySQL verification

All four verifiers passed. They enforce local, name-guarded disposable schemas, copy table definitions only, and remove their temporary databases. No live rows were copied or changed.

- **Reassignment: PASS.** Offer-first versus confirmation, confirmation-first versus offer acceptance, and duplicate confirmation. Each observed an actual MySQL row-lock wait. At two mixed jobs, one action succeeds, the other returns 422, and capacity remains exactly three. Duplicate confirmation gives one success, one 409, one confirmed event and one replacement compensation snapshot.
- **Normal assignment: PASS.** Competing pickup/delivery acceptance stays at three; duplicate acceptance records one history event.
- **Reverse logistics: PASS.** Identical/conflicting failures, proof/OTP delivery versus failure, and retry versus acceptance preserve authoritative transitions, counters, history and capacity.
- **Hub transfers: PASS.** Request, approval, dispatch, rider pickup, partial/final receipt; duplicate dispatch/receipt/discrepancy races; notification deduplication; authorized deep links; fourteen role/page checks. Existing migrations were exercised only on disposable schema copies.

Raw output: [reassignment](../storage/app/private/logistics-admin-six-fixes/mysql-reassignment.txt), [assignment](../storage/app/private/logistics-admin-six-fixes/mysql-assignment.txt), [reverse logistics](../storage/app/private/logistics-admin-six-fixes/mysql-reverse.txt), [transfers](../storage/app/private/logistics-admin-six-fixes/mysql-transfers.txt).

## Commands

Final automated invocation for each suite in the results table:

```powershell
$env:APP_ENV='testing'; $env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; $env:DB_URL=''
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/<Suite>.php --log-junit storage/app/private/logistics-admin-six-fixes/<Suite>.xml
```

Actual sequential runner: `python storage/app/private/logistics-admin-six-fixes/run_suites.py`. `finish_tests.py` waits for that runner, then verifies the final correction suite, LogisticsAuthorizationTest and OperationalRecoveryTest again after the last fixture/own-hub filter changes. No test filter or skip flag was used.

```powershell
php -d memory_limit=512M -S 127.0.0.1:18795 -t public storage/app/private/logistics-admin-six-fixes/browser-router.php
python storage/app/private/logistics-admin-six-fixes/browser_verify.py
$env:APP_ENV='local'; $env:DB_CONNECTION='mysql'; $env:DB_URL=''
php tests/Support/reassignment-mysql.php
php tests/Support/rider-assignment-mysql.php
php tests/Support/reverse-logistics-mysql.php
php database/verify_hub_transfers.php
```

PHP syntax checks passed for the four changed application PHP files, new correction test and new MySQL verifier. Scoped `git diff --check` passed. Browser subprocess execution required an approved Windows sandbox escalation; browser traffic remained limited to loopback.

## Remaining limits

- Final outcome of the six fixes: see verification totals above. No new confirmed application defect was exposed by correcting the old fixtures.
- The 27 existing skipped workbook cases remain unexecuted. Previously pending browser acceptance across the 27 Admin areas remains pending; this correction task does not certify every control.
- Physical camera/scanner/phone behavior, hosted HTTPS/CDNs, SMTP, payment providers and real money movement were not tested. Prior legacy custody exceptions were not repaired or recounted; historical evidence was not fabricated.
- Existing financial calculations, rate policies, compensation snapshot rules, COD/GCash/commissions and four-wheel transfer rules were preserved and covered by the stated regressions.

Production sign-off still depends on outstanding manual/device/deployment and legacy-custody review from the original audit.
