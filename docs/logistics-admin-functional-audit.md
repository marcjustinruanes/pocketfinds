PocketFinds Logistics Admin comprehensive functional audit ? 12 November 2026

**Overall Logistics Admin result: FAIL for unrestricted production acceptance.** Core operational and financial paths work in the tested scenarios, but six application/UI defect categories were reproduced. No fixes were implemented.

This audit covers all 27 existing functional areas and records every inventoried user-facing function separately. It is not a route-existence review and does not certify unexecuted controls. Source inspection, existing automated tests, actual Chromium interactions and saved disposable database records are distinguished below. Execution crossed 11?12 November 2026, Asia/Singapore.

**Safety and environment**

- Application code, existing tests, migrations, configuration and financial calculations/settings on the configured installation were not changed. SHA-256 checks found **zero changes across 794 protected pre-existing files**.
- Existing tests used explicitly forced SQLite `:memory:` databases, fake storage, array mail and isolated fixtures. Suites ran sequentially to avoid shared fake-storage interference.
- Actual browser checks used the real Laravel HTTP kernel on `127.0.0.1:18795`, with a separate disposable SQLite database, synthetic company/Admin/Staff/rider/Seller/buyer accounts, separate file sessions and private QA storage. Production database access was not used by that server. It has been stopped.
- Browser requests outside the loopback host were blocked. PSGC responses were deterministic fixtures; mail/queues were test-only. External fonts/CDNs, hosted TLS, SMTP, actual mobile cameras, physical handovers and real payments were not certified.
- MySQL verifiers cloned table definitions only into name-guarded disposable databases and removed those databases. No live customer/financial rows were copied or changed.
- Synthetic salary/vehicle/assignment/notification/message records and same-rate settings submissions belong only to the QA database. No actual cash, GCash payment, payout or commission activation was performed.

Evidence directory: `storage/app/private/logistics-admin-functional-audit/`. Temporary audit harnesses are stored there, not in application routes or the existing test suite. [Protected-file comparison](../storage/app/private/logistics-admin-functional-audit/protected-after-check.json).

**Results summary**

| Check | PASS | FAIL | ERROR | Unexecuted / blocked |
|---|---:|---:|---:|---:|
| 49 existing selected suites ? 1,252 executions | 1,213 | 11 | 1 | 27 existing skipped workbook cases |
| Additional negative reassignment probe | 0 | 1 | 0 | 0 |
| Final real-browser control scenarios | 18 | 5 | 0 | Hardware/provider/end-to-end gaps listed separately |

Existing suites produced **14,605 assertions**; the additional capacity probe produced three assertions. The browser failures represent five UI/security findings; the sixth finding is the capacity probe. There were no assertion or application fixes, no skip flags, and no failing case was removed to improve these results. The 27 skips are unchanged `markTestSkipped()` branches in `RiderWorkbookTest`; they are not passes.

The previous 555 passing executions were a narrower selection. This broader audit adds suites, failures and unresolved coverage; it does not retroactively reclassify a fixture failure as a successful application test. Counts include inherited executions rather than unique methods.

Actual browser: **Chromium 156.0.8078.4**. The initial sweep completed 30 successful HTML page checks at 1440/390 widths plus login and a JSON poll endpoint; some pages share redirects/layouts. Screenshots and DOM/form metadata are retained. No document-level horizontal overflow was detected in those captured checks. An initial resize captured a moving sidebar; a settled mobile probe verified closed/open/Escape behavior rather than treating that transition as a UI defect. This is not manual pixel-level certification of every screenshot.

The direct Staff-only hub-vehicle URL returned 403, as intended. An invented `/account/notifications` path was a harness mistake, not a broken UI link; actual dropdown notification links worked. A bulk-preview URL opened without a selection was not a valid workflow entry; the subsequent selected-Staff form successfully opened the preview. Initial hidden-panel/phone/form-associated-checkbox/session/fixture lookup harness problems were corrected only in private QA tooling/data, with their logs retained.

