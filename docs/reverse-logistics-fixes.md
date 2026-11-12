PocketFinds reverse-logistics corrections - 11 November 2026

Implemented only corrections to existing failed delivery, retry, return custody, investigation, receipt and notification workflows. No migration was added or applied, no production records were changed, and no historical status, attempt count or custody evidence was fabricated. Existing same-rider retry behavior is retained; this does not introduce a new acceptance or return-routing workflow.

**Corrections**

1. Refusal and exhausted attempts now remain `delivery_failed`; only existing authenticated full sellable Seller receipt completes `returned`. Retry remains blocked for refusals/exhausted attempts.
2. Failed submissions lock the existing operational footprint, recheck actor/company/current assignment/order/stage, and validate a per-attempt HMAC token. Duplicate, conflicting and stale forms cannot increment attempts, overwrite a later delivery, or duplicate history/notifications.
3. Custody summaries read existing RTS state plus matching actor/revision events before terminal fallback. Actual receipt and recorded time are shown; missing legacy evidence remains under review. Staff release is distinguished from rider pickup.
4. Retry uses existing shared rider eligibility and lock order, including barangay coverage and three combined active pickup/delivery jobs. Attempts and financial snapshots are not reset.
5. Failed and RTS alerts use existing notifications with deterministic IDs. Approved company Admins and responsible destination-hub Staff receive operational updates; affected Seller/buyer/assigned rider receive relevant updates. Existing notification openers authorize the subject before marking read and route to the appropriate existing page.
6. Lost, damaged and recovered records prevent dispatch/release. Resolving an investigation alone does not attest recovered/sellable custody or clear the hold. Authorized physical hub receipt remains recordable without automatically offering the parcel again. COD/earning exception gates remain intact.
7. Forms match reason/evidence/description constraints and hide wrong-hub, wrong-owner, cross-hub or held RTS actions. Existing issue updates retain authorization, revision and rollback checks.

**Final automated results**

| Run | PASS | FAIL | ERROR | SKIPPED | Assertions |
|---|---:|---:|---:|---:|---:|
| Reverse logistics corrections | 16 | 0 | 0 | 0 | 144 |
| Existing RTS and investigations | 51 | 0 | 0 | 0 | 674 |
| Logistics and financial regression baseline | 462 | 0 | 0 | 0 | 6339 |
| Total executions | 529 | 0 | 0 | 0 | 7157 |

These are test executions, including inherited regression cases, rather than a count of unique test methods. The previous 462-test regression baseline still passes with its original 6,339 assertions. Detailed individual results: [per-test results](../storage/app/private/reverse-logistics-audit/corrections-per-test-results.md), [machine-readable results](../storage/app/private/reverse-logistics-audit/corrections-results.json).

**Real MySQL verification - PASS**

- Identical failure versus failure: observed lock wait, one committed attempt/history/notification per recipient; competing request rejected 409.
- Conflicting failure reasons: observed lock wait, first accepted reason preserved; competing request rejected 409.
- Proof/OTP-confirmed prepaid delivery versus failure: observed lock wait, delivered state preserved, competing failure rejected 409, no failure increment/history. This status race uses minimal disposable workflow tables; full financial invariants are covered by the regression suites.
- Retry versus offer acceptance with two combined active jobs: observed lock wait, one retry, competing acceptance rejected 422, exactly three active jobs.
- Existing transfer verifier: request/approval/dispatch/pickup/partial and final receipt; dispatch, receipt and discrepancy races; notification deduplication/deep links; 14 role/page checks all passed.

Only schema definitions were read from the configured local database. Fixtures were generated in random, name-validated disposable databases. The probes removed those databases in `finally`; no live rows were copied or changed. The prepaid delivery proof used its own temporary test-storage directory.

**Commands executed**

PHPUnit used process-only environment: `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL=`. The 512 MB memory limit was a CLI option; configuration files were unchanged.

