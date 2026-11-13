# PocketFinds Hub Staff functional audit

12 November 2026. **Overall result: FAIL for unrestricted operational acceptance.** Six confirmed findings remain. This was an audit only; no fixes were implemented.

The review covers all 22 existing Hub Staff areas, with a **141-entry function register**, current route inventory and rendered-control register. It distinguishes executed behavior from source inspection, unexecuted UI combinations and unavailable hardware. A route/controller existing is not counted as functional proof.

## Results and scope

- Fresh run of 50 existing suites: **1,311 executions, 1,284 PASS, 0 FAIL, 0 ERROR, 27 pre-existing SKIPPED; 15,256 assertions**.
- Additional disposable negative audit probes: **6 FAIL, 0 ERROR**. These failures reproduce the findings; they were not suppressed or converted to passes.
- Actual Chromium 156.0.8078.4: **26 functional scenarios, 23 PASS, 3 FAIL**. Browser failures concern unrelated-hub private proof, maintenance retries and an Admin-only notification link.
- Both Origin and Destination Staff opened 16 interface contexts each, at 1440 px and 390 px: **32 successful page checks and 64 viewport checks**, with no document-level horizontal overflow detected. Two representative mobile screenshots were visually reviewed; this is not pixel/accessibility certification of every screen.
- A separate DOM inventory captured **97 forms, 425 buttons, 176 fields and 854 links** across 32 contexts. Repeated controls across accounts/pages are counted separately. Capturing a control does not certify its action: the control register explicitly leaves unexecuted interactions pending.
- Current routes: **148 logistics entries: 87 Admin-only and 61 shared**, plus ten mobile API and ten selected shared account/evidence entries. Internal service guards further restrict shared entries.
- Fresh disposable MySQL reassignment, reverse-logistics and hub-transfer verifiers passed, including actual row-lock contention. No dedicated vehicle-toggle or attendance MySQL concurrency test was performed.

The baseline automated suites remain green despite the six new negative findings. This demonstrates missing coverage; it does not override the reproduced failures.

Evidence registers:

- [Every inventoried function and classification](../storage/app/private/hub-staff-functional-audit/function-results.csv)
- [Rendered controls, fields and action-verification limits](../storage/app/private/hub-staff-functional-audit/rendered-control-register.csv)
- [Route permissions and verification limits](../storage/app/private/hub-staff-functional-audit/route-results.csv)
- [Every automated test outcome](../storage/app/private/hub-staff-functional-audit/automated-per-test-results.csv)
- [Browser scenarios and observations](../storage/app/private/hub-staff-functional-audit/browser-final-results.json)
- [Negative probe results](../storage/app/private/hub-staff-functional-audit/probe-results.json)

`PASS` means the stated scenario has executed assertions at the identified layer; it does not certify every permutation or every button. `FAIL` means an expected behavior was reproduced as incorrect. `NEEDS MANUAL VERIFICATION` means the full identified interaction/provider/state combination was not executed. `BLOCKED` means a required device or an intentionally unsupported workflow prevents that scenario. Area results roll up their functions: a defect makes the area FAIL; incomplete acceptance makes it NEEDS MANUAL VERIFICATION.

## Isolation and accounts

The browser used the actual Laravel HTTP kernel on `127.0.0.1:18796`, a private SQLite database containing exclusively synthetic QA fixtures, separate file sessions, private disks, file cache with locks, test mail and synchronous test queue behavior. External browser requests were blocked; PSGC responses were deterministic fixtures. The previous audit's synthetic fixtures were copied into this audit's own database; customer/financial production rows were not copied.

Separate browser accounts:

| Account | Company/hub | Purpose |
|---|---|---|
| `qa_origin` | Alpha / Santa Cruz, Laguna | Origin operations and own account/attendance |
| `qa_destination` | Alpha / Pila, Laguna | Incoming transfers, receipt and account isolation |
| `qa_peer` | Alpha / same origin hub | Separate Staff session and suspension check |
| `qa_unrelated` | Alpha / Pagsanjan, Laguna | Unrelated same-company hub access |
| `qa_foreign_staff` | Foreign QA / its own hub | Cross-company denial |
| `qa_admin` | Alpha Admin | Authorized approval/dispatch setup between Staff operations |
| Synthetic pickup/delivery/transfer riders | Appropriate company/hub | Offer choices and legitimate transfer-pickup setup |