[All automated outcomes](../storage/app/private/logistics-admin-functional-audit/automated-per-test-results.md), [machine results](../storage/app/private/logistics-admin-functional-audit/automated-results.json), [browser outcomes](../storage/app/private/logistics-admin-functional-audit/browser-final-results.json), [page/form sweep](../storage/app/private/logistics-admin-functional-audit/browser-pages.json).

**Classification rules**

PASS means the stated scenario was executed successfully with relevant UI/backend/database evidence. FAIL means a tested expected behavior failed; test-fixture failures are separately identified. NEEDS MANUAL VERIFICATION means the exact interface/control or complete scenario was not exercised. BLOCKED means a prerequisite, hardware/provider boundary or existing skipped scenario prevented execution. An area result summarizes the reported scope, not every possible permutation; individual functions retain their own unexecuted status in the register.

**All 27 functional areas**

| # | Functional area | Result | Executed evidence / working functions | Failure or remaining boundary |
|---:|---|---|---|---|
| 1 | Dashboard and navigation | **FAIL** | Real login; dashboard/date forms; mobile open/Escape; queue rendering. | Visible topbar Search is inert (F03). |
| 2 | Seller pickup request management | **NEEDS MANUAL VERIFICATION** | Approval saved; duplicate approval cannot change the original approval timestamp; payment/scope guards tested. | Full browser rejection/stock/refund path was not exercised. Public registration failures are fixture problems T01. |
| 3 | Buyer delivery assignments | **PASS** | Browser sent one offer; ownership remains unclaimed; duplicate offer preserved one assignment. Acceptance/expiry/decline/eligibility covered by tests. | Real rider operation and manual handover are outside browser acceptance here. |
| 4 | Rider assignment offers and reassignment | **FAIL** | Initial offers pass. Negative probe confirmed reassignment accepted offline/uncovered replacement at capacity, then persisted four active jobs. | F02: shared eligibility/capacity is bypassed by the replacement path. |
| 5 | Hub-to-hub transfer management | **PASS** | Browser approval/dispatch persisted; repeated dispatch rejected. Workflow tests and disposable MySQL cover origin custody, roles, partial/final receipt, multi-hop, notifications and duplicate actions. | Rider pickup and destination receipt were exercised through automated tests, not physical scanning. |
| 6 | Live monitoring and permitted delivery status | **NEEDS MANUAL VERIFICATION** | Monitor renders; manual reason/status, stale attempts, retry eligibility and return holds have backend/DB evidence. | Not every monitor filter and status button was driven in Chromium. |
| 7 | Parcel lookup and workstation scanner | **NEEDS MANUAL VERIFICATION** | Browser scanner page renders; backend lookup/scope/idempotency/held actions pass. | Physical camera/USB and every workstation action were not performed; external browser CDNs were deliberately blocked. |
| 8 | Mobile scanner management | **NEEDS MANUAL VERIFICATION** | 18 API executions pass for tokens, pairing, scope, retries and stale actions. | No real phone/camera or native-app interaction in this run. |
| 9 | Rider accounts applications and activation | **NEEDS MANUAL VERIFICATION** | Actual browser onboarding stored a pending synthetic applicant and documents after required fixture lookup was supplied. Managed hiring/onboarding/activation tests pass. | Full browser approve/reject/suspend/resend/edit cycle and SMTP delivery remain unexecuted. Workbook failures/skips are retained, not hidden. |
| 10 | Rider barangay coverage | **PASS** | Browser coverage saved; multi-area, company/hub/version, clearing/moved-hub and missing-coverage behavior are tested. | PSGC responses were mocked; external service availability is not certified. |
| 11 | Hub Staff management and hiring | **NEEDS MANUAL VERIFICATION** | Roster renders; server tests exercise hiring, interview and staff/rider onboarding restrictions. | Full browser Staff lifecycle/document/interview/correction workflow not driven. |
| 12 | Hub and geographic management | **NEEDS MANUAL VERIFICATION** | Browser hub search submits; geographic/hiring/role validation tests pass. | Actual browser creation/edit/closure and every hiring toggle were not executed; geography network is mocked. |
| 13 | Fleet and vehicle management | **FAIL** | Browser vehicle submission persists pending platform review; platform/tenant rules tested. | F06: hub/type selects have no programmatic labels. Availability/edit/delete were not all browser-driven. |
| 14 | Parcel issues investigations recovery and RTS | **PASS** | Actual RTS request saves requested custody and leaves shipment failed. Witnesses, roles, safe legacy rendering, holds, finance exceptions, idempotency and rollback pass. | No physical return/condition inspection was claimed. Unsupported dispositions remain held. |
| 15 | History and operational reporting | **NEEDS MANUAL VERIFICATION** | History/reports render; browser-requested operational CSV downloads; export audit/scope tests pass. | Every date/status/hub/search/pagination combination was not exercised. |
| 16 | Compensation settings | **PASS** | Browser resaved current rates; revision increments, stale replay rejects and existing compensation snapshots remain unchanged. | The separate whole-transfer-batch earning test has a missing custody fixture (T02), not a demonstrated rate-calculation failure. |
| 17 | Shipping rates and shipping payment verification | **NEEDS MANUAL VERIFICATION** | Browser negative shipping amount returns 422 without DB change; payment gate/rate/snapshot/review tests pass. | Full browser carrier-account setup and proof verify/reject cycle were not executed. |
| 18 | COD Cash Desk management | **NEEDS MANUAL VERIFICATION** | Cash Desk renders; collection/count/allocation/authorization/discrepancy tests pass. | Browser cash movement/count cycle requires funded synthetic collection fixtures; no physical cash moved. |
| 19 | Treasury and Seller settlements | **NEEDS MANUAL VERIFICATION** | Finance pages render; exact money, reservation, actual receipt, payout, ownership and rollback cases pass. | Full browser simulated treasury receipt/Seller payment/acknowledgement sequence not executed; no real payment. |
| 20 | Rider earnings and payouts | **NEEDS MANUAL VERIFICATION** | Earning/payout pages and covered backend cases pass. | Whole-batch earning scenario is blocked by T02 fixture; full browser payout flow not executed. |
| 21 | Hub Staff payroll and salary management | **NEEDS MANUAL VERIFICATION** | 38 salary and 52 workspace executions pass; browser configured a synthetic monthly rate and opened a selected-Staff bulk preview. | Full browser funding/payment/acknowledgement and all bulk modes remain manual; preview did not pay anyone. |
| 22 | Attendance management | **NEEDS MANUAL VERIFICATION** | Attendance page and CSV load; attendance/leave/correction/timezone/role rules covered in tests. | Browser correction decisions, leave/absence and record-review actions not all executed. |
| 23 | Financial reconciliation | **PASS** | Reconciliation renders; summary CSV downloaded; conservation, buckets, reservations, exceptions and tenant checks pass. | Every export dataset/filter combination was not manually checked; reconciliation does not repair balances. |
| 24 | Commission management and financial messaging | **NEEDS MANUAL VERIFICATION** | Commission portal renders; guarded financial/payment/conversation/enrollment scenarios pass in synthetic tests. | Full commission browser transaction/conversation cycle not executed; live enrollment/receiving-account activation was not attempted. |
| 25 | Activity logs and audit history | **PASS** | Activity screen renders; tested operational/financial mutations save audit events and audit failures roll back tested operations. | Every log filter was not browser-driven; logs do not prove physical custody. |
| 26 | Notifications messages and attachments | **FAIL** | Browser message+PNG saved; notification deep link opened authorized subject. | F04: Reply context not saved. F05: filename HTML marker executed locally. No cross-user stored exploit was claimed. |
| 27 | Admin profile and account security | **FAIL** | Profile renders; wrong-current-password browser submission leaves hash unchanged and shows error. | F01: suspended Admin downloaded same-company private proof. Full profile/address/email-OTP/password-success workflow not all driven. |

