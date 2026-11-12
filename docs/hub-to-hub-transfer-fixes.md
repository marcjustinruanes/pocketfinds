# Hub-to-hub transfer corrections

Implemented 11 November 2026. Scope: the seven findings in the existing Logistics Admin / Hub Staff transfer audit. The earlier audit remains an historical report; this document records the corrections.

## Corrections

1. **Province-aware routes.** Equal municipality names bypass transfer generation only when their provinces are compatible. Santa Cruz, Laguna to Santa Cruz, Marinduque generates a relay leg with the correct province identities. Existing same-province and regional-hub routing remains intact.
2. **Shared transfer eligibility.** The rider dropdown and dispatch use `TransferRiderEligibility`. Riders must be online (`is_available`), approved, correctly assigned to the origin company/hub, four-wheel and have valid personal/company ownership. Existing exclusive active/ambiguous-work checks remain. Company riders require an approved, available matching four-wheel company vehicle; dispatch retains its locked vehicle reservation checks. Ordinary three-job capacity and barangay rules are not applied to transfers.
3. **Accurate partial-receipt activity.** The service returns the leg IDs received in the current operation to the existing activity middleware. Earlier receipts and unreceived members are excluded. The legacy single-parcel scanner adapter retains its single-leg activity behavior.
4. **Historical pickup isolation.** The transfer detail table displays pickup evidence only for a dispatched batch whose leg still belongs to that batch. Rejected/cancelled requests do not display current parcel custody from a later batch. Historical membership and event records are retained.
5. **Authoritative custody gate.** Approval and dispatch validate recorded receipt evidence through `TransferCustodyEvidence`. Initial origin custody requires chronological seller/rider handover and receipt timestamps, matching order receipt history with an actor, and the matching company/hub/actor parcel-receipt activity record. An intermediate hop requires the preceding completed leg, dispatched company batch, rider pickup, destination hub and actual member receipt/receiver evidence. A received member can advance while its preceding batch remains partially in transit. Missing or inconsistent legacy evidence produces a review warning and blocks approval/dispatch. No evidence is backfilled, inferred from status alone, or fabricated.
6. **Receipt/completion notifications.** Each parcel receipt and completed batch notifies approved company Logistics Admins and approved staff at the operational origin/destination hubs. Deterministic notification UUIDs use the existing notification primary key to suppress retries. Creation remains in the receipt transaction, so failed receipts cannot leave notifications behind. Parcel messages identify the tracking number. Links recheck current account, company and hub authorization before opening the batch. New pickup alerts reference their batch; old shipment-reference pickup alerts are resolved against recorded pickup history as of the alert time. Ambiguous historical links are not guessed.
7. **Discrepancy retry identity.** Forms include a UUID request key. The batch lock, a current locking event lookup and a database uniqueness constraint guarantee one event per key. An identical retry succeeds without adding an event or duplicate activity records; reusing the key for another actor/payload returns 409. A new key records a genuinely separate observation, even if its wording is identical. Historical events retain null request keys.

## Migration

`2026_11_11_000002_add_transfer_discrepancy_request_keys.php` adds only nullable `request_key` and `request_hash` columns and a unique `(batch_id, request_key)` index to `hub_transfer_events`. Persistent request identity is required to distinguish a retried submission from a separate observation. There is no data backfill or historical update.

The migration was applied by its exact path to the configured local MySQL database. Before/after hashes verified that the original column values in nine operational/financial tables were unchanged, including transfers, members, events, legs, shipments, orders, notifications, compensation and financial transactions. Other environments must apply this migration before using discrepancy submissions:

```sh
php artisan migrate --path=database/migrations/2026_11_11_000002_add_transfer_discrepancy_request_keys.php --force
```

## Files changed in this task