Only synthetic records were mutated. No real payments, live remittances, messages to real recipients or production role changes occurred. MySQL scripts copied table definitions into name-guarded disposable databases, exercised their fixtures, then removed those schemas. Existing migrations ran only in disposable environments.

SHA-256 comparison found **zero changes across 794 pre-existing application, existing-test, migration, configuration and manifest files**, including `.env`. See [protected-after-check.json](../storage/app/private/hub-staff-functional-audit/protected-after-check.json). Only this documentation and private audit artifacts were created. The temporary QA server has been stopped.

## All functional areas

| Area | Classification | Executed evidence / limits |
|---|---|---|
| 1. My Hub dashboard | **FAIL** | LogisticsAuthorizationTest; browser 32-page sweep |
| 2. Seller pickup rider offers | **PASS** | RiderAssignmentWorkflowTest; RiderBarangayCoverageTest; browser pickup offer |
| 3. Origin hub receipt and sorting | **PASS** | ParcelCustodyTest; LogisticsAuthorizationTest; browser scanner receipt |
| 4. Hub-transfer requests | **NEEDS MANUAL VERIFICATION** | HubTransferWorkflowTest; MySQL transfer verifier; browser transfer request |
| 5. Incoming hub-transfer receipt | **PASS** | HubTransferWorkflowTest; MySQL transfer verifier; browser partial/final receipts/discrepancy |
| 6. Buyer delivery rider offers | **PASS** | RiderAssignmentWorkflowTest; RiderBarangayCoverageTest; browser delivery offer |
| 7. Assignment evidence and possession review | **NEEDS MANUAL VERIFICATION** | LogisticsAdminDefectCorrectionsTest; OperationalRecoveryTest |
| 8. Scan Parcel and phone relay | **NEEDS MANUAL VERIFICATION** | PhoneScannerTest; browser scanner and phone relay |
| 9. Mobile scanner API | **NEEDS MANUAL VERIFICATION** | MobileScannerTest; PhoneScannerTest |
| 10. Live Monitor and limited status operations | **FAIL** | LogisticsAuthorizationTest; ReverseLogisticsCorrectionsTest; negative audit probes |
| 11. Delivery History | **NEEDS MANUAL VERIFICATION** | LogisticsAuthorizationTest history/filter/pagination; browser page/filter checks |
| 12. Parcel issues and investigations | **NEEDS MANUAL VERIFICATION** | IssueInvestigationTest; ReverseLogisticsCorrectionsTest; browser issue lifecycle |
| 13. Suspended-rider physical parcel recovery | **NEEDS MANUAL VERIFICATION** | OperationalRecoveryTest; ReverseLogisticsCorrectionsTest; ReverseLogisticsTransferHoldTest |
| 14. Return-to-seller custody | **NEEDS MANUAL VERIFICATION** | RtsWorkflowTest; ReverseLogisticsCorrectionsTest |
| 15. Cash Desk and actual rider remittance counts | **NEEDS MANUAL VERIFICATION** | LogisticsCashDeskTest; CodCollectionTest; StaffCashNotificationsTest |
| 16. Suspended-rider cash recovery submissions | **NEEDS MANUAL VERIFICATION** | OperationalRecoveryTest; LogisticsCashDeskTest |
| 17. Authorized cash transfers and treasury release | **NEEDS MANUAL VERIFICATION** | LogisticsCashDeskTest; CodSellerPayoutTest; StaffCashNotificationsTest |
| 18. Hub Vehicles | **FAIL** | browser maintenance retry and wrong-hub checks; negative vehicle probes |
| 19. My Attendance | **PASS** | HubSalaryAttendanceTest; browser clock/correction/isolation |
| 20. My Salary and receiving account | **NEEDS MANUAL VERIFICATION** | StaffSalaryTest; StaffPayrollWorkspaceTest; browser own receiving account |
| 21. Messages, attachments and notifications | **FAIL** | MediaLifecycleTest; LogisticsAuthorizationTest; browser messages/attachments/notifications |
| 22. My Account, security and private evidence | **FAIL** | SensitiveDocumentsTest; LogisticsAuthorizationTest; browser password/suspended session/privacy probe |