The [individual-function register](../storage/app/private/logistics-admin-functional-audit/function-results.csv) contains **184 entries**, preserving all 27 inventory groups and the additional visible controls/security scenarios examined here. It explicitly retains unexecuted functions as NEEDS MANUAL VERIFICATION or BLOCKED. It is not a claim that every button was clicked merely because its page loaded. Route-by-route scope remains available in the [original Admin inventory](logistics-admin-function-inventory.md).

**Confirmed defects and recommended corrections ? ranked**

**F01 ? HIGH ? Suspended Logistics Admin can download private proof ? FAIL**

- Expected: Reject suspended actors even when the old session remains authenticated.
- Actual: The synthetic Admin was suspended after login; GET /delivery-proofs/{shipment} returned 200 and 68 PNG bytes. Its status was restored only in the disposable fixture.
- Root cause: The shared endpoint has auth/throttle middleware, but its logistics authorization branch checks is_logistics and company without current approval.
- Affected files: `app/Http/Controllers/DeliveryProofController.php::show`; `routes/web.php delivery-proofs.show`.
- Recommended correction: Revalidate the current approved actor/role at the shared evidence endpoint. Apply the accepted buyer/Seller/rider/company/hub evidence policy without weakening existing owner checks.
- Evidence: `browser-actions.json; browser-final-results.json`.