| File | Purpose |
| --- | --- |
| `app/Models/LogisticsHub.php` | Province-aware route generation |
| `app/Services/TransferRiderEligibility.php` | Shared transfer-specific eligibility |
| `app/Services/TransferCustodyEvidence.php` | Read-only authoritative custody validation and review messages |
| `app/Services/HubTransferNotifications.php` | Scoped, duplicate-safe receipt/completion alerts and authorized links |
| `app/Services/HubTransferWorkflow.php` | Integrate eligibility/custody, exact receipt activity IDs, notifications and discrepancy idempotency |
| `app/Http/Controllers/HubTransferController.php` | Shared dropdown filtering, review warnings, activity IDs and UUID validation |
| `app/Http/Controllers/CommerceController.php` | Transfer-only notification link branch; existing financial notification branches remain intact |
| `resources/views/logistics/transfer-show.blade.php` | Custody review/no-rider messages, historical pickup isolation, discrepancy UUID |
| `database/migrations/2026_11_11_000002_add_transfer_discrepancy_request_keys.php` | Additive discrepancy request identity |
| `database/verify_hub_transfers.php` | Disposable MySQL migration, locking, receipt, notification and discrepancy checks |
| `tests/Support/WorkflowSchema.php` | Isolated request-key schema and explicit synthetic origin receipt fixture helper |
| `tests/Feature/HubTransferWorkflowTest.php` | Seven-finding regressions, partial onward transfer, authorization, retries and transfer capacity |
| `tests/Feature/HubTransferMigrationTest.php` | Existing event preservation, unique request keys and reversible migration |
| `tests/Feature/ParcelCustodyTest.php` | Explicit valid/missing receipt fixtures |
| `tests/Feature/LogisticsAuthorizationTest.php` | Update the legacy transfer test fixture with the newly required custody evidence |
| `docs/hub-to-hub-transfer-fixes.md` | This implementation and validation report |

Pre-existing workspace changes, including earlier Rider/coverage work, were preserved. This task does not modify the Rider controller/dashboard, offer acceptance logic, barangay coverage, financial business rules or transfer status vocabulary. The only pickup callback adjustment in the shared transfer service is the notification's batch reference.

## Validation

- Focused transfer/custody/migration suite: **56 passed, 714 assertions**. This includes inherited workflow regressions in `ParcelCustodyTest`.
- MySQL verifier: passed with real row-lock waits between separate PHP processes for competing dispatches, competing final receipts and concurrent identical discrepancy submissions. Exactly one dispatch/receipt transition and one discrepancy event survive; a separate discrepancy key creates another observation. Receipt/completion notifications remain duplicate-free. Fourteen transfer page/role checks passed, along with current and legacy notification deep links against MySQL. The verifier created and removed a disposable database; fixture tests did not alter configured records.
- PHP syntax checks passed for all 14 changed PHP source/test/verifier files. Whitespace checks passed for the transfer-specific diff.
- Broader regression suite: **406 tests, 386 passed, 17 failures and 3 errors, 5,474 assertions**. All 20 unsuccessful cases exactly match the baseline control; no additional failures were introduced. The control ran the same three affected test classes with pre-change transfer workflow/routing/schema code: 42 tests, 22 passed, the same 17 failures and 3 errors, 232 assertions.
- Fully passing regression groups: LogisticsAuthorization (37), LogisticsShipping (23), LogisticsCashDesk (25), LogisticsReconciliation (19), RiderAssignmentWorkflow (9), RiderBarangayCoverage (12), OperationalAudit (21), CodSellerPayout (23), GcashPurchase (74), CommissionMessaging (65), CommissionWorkflowFix (56).
- Baseline failures remain in LogisticsFinanceFoundation (3), OperationalRecovery (7) and CodCollection (10). Causes include older expected pickup/delivery compensation defaults (2,500/4,000 cents versus the existing 3,000/5,000 cents), missing required pickup tracking or delivery proof/OTP in older fixtures, and resulting absent COD/recovery records. These test/code mismatches are outside the seven transfer findings; no financial or Rider behavior was changed to make them pass.

Focused and MySQL commands:

```sh
php -d memory_limit=512M vendor/pestphp/pest/bin/pest tests/Feature/HubTransferWorkflowTest.php tests/Feature/HubTransferMigrationTest.php tests/Feature/ParcelCustodyTest.php --compact
# PowerShell: use the local environment only for the disposable verifier process.
$env:APP_ENV='local'
php database/verify_hub_transfers.php
```

Artifacts are in ignored `storage/app/private/`: focused and regression JUnit reports, MySQL verifier output, migration preservation hashes, and a baseline control report. The baseline control preloads pre-change transfer workflow/routing/schema code from Git without replacing workspace application files.

## Remaining limitations

- The 20 baseline regression failures listed above remain unresolved outside this scope.
- Missing legacy custody evidence deliberately remains missing. Staff/Admin must review it through the existing operational process; this change provides no automatic historical repair or bypass.
- Historical pickup alerts with indistinguishable recorded batch times cannot be assigned an authoritative deep link. New alerts store the batch ID directly.
- Automated HTTP/database checks were run; no physical scanner/camera or interactive browser test was performed.
- Pest reports a permission warning when writing its vendor test-run-history cache. Tests and JUnit reports still complete; vendor files were not changed.