The [141-entry register](../storage/app/private/hub-staff-functional-audit/function-results.csv) contains each existing inventory bullet plus explicit security/duplicate checks: **100 PASS, 9 FAIL, 29 NEEDS MANUAL VERIFICATION and 3 BLOCKED**. Multiple failing functions can refer to one finding; there are six distinct confirmed findings, not nine independent defects. The older inventory's separate-reassignment-validation statement was updated in the register to reflect the completed November 12 shared-eligibility correction.

## Confirmed findings, ranked by severity

### HS-F01 — HIGH: private proof bypasses Staff hub scope

An approved Staff account at a different, unrelated Alpha hub cannot look up the parcel through the scanner (**404**) but can retrieve its private delivery proof using the shipment URL (**200**). Chromium received the synthetic 68-byte PNG. A separate HTTP probe reproduces this.

`DeliveryProofController::show()` checks current approval, recognized logistics role and matching company, but does not constrain Hub Staff to shipments touching their assigned hub. Those restrictions already exist in `LogisticsAccess::shipments()` and the operational interfaces. Knowing a shipment UUID is not authorization.

Affected files: [DeliveryProofController.php](../app/Http/Controllers/DeliveryProofController.php), existing policy in [LogisticsAccess.php](../app/Services/LogisticsAccess.php).

Recommended correction: apply the existing Staff parcel/hub policy before streaming, while preserving approved Admin and legitimate order-participant access. Review existing copied links against that same policy. No change to proof storage or new module is required.

This is the pre-existing company-wide Staff evidence exception noted in the older inventory. The six Admin corrections preserved that scope and fixed suspended-Admin access; this audit does not misreport a regression in the suspension fix.

Evidence: [proof-scope.json](../storage/app/private/hub-staff-functional-audit/proof-scope.json), browser failure and `test_hub_audit_unrelated_hub_must_not_read_private_proof`.

### HS-F04 — HIGH: Staff failures bypass attempt counting and exhaustion

Using the existing Staff status operation to record an actual failed delivery updates shipment status, reason and failure time, but leaves `delivery_attempts` unchanged. The probe performed **four failure/retry cycles; the counter remained zero in every cycle**. The retry guard therefore continued admitting attempts that the rider-recorded path would count toward exhaustion.

`LogisticsController::updateStatus()` omits the increment performed by `RiderController::markFailed()`. `RiderDeliveryOffers::assertRetryEligible()` trusts that counter.

Affected files: [LogisticsController.php](../app/Http/Controllers/LogisticsController.php), existing failure mutation in [RiderController.php](../app/Http/Controllers/RiderController.php), retry guard in [RiderDeliveryOffers.php](../app/Services/RiderDeliveryOffers.php).

Recommended correction: reuse the existing guarded actual-failure mutation so attempts are recorded once with correct locking, history and notifications. Preserve custody and financial rules. If administrative non-attempt corrections are intentional policy exceptions, clarify that policy and distinguish their evidence; the current UI does not distinguish them.

The assignment remaining `out_for_delivery` also occurs in the existing Rider failure path and preserves rider custody. It is **not independently classified as a defect** and should not be changed merely to make status strings match.

Evidence: [manual-failure.json](../storage/app/private/hub-staff-functional-audit/manual-failure.json), `test_hub_audit_staff_failure_records_attempt_and_failure_evidence`.

### HS-F02 — MEDIUM: maintenance retries put vehicles back into service

An approved available vehicle was marked in maintenance: availability **1 → 0**. Replaying the identical request succeeded and reversed it: **0 → 1**. Both the actual browser action/retry and isolated HTTP probe reproduce this.

`toggleVehicleAvailability()` blindly inverses the current value without the intended target, revision or replay protection. A double submission/network retry can undo the operator's maintenance decision.

Affected files: [LogisticsController.php](../app/Http/Controllers/LogisticsController.php), [hub-vehicles.blade.php](../resources/views/logistics/hub-vehicles.blade.php).

Recommended correction: submit the explicit intended condition, reject stale/conflicting input and serialize with existing vehicle/eligibility locks. Keep the existing fleet workflow. Sequential retry is confirmed; no concurrent-toggle outcome is claimed.