**F02 ? HIGH ? Possession reassignment bypasses rider eligibility and capacity ? FAIL**

- Expected: An ordinary rider at the existing combined three-job limit must not receive a fourth job; replacement eligibility must be consistent with the accepted online/coverage policy.
- Actual: An approved same-hub replacement was offline, had zero barangay routes and already had three active jobs. Admin request and destination Staff confirmation both redirected successfully; ownership changed and active jobs became four.
- Root cause: AssignmentReassignment::replacement checks approval/company/hub/vehicle but omits shared online/barangay/capacity checks. Confirmation repeats that narrower validation and does not enforce the combined limit.
- Affected files: `app/Services/AssignmentReassignment.php::replacement, request, decide`; `app/Services/RiderDeliveryOffers.php existing shared eligibility/locks`.
- Recommended correction: Reuse the existing shared eligibility and current capacity check under compatible ordered locks at request/confirmation. Preserve possession confirmation, snapshots and separate four-wheel transfer rules.
- Evidence: `reassignment-observed.json; reassignment-probe.xml; ReassignmentProbeTest.php`.

**F03 ? MEDIUM ? Visible topbar Search is inert ? FAIL**

- Expected: The visible Search control must perform a supported search or clearly indicate it is unavailable.
- Actual: Typing QA-ADMIN-1 and pressing Enter caused no navigation, results or search request. Page-specific rider/Staff/hub search forms worked.
- Root cause: The topbar input has no form/name or bound search handler.
- Affected files: `resources/views/logistics/partials/topbar.blade.php`; `public/js/admin.js / navigation.js inspected for handlers`.
- Recommended correction: Wire the existing affordance to an existing supported lookup/monitor search, or remove/clearly disable the misleading affordance. No new search module is necessary.
- Evidence: `browser-actions.json`.

**F04 ? MEDIUM ? Message Reply UI discards its selected context ? FAIL**

- Expected: Replying to message 1 should persist its ID in reply_to_id for the new reply.
- Actual: The Reply banner opened and a new message was saved, but reply_to_id was null.
- Root cause: replyToMessage stores only text; sendMessage does not append reply_to_id even though the backend already supports it.
- Affected files: `resources/views/logistics/messages.blade.php::replyToMessage, sendMessage`; `app/Http/Controllers/Concerns/HandlesMessaging.php existing reply validation/persistence`.
- Recommended correction: Track the selected message ID and submit the existing reply_to_id field; preserve thread authorization and clear it on cancel/success.
- Evidence: `browser-message-probes.json`.

**F05 ? MEDIUM ? Selected attachment filename is interpreted as HTML ? FAIL**

- Expected: A filename must remain inert display text.
- Actual: A synthetic filename containing an img/onerror marker set a harmless window flag in the authenticated Admin browser before upload. No exfiltration or real recipient was involved.
- Root cause: renderFileChips interpolates f.name into innerHTML. Attachment preview HTML also interpolates attachment names and deserves the same escaping review.
- Affected files: `resources/views/logistics/messages.blade.php::renderFileChips and appendMessage`.
- Recommended correction: Use textContent and safe DOM property assignment for names/attributes. Add a harmless filename regression case while preserving attachments.
- Evidence: `browser-message-probes.json`.

