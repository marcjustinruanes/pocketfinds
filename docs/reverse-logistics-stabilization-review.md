PocketFinds reverse-logistics stabilization review - 11 November 2026

Four confirmed defect categories were corrected after explaining the proposed changes and reproducing the failures in isolated fixtures. No new workflow/module, migration, financial calculation, compensation rate or business policy was introduced. No production or historical records were modified.

**Findings and disposition**

| Severity | Category | Finding | Correction / disposition |
|---|---|---|---|
| High | Confirmed defect - fixed | RTS could advance a stored legacy state without its supporting event, authorizing hub release or Seller completion despite missing evidence. An unverified legacy Seller-received state, or a witnessed completion with inconsistent parcel/order statuses, could also clear the return hold. | Reuse one matching-event witness check for transitions, return holds and custody presentation. Missing/inconsistent witnesses or completion statuses now require review; no event or status is backfilled. |
| Medium | Confirmed defect - fixed | Logistics-recorded failure did not persist the submitted reason; notifications could lose their reason. An unreachable duplicate notification branch remained. | Restore reason persistence and remove unreachable code. Existing financial/status rules are retained. |
| Medium | Confirmed defect - fixed | Nonempty RTS metadata without `state`, or partial recovery metadata without required fields, could crash Issues with HTTP 500. | Safely show evidence-review messages, preserve raw metadata and prevent generic edits to return-custody records. Complete recovery cards still render normally. |
| Medium | Confirmed UI defect - fixed | The scanner offered rider assignment for a held damaged parcel, although the backend denied it. | Suppress the unavailable assignment action/rider choices and keep the custody-review explanation. Authorized physical receipt remains available. |
| High | Legacy data review - unresolved | One configured local parcel is marked returned without `returned_at` or a verified Seller-return event. | Review existing evidence manually. Do not infer that a physical return did or did not occur; do not rewrite the record automatically. |
| High | Operational/financial policy gaps | Cross-hub reverse transport, partial/unsellable returns, disposition clearance, recovery redispatch and loss/liability settlement remain unsupported or held. | Preserve holds. Decisions require approved operational policy, outside this correction. Investigation resolution alone does not authorize release/restock/refund. |
| Pending | Untested real-world behavior | Browser/device camera permissions, USB scanners, actual labels and physical handovers have not been exercised. | Complete the manual checks below using separate test accounts and disposable records. |

The legacy RTS/recovery crash and evidence-bypass defects were reproduced with disposable fixtures; the configured local database currently contains no RTS or recovery records. They are not claims that those malformed RTS/recovery rows were found in live data.

**Review coverage and security**

Reviewed the previous correction manifest, affected controllers/services/models/views/test helpers, relevant route middleware, notification openers, transfer receipt/dispatch integration, and browser/mobile scanner paths. The shared witness check compares existing event action, state, revision and actor with the linked parcel/company; transport/Seller witnesses must match the recorded owner, and hub receipt/release witnesses must reference the destination hub. This does not reconstruct historical user roles or invent missing handovers.

Current permissions remain enforced: approved owning Seller, assigned company rider, responsible destination Hub Staff and authorized company Admin. Forward transfer request/approval/dispatch/pickup/receipt remain in the existing workflow. Rider retry keeps shared barangay, hub, approval, online, vehicle and combined three-active-job checks; four-wheel transfer rules remain separate.

A completed return must have a verified Seller event, resolved issue, returned shipment/order and return timestamp. The guarded model transition has an internal completion context for the brief interval before its own atomic parcel/order writes; it still requires the matching Seller witness and preserves condition, recovery and possession-review restrictions.

The new RTS event is recorded in the same existing transaction before Seller completion updates. This lets model guards verify the event; any later failure still rolls back the event, order/shipment changes, notifications and stock restoration. No historical event is created by review or display.

Hash checks confirmed `Order`, `LogisticsFinance`, `RiderCompensation`, `CodCollection`, `CodSellerPayouts`, `LogisticsShipping`, `GcashWorkflow`, `CommissionPayments` and existing migrations remain unchanged. The separate post-delivery buyer-return sellability/quantity policy remains unchanged and requires operational review; this is not a new refund, inventory or liability mechanism.

**Read-only legacy review**

The configured local MySQL database was inspected in an explicitly read-only transaction, rolled back after SELECT queries. Only aggregate counts were printed; a private exception manifest contains parcel identifiers for authorized follow-up and no customer names, addresses or payment amounts.