Evidence: [vehicle-replay.json](../storage/app/private/hub-staff-functional-audit/vehicle-replay.json), browser failure and `test_hub_audit_replayed_vehicle_action_must_not_reverse_saved_availability`.

### HS-F03 — MEDIUM: missing company predicate for inconsistent vehicle hub pointers

A disposable vehicle with `company_name=Other` but an Alpha hub foreign key was changed by Alpha Staff: **302**, availability **1 → 0**. `hubVehicles()` filters by hub only; its toggle adds platform approval, but neither also filters by actor company.

Affected file: [LogisticsController.php](../app/Http/Controllers/LogisticsController.php).

Recommended correction: require company and assigned hub together in vehicle reads/writes; review inconsistent legacy pointers separately without automatic repairs.

This is a **conditional missing validation**, reproduced with an intentionally inconsistent fixture. No production corruption was searched for or asserted. Normal vehicles at a different hub were correctly denied with 404.

Evidence: [vehicle-company.json](../storage/app/private/hub-staff-functional-audit/vehicle-company.json), `test_hub_audit_vehicle_company_guard_on_inconsistent_legacy_hub_pointer`.

### HS-F06 — MEDIUM: monitor displays another hub's status actions

An origin Staff account sees an update form for a parcel whose destination is another hub. Submitting it returns **403** because the backend correctly requires destination-hub authorization. The view exposes an action that the operator cannot perform.

Affected files: [monitor.blade.php](../resources/views/logistics/monitor.blade.php), [LogisticsController.php](../app/Http/Controllers/LogisticsController.php).

Recommended correction: use existing operation-specific authorization when rendering action controls. Preserve route visibility and backend enforcement. No unauthorized write occurred.

Evidence: [monitor-hub-action.json](../storage/app/private/hub-staff-functional-audit/monitor-hub-action.json), `test_hub_audit_monitor_must_not_offer_wrong_hub_status_updates`.

### HS-F05 — LOW: Staff alerts open an Admin-only page

Opted-in Staff with an unapproved pickup sees a topbar link to `/logistics/requests`. That target returns **403** because original request approval is Admin-only.

Affected files: [topbar.blade.php](../resources/views/logistics/partials/topbar.blade.php), pending-count context in [LogisticsController.php](../app/Http/Controllers/LogisticsController.php).

Recommended correction: use an existing authorized Staff destination, or omit the unsupported review action. Preserve the Admin review link. This is misleading UI, not privilege escalation.

Evidence: [notification-admin-link.json](../storage/app/private/hub-staff-functional-audit/notification-admin-link.json), browser failure and `test_hub_audit_staff_notification_must_not_link_to_admin_only_requests`.

## Working behavior demonstrated

- Hub/company/province scoping, unrelated parcel lookup denial, current approval checks and rejection of all Admin-only logistics routes passed their existing automated assertions. The browser independently checked nine Admin-only screens and unrelated/foreign parcel lookup.
- Pickup and delivery forms saved **offers**, without prematurely assigning ownership. Wrong coverage and wrong-hub requests were rejected. Duplicate pickup offer did not create a second assignment. Shared three-job, online, vehicle, approval and barangay rules passed the fresh assignment/reassignment suites and MySQL races.
- Actual scanner origin receipt sorted a local parcel and rejected a duplicate receipt. Custody/assignment/compensation hooks passed the related isolated suites.
- Origin Staff created a two-parcel transfer through the form. Staff could not approve/dispatch. Authorized Admin setup dispatched a four-wheel rider; receipt before pickup was rejected. After actual backend rider pickup confirmation, the Destination Staff scanner received one member, leaving one in transit. The remaining-batch form completed the transfer and sorted both parcels.
- Identical discrepancy retry produced one event; a fresh observation produced another. Duplicate final receipt was rejected. An unrelated hub could not open the batch. Notifications opened the correct transfer and marked only the owning Staff record read. MySQL tests additionally covered deduplication, links, competing receipt/dispatch/discrepancy actions and fourteen role/page checks.
- Issue creation and resolution saved history; wrong-hub access, stale revision and missing damage evidence were rejected. Investigation assignment and full positive recovery/RTS UI combinations remain separately pending in the function register despite passing backend coverage.
- Staff clock-in/out, active-shift duplicate prevention, correction submission, duplicate correction rejection and other Staff attendance-record denial passed actual browser checks. Backend tests covered overnight shifts, original timestamps, reviewed corrections, absence/leave restrictions, audit rollback and payroll separation.
- Staff text, reply ID, inert filename rendering, real synthetic PNG upload and participant-only media access passed. Unauthorized contact was denied. Another Staff account could not open the recipient's notification ID.
- Own receiving-account instructions saved; another Staff salary page did not expose them. Wrong current password left its hash unchanged. No payout was performed through the browser.