**F06 ? LOW ? Fleet hub/type selects have no accessible names ? FAIL**

- Expected: Both visible selects should have associated programmatic labels.
- Actual: Their labels, aria-label and aria-labelledby were empty in Chromium. Normal vehicle submission still persisted a pending record.
- Root cause: Visible standalone labels are not associated with the selects.
- Affected files: `resources/views/logistics/fleet.blade.php`.
- Recommended correction: Associate the existing labels with matching select IDs or wrap the controls; retain the current form and validation.
- Evidence: `browser-more.json`.

F01 is a reproduced current-approval failure, not a claim of cross-company disclosure. The broader same-company Hub Staff delivery-proof policy also remains a review issue from the prior readiness report. F05 proves local filename-to-DOM execution in the Admin browser; no cross-user stored exploit, data exfiltration or external attack was claimed. Both fixes can improve existing controls without adding modules.

**Existing automated failures/errors ? exact cases and causes**

The following remain FAIL/ERROR in this audit. Current valid-input managed onboarding, hiring and activation suites pass; the public-registration/workbook fixture failures do not establish that those managed workflows are broken.

| Exact case(s) | Expected versus actual | Cause / affected feature | Recommended correction |
|---|---|---|---|
| `FulfillmentWorkflowTest::test_public_rider_and_staff_registration_store_documents_and_preserve_pending_roles` | Expected registration 200; actual 422 | Missing required middle name and incompatible resume evidence. Public registration fixture no longer meets current validation. | Update valid fixture inputs when authorized; retain pending-role/document persistence assertions. |
| `FulfillmentWorkflowTest::test_registration_failure_after_upload_removes_files_and_invalid_hub_never_uploads` | Expected injected storage-failure 500; actual 422 before upload phase | Same invalid baseline prevents the intended rollback test from reaching storage. | Supply valid current registration inputs; keep the injected failure and cleanup assertions. |
| `RiderCompensationTest::test_whole_transfer_batch_earns_once_and_partial_receipt_earns_nothing` | Expected request/dispatch then earning assertions; actual ERROR for missing authoritative origin evidence | Cloned SECOND parcel lacks its own order history/activity receipt witness. No rate calculation is reached. | Add genuine disposable fixture witnesses for each parcel; never remove the custody guard or fabricate live history. |
| `RiderWorkbookTest::test_workbook_case`, datasets `TC-RID-001`, `005`, `006`, `010`, `028`, `032` | Expected successful submission/workbook continuation; actual 422 for missing NBI evidence | Incomplete managed-rider baseline blocks the intended scenario. | Add valid current NBI fixtures without weakening assertions. |
| Same method, dataset `TC-RID-012` | Expected licence-number boundary errors; actual NBI error before later field checks | Incomplete baseline masks the licence boundary test. | Complete the baseline before asserting that field's independent failures. |
| Same method, dataset `TC-RID-014` | Workbook treats today/tomorrow licence expiry as valid; current backend rejects it | Outdated expectation against the existing one-month minimum. | Align the test with the documented current validation; do not relax the application rule. |
| `CompanyOwnershipTest::test_rejected_founder_reapplies_on_same_id_and_preserves_hubs` | Expected initial registration 200; actual required-middle-name 422 | Initial founder fixture is invalid before reapplication assertions. | Supply valid middle-name/no-middle-name inputs appropriate to the current rules, preserving identity/hub retention checks. |

The [failure CSV](../storage/app/private/logistics-admin-functional-audit/test-failures.csv) records expected/actual/root cause and affected test files individually. No financial rate or calculation change is indicated by these failures.

All **27 pre-existing skipped cases** are in `RiderWorkbookTest`: six are file-picker/camera/manual cases; ten retain a stale reason claiming an emailed activation flow does not exist; eleven require phone/UI/network scenarios outside that server test. Current `RiderActivationTest` passed 14 executions and managed browser onboarding produced a pending applicant. Therefore the stale activation skip reason is a test-maintenance gap, not evidence that activation is missing. None of these skipped cases was counted as PASS or implemented during this audit.