- Five shipments were present: one each returned, delivered, completed, out for delivery and picked up.
- One returned parcel lacks both its return timestamp and a verified Seller-return event.
- No failed/returned order-versus-shipment status mismatch was found.
- No RTS or recovery records were present in this local database.
- No issue, timestamp, status, custody evidence or financial record was inserted or updated.

Evidence: [aggregate counts](../storage/app/private/reverse-logistics-audit/stabilization-legacy-aggregates.json), [private exception manifest](../storage/app/private/reverse-logistics-audit/stabilization-legacy-exceptions.csv), [read-only probe](../storage/app/private/reverse-logistics-audit/stabilization-legacy-readonly.php). These five local records are not a complete production-data certification.

**Final automated verification**

| Run | PASS | FAIL | ERROR | SKIPPED | Assertions |
|---|---:|---:|---:|---:|---:|
| Focused reverse logistics and stabilization | 24 | 0 | 0 | 0 | 190 |
| Logistics and financial baseline | 462 | 0 | 0 | 0 | 6339 |
| RTS and investigation regressions | 51 | 0 | 0 | 0 | 674 |
| Phone and mobile scanner API regressions | 18 | 0 | 0 | 0 | 273 |
| Total executions | 555 | 0 | 0 | 0 | 7476 |

Counts include inherited test executions, rather than unique methods. Suites were executed sequentially to avoid sharing Laravel fake-storage directories. [Every test result](../storage/app/private/reverse-logistics-audit/stabilization-per-test-results.md) and [machine-readable summary](../storage/app/private/reverse-logistics-audit/stabilization-results.json) are retained.

Eight stabilization scenarios cover manual reason persistence, missing hub receipt evidence, missing return transport evidence, malformed RTS rendering, unverified Seller-received hold bypass, inconsistent completed-return statuses, held scanner assignment actions, and incomplete/complete recovery rendering. The initial four reproduction tests failed before their application fixes; separate scanner, recovery and inconsistent-completion probes also reproduced their defects. New test fixtures initially needed an isolated unserviceable-area table; that was fixture setup, not a deployed schema defect. No failing test was skipped, suppressed or weakened.

Real MySQL disposable-database checks also passed:

- Identical and conflicting failed-delivery submissions: observed row-lock waits, one accepted attempt/history/notification per recipient; competing request 409.
- Confirmed delivery versus failed submission: delivery preserved, competing failure 409, no failed-attempt increment.
- Retry versus offer acceptance at two active jobs: one retry, competing acceptance 422, exactly three combined active jobs.
- Existing transfer request, approval, dispatch, pickup, partial/final receipt; dispatch/receipt/discrepancy races; notification deduplication and authorized links; 14 role/page checks.

Disposable databases were removed. Only schema definitions were read for fixture construction; no customer rows were copied or changed. See [reverse races](../storage/app/private/reverse-logistics-audit/stabilization-mysql-reverse.txt) and [transfer verification](../storage/app/private/reverse-logistics-audit/stabilization-mysql-transfer.txt).

`php -l` passed for all 20 PHP files in the previous correction manifest. `node --check public/js/parcel-scan.js` passed. Affected Blade views rendered through HTTP/view tests. `git diff --check` passed for the files changed here.

**Browser and scanner readiness**

| Area | Current evidence | Manual execution still required |
|---|---|---|
| Desktop lookup and held parcel actions | HTTP regression verifies authorization, custody description and suppression of unavailable assignment. | Chrome/Edge/mobile viewport, keyboard/USB Enter behavior, repeated scans and stale tabs. |
| Phone connection | API tests verify owner/session/hub/company restrictions, expiry, disconnect and duplicate codes. | Pair physical devices, scan a real QR, disconnect/reconnect and test loss of connectivity. |
| Mobile receipt | API tests verify actual receiving hub, rider confirmation, stale-state conflicts and idempotent retry. | Confirm that the operator receives only the physically present parcels, including a partial batch. |
| Camera | Source includes permission/CDN-failure fallback; JavaScript syntax passes. | HTTPS secure context, allow/deny permission, focus/rotation, real barcode quality and offline fallback. |
| Failed delivery and retry | Attempt-token, authorization, capacity, barangay and stale-submission tests pass. | Two browser tabs, network timeout after commit, eligible/ineligible rider display and clear errors. |
| Return custody | Existing-event checks, role restrictions, duplicate completion and rollback pass. | Separate Admin, Origin Staff, Destination Staff, assigned rider and Seller accounts; actual handovers using test parcels. |
| Financial workflows | Existing automated COD/GCash/commission/shipping/payout regressions pass; calculations unchanged. | Use disposable test balances only; no real payment or production cash transaction. |