A direct two-hub transfer was browser-tested. Multi-hop progression, historical evidence isolation, previous-hub custody guards and normal financial workflows were verified by the fresh isolated automated/MySQL scenarios; no physical handover or all-hop browser certification is claimed.

## Missing coverage, UI observations and policy limits

- The captured controls include unlabeled native inputs/selects: transfer parcel checkboxes, courier selects, monitor status selects and history filters lack associated HTML labels in the examined contexts. The [DOM metadata](../storage/app/private/hub-staff-functional-audit/browser-controls.json) and source identify the controls. Existing labels/accessible names should be reviewed with keyboard/screen-reader acceptance; no assistive-device compliance certification was performed.
- The Hub dashboard displays the literal heading `Approved ? Awaiting Logistics Admin Dispatch`; this is a minor copy/encoding observation visible in the mobile screenshot, not a status-transition defect.
- Full browser acceptance remains pending for grouped dashboard transfer requests, pending cancellation, possession-review forms, positive suspended-rider parcel/cash recovery, the complete local return-custody cycle, populated cash counting/transfer/treasury-release forms, paid salary acknowledgement and every filter/pagination permutation. Their identified backend tests passed; that is not a claim those exact UI sequences were executed.
- Physical USB/camera/native-mobile operation is BLOCKED by unavailable devices. Browser external requests were blocked, so production CDN availability, hosted HTTPS/camera permissions and real network pairing were not certified. The browser phone-relay HTTP lifecycle passed using a private persistent cache with locks; mobile API suites passed separately.
- Real SMTP/email-change delivery, production PSGC availability and real payment/remittance handling need manual verification. No real cash was counted or transferred. Financial tests used disposable fixtures and retained exact-money, snapshot, allocation, duplicate and rollback assertions.
- Cross-hub reverse returns remain intentionally unsupported by the existing local RTS shortcut. This is a business/workflow restriction, not a missing feature to implement during this audit. Do not bypass custody or infer Seller receipt.
- Admin-only company settings, approvals, coverage editing, payroll control and cash authorization are correctly unavailable to Staff. They are not missing Hub Staff features. No additional entire Hub Staff module was found absent from the current inventory.
- Legacy custody/vehicle consistency was not recounted in the configured database. Conditional fixture findings do not establish that production has inconsistent records. Historical evidence must not be fabricated or auto-repaired.
- The workbook's 27 prior skips remain unexecuted. Stale skip explanations are not proof that activation is missing; `RiderActivationTest` ran separately and passed.

## Automated and browser details

| Existing suite | Executions | PASS | FAIL | ERROR | Prior SKIPPED |
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

| Browser functional scenario | Result |
|---|---|
| Navigation own hub and mobile menu | **PASS** |
| Admin-only interfaces denied to Hub Staff | **PASS** |
| Unrelated hub and foreign company parcel lookup isolation | **PASS** |
| Private delivery proof respects assigned hub | **FAIL** |
| Pickup offer UI persistence duplicate and wrong hub | **PASS** |
| Delivery offer barangay backend validation | **PASS** |
| Delivery form creates offer without accepting assignment | **PASS** |
| Actual scanner origin receipt and duplicate rejection | **PASS** |
| Attendance clock correction duplicate and record isolation | **PASS** |
| Maintenance submission is safe against identical retries | **FAIL** |
| Vehicle wrong hub and pending approval restrictions | **PASS** |
| Hub Staff alerts do not point to Admin-only pages | **FAIL** |
| Staff messages reply filename and recipient scope | **PASS** |
| Account password current credential validation | **PASS** |
| Transfer monitor history attendance invalid filters | **PASS** |
| Current approval rechecked for Staff session | **PASS** |
| Transfer request UI saves two-parcel pending batch | **PASS** |
| Transfer separation of duties and pickup prerequisite | **PASS** |
| Actual destination scanner partial receipt after rider pickup | **PASS** |
| Discrepancy form retry and distinct observation | **PASS** |
| Complete receipt sorting notifications and unrelated-hub denial | **PASS** |
| Issue creation resolution revision and hub restrictions | **PASS** |
| Salary receiving account form and privacy | **PASS** |
| Phone relay API lifecycle and account isolation | **PASS** |
| Staff attachment upload and private media participant restriction | **PASS** |
| Transfer notification open deep link and read-all isolation | **PASS** |