```powershell
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/ReverseLogisticsCorrectionsTest.php tests/Feature/ReverseLogisticsTransferHoldTest.php --filter test_reverse_ --colors=never --log-junit storage/app/private/reverse-logistics-audit/focused.xml
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/RtsWorkflowTest.php tests/Feature/IssueInvestigationTest.php --colors=never --log-junit storage/app/private/reverse-logistics-audit/rts-investigation.xml
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/LogisticsFinanceFoundationTest.php tests/Feature/OperationalRecoveryTest.php tests/Feature/CodCollectionTest.php tests/Feature/HubTransferWorkflowTest.php tests/Feature/HubTransferMigrationTest.php tests/Feature/ParcelCustodyTest.php tests/Feature/LogisticsAuthorizationTest.php tests/Feature/LogisticsShippingTest.php tests/Feature/LogisticsCashDeskTest.php tests/Feature/LogisticsReconciliationTest.php tests/Feature/RiderAssignmentWorkflowTest.php tests/Feature/RiderBarangayCoverageTest.php tests/Feature/OperationalAuditTest.php tests/Feature/CodSellerPayoutTest.php tests/Feature/GcashPurchaseTest.php tests/Feature/CommissionMessagingTest.php tests/Feature/CommissionWorkflowFixTest.php --colors=never --log-junit storage/app/private/reverse-logistics-audit/regression-isolated.xml

# Process-only APP_ENV=local, DB_CONNECTION=mysql, DB_URL=; scripts enforce local host and disposable database names.
php tests/Support/reverse-logistics-mysql.php
php database/verify_hub_transfers.php
```

`php -l` passed for all 20 affected PHP files; `git diff --check` passed for affected tracked files. Focused Blade views were rendered through HTTP/view tests. No browser, physical scanner or real-payment test is claimed.

Execution evidence is under `storage/app/private/reverse-logistics-audit/`: `focused.*`, `rts-investigation.*`, `regression-isolated.*`, `mysql-reverse.txt`, `mysql-transfer.txt`, `lint.txt`.

**Investigation and reruns**

Initial focused runs exposed incomplete new test fixture inputs; those were corrected before final verification. Four older issue-audit cases also had invalid inputs: two supplied PDF evidence to an existing image-only initial-report endpoint, one omitted required damage proof, and one rollback case used a description below the existing minimum plus incompatible evidence. Only fixture inputs were corrected, preserving authorization, rejection, financial, evidence and rollback assertions. No test was skipped or suppressed.

The first large run exhausted PHP's default 128 MB limit. A CLI-only 512 MB limit resolved that test-runner constraint. One later concurrent run had a cleanup assertion contaminated by another suite using Laravel's shared fake-storage directory. The final 462-test run was executed alone and passed. These superseded attempts are retained in the initial/regression logs; final results above use the isolated completed runs.

Hash comparison with the prior QA snapshot confirmed no change to `Order`, `LogisticsFinance`, `RiderCompensation`, `CodCollection`, `CodSellerPayouts`, `LogisticsShipping`, `GcashWorkflow`, `CommissionPayments`, or any migration. Their financial/inventory calculations remain intact.

**Modified files - complete list for this task**