Readiness decision: the corrected code passes the listed automated checks. Real-world rollout still needs the legacy exception reviewed and manual browser/device/physical-custody checks completed. Policy-held cases remain held; passing automation does not authorize an unsupported financial or custody action.

**Files changed in this stabilization pass**

| File | Change |
|---|---|
| [app/Http/Controllers/LogisticsController.php](../app/Http/Controllers/LogisticsController.php) | Persist manual failure reason, remove unreachable duplicate notification branch, hide scanner assignment actions on held parcels. |
| [app/Http/Controllers/LogisticsManagementController.php](../app/Http/Controllers/LogisticsManagementController.php) | Keep incomplete return-custody records out of generic investigation editing. |
| [app/Services/LogisticsRts.php](../app/Services/LogisticsRts.php) | Share existing-event witness validation across transition, completed-return hold checks and presentation. Write the new event inside the existing transaction before Seller completion triggers model guards. |
| [app/Services/AssignmentReassignment.php](../app/Services/AssignmentReassignment.php) | Pass the internal witnessed-completion context while preserving all other holds. |
| [app/Models/Shipment.php](../app/Models/Shipment.php) | Restrict that context to the existing failed-to-returned model transition with a return timestamp. |
| [app/Services/ParcelCustody.php](../app/Services/ParcelCustody.php) | Reuse the shared witness check; keep incomplete/inconsistent evidence under review. |
| [resources/views/logistics/partials/issue-management.blade.php](../resources/views/logistics/partials/issue-management.blade.php) | Safely render incomplete return state and prevent generic return-custody edits. |
| [resources/views/logistics/partials/rts-custody.blade.php](../resources/views/logistics/partials/rts-custody.blade.php) | Hide return actions without matching evidence and show the review explanation. |
| [resources/views/logistics/partials/recovery-custody.blade.php](../resources/views/logistics/partials/recovery-custody.blade.php) | Render incomplete recovery metadata as review required; retain normal rendering for complete records. |
| [tests/Feature/ReverseLogisticsCorrectionsTest.php](../tests/Feature/ReverseLogisticsCorrectionsTest.php) | Add eight stabilization scenarios, including preservation of incomplete legacy metadata and complete recovery rendering. |
| [docs/reverse-logistics-stabilization-review.md](../docs/reverse-logistics-stabilization-review.md) | This review and verification report. |

Earlier worktree changes remain preserved. Generated QA evidence and the read-only review probe are under `storage/app/private/reverse-logistics-audit/`; they are not application modules.

**Commands**

PHPUnit used process-only `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL=`. No configuration file was edited.

```powershell
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/ReverseLogisticsCorrectionsTest.php tests/Feature/ReverseLogisticsTransferHoldTest.php --filter 'test_stabilization_|test_reverse_' --colors=never --log-junit storage/app/private/reverse-logistics-audit/stabilization-focused.xml
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/RtsWorkflowTest.php tests/Feature/IssueInvestigationTest.php --colors=never --log-junit storage/app/private/reverse-logistics-audit/stabilization-rts-investigation.xml
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/PhoneScannerTest.php tests/Feature/MobileScannerTest.php --colors=never --log-junit storage/app/private/reverse-logistics-audit/stabilization-scanner.xml
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/LogisticsFinanceFoundationTest.php tests/Feature/OperationalRecoveryTest.php tests/Feature/CodCollectionTest.php tests/Feature/HubTransferWorkflowTest.php tests/Feature/HubTransferMigrationTest.php tests/Feature/ParcelCustodyTest.php tests/Feature/LogisticsAuthorizationTest.php tests/Feature/LogisticsShippingTest.php tests/Feature/LogisticsCashDeskTest.php tests/Feature/LogisticsReconciliationTest.php tests/Feature/RiderAssignmentWorkflowTest.php tests/Feature/RiderBarangayCoverageTest.php tests/Feature/OperationalAuditTest.php tests/Feature/CodSellerPayoutTest.php tests/Feature/GcashPurchaseTest.php tests/Feature/CommissionMessagingTest.php tests/Feature/CommissionWorkflowFixTest.php --colors=never --log-junit storage/app/private/reverse-logistics-audit/stabilization-regression.xml
node --check public/js/parcel-scan.js
# Process-only APP_ENV=local, DB_CONNECTION=mysql, DB_URL= for local MySQL probes:
php tests/Support/reverse-logistics-mysql.php
php database/verify_hub_transfers.php
php storage/app/private/reverse-logistics-audit/stabilization-legacy-readonly.php
```