The six negative probes are stored only in the private audit directory. Their final assertions all FAIL against the current implementation, with no errors. Early probe/browser harness attempts exposed a Symfony streamed-response status accessor, stale in-memory preference fixture, broad form selector, collapsed salary form, wrong receipt payload keys and an incorrect event-column name. Only those private harnesses were corrected. Earlier outputs are retained; they are not counted as additional application bugs. Final receipt probes use `tracking_number`/`confirm_all`, so rejection-before-pickup actually reaches the custody guard.

## Commands and reproducibility

The sequential runner executed every full suite in the table, without a filter, skip flag or existing test changes:

```powershell
python storage/app/private/hub-staff-functional-audit/run_suites.py
# Each suite invocation, with forced isolated environment:
$env:APP_ENV='testing'; $env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; $env:DB_URL=''
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/<Suite>.php --log-junit <private-output.xml>

# Only the six NEW disposable audit probes are selected here; existing suites ran in full above.
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit storage/app/private/hub-staff-functional-audit/HubStaffProbeTest.php --filter test_hub_audit_ --log-junit storage/app/private/hub-staff-functional-audit/probes-final.xml
php artisan route:list --json

# Private wrapper explicitly forces the synthetic SQLite database/disks/session/cache:
php -d memory_limit=512M -S 127.0.0.1:18796 -t public storage/app/private/hub-staff-functional-audit/browser-router.php
python storage/app/private/hub-staff-functional-audit/browser_audit.py
python storage/app/private/hub-staff-functional-audit/browser_more.py
python storage/app/private/hub-staff-functional-audit/browser_controls.py

# Each script guards and removes its own disposable MySQL schema:
$env:APP_ENV='local'; $env:DB_CONNECTION='mysql'; $env:DB_URL=''
php tests/Support/reassignment-mysql.php
php tests/Support/reverse-logistics-mysql.php
php database/verify_hub_transfers.php
```

The 512 MB setting is process-only; application configuration was unchanged. Browser subprocesses required an approved Windows sandbox escalation; network traffic remained restricted to loopback. Raw commands, exits and outputs are retained beside the [suite-progress.json](../storage/app/private/hub-staff-functional-audit/suite-progress.json), [MySQL reassignment](../storage/app/private/hub-staff-functional-audit/mysql-reassignment.txt), [reverse](../storage/app/private/hub-staff-functional-audit/mysql-reverse.txt) and [transfer](../storage/app/private/hub-staff-functional-audit/mysql-transfers.txt) outputs.

## Recommended order and readiness

1. Address HS-F01 private evidence scope and HS-F04 failed-attempt consistency before unrestricted operational use.
2. Address HS-F02 maintenance replay and HS-F03 explicit company validation; preserve existing vehicle eligibility and review legacy inconsistencies without mutation.
3. Align monitor actions and topbar links with existing Hub Staff permissions (HS-F06/HS-F05), then finish label/copy and accessibility acceptance.
4. Add focused regression coverage for the reproduced cases when fixes are authorized; retain all financial, custody, role and duplicate assertions.
5. Complete the pending browser/device/financial acceptance and business-policy reviews listed above before production sign-off.

**Audit complete; implementation intentionally unchanged.** The tested core transfer, assignment, custody, attendance and financial assertions provide useful coverage, but the six findings and explicit verification gaps prevent declaring every Hub Staff function reliable for unrestricted production use.