| File | Purpose |
|---|---|
| [app/Http/Controllers/RiderController.php](../app/Http/Controllers/RiderController.php) | Locked, fresh failed-attempt validation, stale-form token, failed status and scoped notifications. |
| [app/Http/Controllers/LogisticsController.php](../app/Http/Controllers/LogisticsController.php) | Shared retry eligibility/capacity locks, fresh operator scope, consistent failure alerts, physical receipt without offering held parcels. |
| [app/Http/Controllers/LogisticsManagementController.php](../app/Http/Controllers/LogisticsManagementController.php) | Scoped parcel notification targets, consistent issue locks/fresh authorization, additional-evidence validation. |
| [app/Http/Controllers/CommerceController.php](../app/Http/Controllers/CommerceController.php) | Authorized failure/RTS notification routing before marking read. |
| [app/Http/Controllers/BuyerController.php](../app/Http/Controllers/BuyerController.php) | Existing buyer notification opener handles authenticated failure/RTS targets. |
| [app/Http/Controllers/SellerController.php](../app/Http/Controllers/SellerController.php) | Existing Seller notification opener links to the affected order. |
| [app/Models/Shipment.php](../app/Models/Shipment.php) | Per-attempt HMAC token and internal authorized hub-receipt context; existing financial guards retained. |
| [app/Services/RiderDeliveryOffers.php](../app/Services/RiderDeliveryOffers.php) | Retry reuses existing barangay, hub, company, approval, online, vehicle and combined-capacity checks. |
| [app/Services/AssignmentReassignment.php](../app/Services/AssignmentReassignment.php) | Central condition/recovery/RTS holds; physical receipts do not clear holds. |
| [app/Services/LogisticsRecovery.php](../app/Services/LogisticsRecovery.php) | Shared hold queries and release/dispatch restriction; investigation resolution is not physical clearance. |
| [app/Services/LogisticsRts.php](../app/Services/LogisticsRts.php) | Central active-return check, release/sellable-receipt restrictions and return-custody notifications. |
| [app/Services/ParcelCustody.php](../app/Services/ParcelCustody.php) | Present actual authenticated RTS event evidence before terminal-status fallback; missing evidence stays under review. |
| [app/Services/HubTransferWorkflow.php](../app/Services/HubTransferWorkflow.php) | Apply condition holds to existing request/approval/dispatch/pickup gates, while allowing authorized actual receipt; no reverse-transfer system added. |
| [app/Services/LogisticsParcelNotifications.php](../app/Services/LogisticsParcelNotifications.php) | New internal helper reuses existing notification storage; scoped recipients, deterministic deduplication and authorized existing-page links. |
| [resources/views/rider/show.blade.php](../resources/views/rider/show.blade.php) | Hidden attempt token on the existing failed-delivery form. |
| [resources/views/logistics/issues.blade.php](../resources/views/logistics/issues.blade.php) | Correct retry reason bounds, parcel anchors and hold/return-required instructions. |
| [resources/views/logistics/partials/rts-custody.blade.php](../resources/views/logistics/partials/rts-custody.blade.php) | Display only actions supported by actor, hub, ownership, locality and hold state. |
| [resources/views/logistics/partials/issue-management.blade.php](../resources/views/logistics/partials/issue-management.blade.php) | Match initial-report evidence types/description bounds, damage requirement and hold instructions to backend validation. |
| [tests/Feature/ReverseLogisticsCorrectionsTest.php](../tests/Feature/ReverseLogisticsCorrectionsTest.php) | New focused HTTP, custody, notifications, eligibility, hold and rollback cases. |
| [tests/Feature/ReverseLogisticsTransferHoldTest.php](../tests/Feature/ReverseLogisticsTransferHoldTest.php) | New actual transfer receipt versus subsequent dispatch-hold case. |
| [tests/Feature/RemainingLogisticsAuditTest.php](../tests/Feature/RemainingLogisticsAuditTest.php) | Two valid damage-evidence fixture corrections; assertions preserved. |
| [tests/Feature/IssueInvestigationTest.php](../tests/Feature/IssueInvestigationTest.php) | Valid image/description fixtures for evidence, authorization and audit rollback; assertions preserved. |
| [tests/Support/WorkflowSchema.php](../tests/Support/WorkflowSchema.php) | Add isolated valid PNG issue-evidence fixture helper; existing financial fixture defaults unchanged. |
| [tests/Support/reverse-logistics-mysql.php](../tests/Support/reverse-logistics-mysql.php) | Disposable MySQL races with actual lock-wait assertions and database cleanup. |
| [docs/reverse-logistics-fixes.md](../docs/reverse-logistics-fixes.md) | This implementation/verification report. |

Pre-existing changes from earlier tasks remain in the worktree; they are not attributed to this correction. Generated execution reports are under the private audit directory rather than application source.

**Remaining limits and review requirements**

- Legacy premature `returned` rows and missing custody witnesses remain untouched and require evidence-based review. This correction does not backfill or guess their history.
- Cross-hub reverse transport, partial/unsellable returns, write-off/liability and financial exception settlement remain existing held/policy gaps. No alternate return/transfer system or compensation entitlement was introduced.
- Lost/damaged/recovered parcels remain held when a record is merely marked resolved. No new physical-disposition clearance or recovery-redispatch feature was added.
- The separate post-delivery buyer-return condition/quantity policy remains unchanged, as requested for inventory and financial behavior.
- Manual browser/scanner validation remains to be performed using isolated test accounts and fixtures.