**Working functions actually exercised**

- Real login and company Admin pages; mobile menu open/close/Escape; valid/invalid dashboard dates; page-specific rider/Staff/hub search; rider pagination over 25 synthetic records.
- Pickup approval with duplicate protection; delivery offer creation with ownership remaining unaccepted and one saved assignment; saved barangay coverage.
- Approved transfer dispatch with a four-wheel rider; duplicate dispatch rejected. Automated/real MySQL checks also cover custody, partial/final receipts, route sequence, history isolation, notifications and discrepancies.
- Normal pending fleet submission, local RTS request without premature `returned`, authorized notification destination, normal text+PNG messaging and wrong-current-password rejection.
- Same-rate compensation form saves with revision protection and unchanged snapshots; invalid shipping amount rejects without a saved rate change; salary configuration and selected-Staff preview; operational/financial/payroll/attendance CSV downloads.
- Automated monetary, payroll, attendance, hiring, onboarding, privacy and audit cases pass in their stated suites, with the separate fixture failures retained above. For example: Hub Hiring 25, Personnel Onboarding 13, Rider Activation 14, Hub Address 12, Staff Salary 38, Payroll Workspace 52, Hub Salary/Attendance 54 and Payment Reporting 24 executions passed.

**Database, transaction and consistency assessment**

- PASS in tested scenarios: persisted offer/pending vehicle/approval/coverage/message/RTS/salary records matched their expected state; unaccepted offers did not assign shipment ownership; duplicate/stale requests did not duplicate the tested operational records; configured same-rate saves did not rewrite compensation snapshots.
- PASS in executed regression cases: exact money, reservations, conservation, role ownership, actual-receipt separation and audit-failure rollback remain protected. No new financial-calculation defect was demonstrated.
- FAIL: reassignment changed ownership and produced four active jobs despite the existing three-job rule (F02). Normal transaction success does not make that business invariant correct.
- FAIL: Reply UI saved a message without its intended reply relation (F04), although the backend supports that field.
- BLOCKED: the dedicated whole-transfer-batch earning test cannot reach its earning assertions because its cloned parcel has invalid custody fixtures. This is not evidence that full batch earning works or that its calculation is wrong.
- MySQL verifiers passed current reverse races (duplicate/conflicting failures, completed delivery versus failure, retry versus acceptance) and transfer request/approval/dispatch/pickup/partial/final receipt, duplicate dispatch/receipt, discrepancy idempotency, notification deduplication/deep links and 14 role/page checks. See [reverse output](../storage/app/private/logistics-admin-functional-audit/mysql-reverse.txt) and [transfer output](../storage/app/private/logistics-admin-functional-audit/mysql-transfers.txt). SQLite success is not a blanket certification of all MySQL paths or production scale.

**Missing/incomplete functionality and manual gaps**

- Confirmed incomplete existing controls: topbar Search and Reply-context submission. Unsafe filename rendering and missing select labels are UI/security defects, not requests for new modules.
- The original skipped workbook cases and complete live-looking browser cycles remain unexecuted where listed. Tests that have stale input/blocked reasons need maintenance; this audit did not alter them.
- Full browser approve/reject/interview/reactivation cycles; all profile/address/email-OTP/password-success actions; every filter/export dataset; and full simulated cash/treasury/Seller/rider payment acknowledgement have not all been driven interactively. They remain NEEDS MANUAL VERIFICATION despite related server tests.
- BLOCKED in this browser environment: actual camera/USB/phone/native pairing, external decoder/geography availability and real provider receipts. Network blocking/mocked PSGC was a deliberate safety boundary, not proof of an application outage.
- Cross-hub reverse transport, partial/unsellable returns, condition clearance/recovery redispatch, loss/liability and financial exception settlement remain existing held/policy gaps. No new feature or bypass was added. The separate post-delivery buyer-return sellability policy was not changed.
- The previous read-only local legacy review found one returned parcel without a timestamp/verified Seller-return event. That prior finding was not re-queried or repaired here and is not a new production census.

**UI and usability notes**

Native HTML validation, conditional ownership fields, collapsed salary/RTS details, payroll checkboxes associated using the HTML `form` attribute and activation prerequisites are real behavior. Initial harness attempts that ignored those conditions were corrected in private tooling. Missing lookup/type data was supplied only to the synthetic fixture. The valid browser onboarding then saved a pending applicant. These setup/selector failures are not listed as app defects.

F03, F04 and F06 remain genuine UI failures after using valid controls. F05 is genuine unsafe filename rendering. A password error appeared in both global and form-local notices; this is a minor duplication observation, not a demonstrated password-security failure. Browser screenshots/metadata are retained, but no claim is made that every pixel, keyboard/screen-reader journey or physical device has been manually certified.

**Execution and reproducibility**

`BrowserFixtureTest.php` created a new disposable SQLite copy from the existing test schema; its one successful setup assertion is not counted as a functional pass. `browser-router.php` booted the real application with forced QA database/storage/session/mail settings. Browser scripts used actual DOM controls and actual HTTP requests, then checked saved SQLite rows. External requests were blocked. Harmless security payloads stayed in the local fixture.

Existing test invocation, executed once per selected file:

```powershell
# Process-only environment: APP_ENV=testing; DB_CONNECTION=sqlite; DB_DATABASE=:memory:;
# DB_URL=; MAIL_MAILER=array; QUEUE_CONNECTION=sync; CACHE_STORE=array; SESSION_DRIVER=array
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/<Suite>Test.php --colors=never --log-junit storage/app/private/logistics-admin-functional-audit/<Suite>Test.xml
```

All 49 file names and their return codes are retained in [suite-progress.json](../storage/app/private/logistics-admin-functional-audit/suite-progress.json). The orchestrator finishing is not a passing test verdict; nonzero suite results are retained. Additional executed commands:

```powershell
php -d memory_limit=512M vendor/bin/phpunit storage/app/private/logistics-admin-functional-audit/BrowserFixtureTest.php --filter test_prepare_disposable_browser_database --colors=never
php -d memory_limit=512M vendor/bin/phpunit storage/app/private/logistics-admin-functional-audit/ReassignmentProbeTest.php --filter test_reassignment_must_not_exceed --colors=never --log-junit storage/app/private/logistics-admin-functional-audit/reassignment-probe.xml
python storage/app/private/logistics-admin-functional-audit/browser_audit.py
python storage/app/private/logistics-admin-functional-audit/browser_actions.py
python storage/app/private/logistics-admin-functional-audit/browser_actions.py --followup
python storage/app/private/logistics-admin-functional-audit/browser_more.py
python storage/app/private/logistics-admin-functional-audit/browser_more.py --followup
python storage/app/private/logistics-admin-functional-audit/browser_message_probes.py
# Local MySQL probes, APP_ENV=local; DB_CONNECTION=mysql; DB_URL=:
php tests/Support/reverse-logistics-mysql.php
php database/verify_hub_transfers.php
```

The temporary loopback server was started with the private router and has been stopped; other existing PHP/services were not stopped. Synthetic SQLite fixtures, screenshots and diagnostics remain private for reproducibility. No production configuration, existing test or application behavior was changed.

**Recommended correction order and readiness decision**

1. HIGH: close the suspended-actor private-proof gap (F01), and enforce existing replacement eligibility/capacity at confirmed reassignment (F02).
2. MEDIUM: make filenames inert (F05), wire the existing reply field (F04), and resolve the existing misleading Search affordance (F03).
3. LOW: associate fleet selector labels (F06); then review duplicate notices and complete accessibility acceptance.
4. Separately maintain the 12 failing/error test inputs/expectations and stale skipped reasons, preserving custody/authorization/money/rollback assertions. Do not lower safety gates to make old fixtures pass.
5. Re-run focused, regression and browser checks after any future authorized fixes, then finish unexecuted operational/UI/device cases and deployment/security/data-review gates.

**Decision: FAIL / not ready for unrestricted Logistics Admin production sign-off.** Many existing operational and financial functions work, but the six confirmed defects and stated verification gaps prevent declaring that every Admin function works reliably. This report implements no correction and does not authorize production activation.
