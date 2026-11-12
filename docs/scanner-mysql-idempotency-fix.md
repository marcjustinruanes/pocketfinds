# Scanner receipt idempotency correction

12 November 2026. **The seven requested simultaneous-request scenarios passed on a new isolated MySQL instance.** The previously reproduced identical-retry 500 was not observed in this run; both requests returned the exact stored successful response, with one physical receipt.

## Cause and change

`MobileScannerController::operate()` performs an early ordinary receipt lookup before acquiring the actor lock. In MySQL repeatable-read isolation, that lookup may establish a snapshot before another transaction commits the operation receipt. After waiting on the actor/parcel lock, a second ordinary read can still miss the committed row and attempt a duplicate insert. The prior run demonstrated MySQL 1062 and HTTP 500.

The post-actor-lock receipt lookup now uses `lockForUpdate()`, a current locking read. The initial lookup, existing commerce/parcel lock order, authorization checks, payload hash conflict check, successful-result visibility recheck and existing transaction/custody workflow remain in place. Changed application file: [MobileScannerController.php](../app/Http/Controllers/MobileScannerController.php). No migration, financial policy or production configuration changed.

## New MySQL evidence

Fresh MySQL 8.0.45 instance, bound only to `127.0.0.1:23307`, reported server UUID `d3f67fcc-de80-11f1-a675-ec63d7c42295` and a new private `scanner-mysql-fix/data` directory. Initial schema list contained only MySQL system databases. Existing migrations ran against the new `scanner_disposable_qa` schema on that instance, with synthetic fixtures and a fixed non-secret test application key. Every worker independently checked testing environment, port, schema, UUID and data-directory marker before issuing a Laravel HTTP-kernel request. The configured production database was never connected.

Two independent PHP processes waited at a start barrier for each scenario. Each request used `/api/v1/mobile/operations`; the harness captured actual HTTP status/JSON and before/after database snapshots. This is a real MySQL concurrent application test, not SQLite or physical-device verification. The exact observed outcomes:

| Scenario | HTTP responses | Stored operations | Parcel, custody, history, activity and transfer results |
|---|---|---|---|
| Same Staff, same ID and payload | **200, 200** | One; both response bodies equal the stored 200 response | picked_up → sorted; origin receipt timestamp and Pila custody; two status-history entries, one receipt activity |
| Same Staff, same ID, conflicting payload | **200, 409** | One | One origin receipt and activity; conflict did not overwrite the successful result |
| Same Staff, different IDs, same parcel | **409, 200** | Two, including the rejected outcome | One origin receipt, two expected history entries, one activity |
| Different Staff, same ID, same parcel | **200, 409** | Two, scoped to different users | One origin receipt, two expected history entries, one activity |
| Different Staff, different IDs, same parcel | **200, 409** | Two | One origin receipt, two expected history entries, one activity |
| Different transfer members concurrently | **200, 200** | Two successful results | Each parcel sorted with destination custody, received timestamp/actor, one history, one activity and one parcel-received event; batch stayed in transit with two of three members received |
| Same remaining transfer member concurrently | **409, 200** | Two | One member receipt/actor and completed leg, one history, one activity and one parcel-received event; batch completed with three received members and one batch completion event |

An independent validation script checked the returned 200 JSON against stored response JSON, status, receipt timestamps, custody, per-parcel history/activity counts, member/leg evidence and batch events. See [validated results](../storage/app/private/scanner-mysql-fix/validated-results.json), [full response and database snapshots](../storage/app/private/scanner-mysql-fix/race-evidence.json), and [summary](../storage/app/private/scanner-mysql-fix/summary.json). Each response belongs to a real application request; status order reflects that run and is not an ordering guarantee. The conflicting-payload test observed the valid request win. Different race schedules and physical devices are not exhaustively tested.

## Regression tests

| Suite | Result |
|---|---|
| MobileScannerTest | 9 PASS, 134 assertions |
| PhoneScannerTest | 9 PASS, 139 assertions |
| HubTransferWorkflowTest | 25 PASS, 340 assertions |
| ParcelCustodyTest | 27 PASS, 353 assertions |
| HubStaffDefectCorrectionsTest | 33 PASS, 352 assertions |
| RiderBarangayCoverageTest | 12 PASS, 122 assertions |
| JavaScript phone-scanner tests | 5 PASS |
| Flutter scanner tests | 24 PASS; existing `path_provider_windows` plugin warning emitted |
| PHP syntax | PASS |

The six Laravel suites total **115 tests, 1,440 assertions** using isolated SQLite fixtures. They cover authorization, existing retry conflict/visibility behavior, transfer, custody and barangay regressions; SQLite is not the MySQL concurrency evidence. Flutter tests use mocks/fixtures, not a physical device.

Commands executed from the repository root, apart from Flutter:

```text
python storage/app/private/scanner-mysql-fix/prepare.py
python storage/app/private/scanner-mysql-fix/migrate_fixture.py
python storage/app/private/scanner-mysql-fix/setup.py
python storage/app/private/scanner-mysql-fix/prepare_races.py
python storage/app/private/scanner-mysql-fix/run_races.py
python storage/app/private/scanner-mysql-fix/summarize.py
python storage/app/private/scanner-mysql-fix/validate.py
python storage/app/private/scanner-mysql-fix/run_regression.py
node tests/JavaScript/phone-scanner.test.cjs
php -l app/Http/Controllers/MobileScannerController.php
python storage/app/private/scanner-mysql-fix/prepare_cleanup.py
python storage/app/private/scanner-mysql-fix/cleanup.py
```

Flutter command in `mobile/pocketfinds_scanner`: `flutter test --no-pub --reporter expanded`. The regression runner sets SQLite `:memory:` and testing environment explicitly and invokes `php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/<suite>.php --log-junit <private-result>`. Per-suite commands and results are in [backend results](../storage/app/private/scanner-mysql-fix/backend-results.json).

The MySQL instance was initialized with `mysqld --no-defaults --initialize-insecure` against the new private directory, and its identity queried before creating the schema. `migrate_fixture.py` used the explicit isolated connection and `php artisan migrate --force --no-interaction`; no production migration ran. Before cleanup the script rechecked port, UUID and directory, dropped only the verified disposable database, verified its absence, shut down that isolated instance and removed temporary token-bearing files. See [cleanup evidence](../storage/app/private/scanner-mysql-fix/cleanup.json). Synthetic, non-secret evidence remains in the private directory.

## Remaining limitations

No new release candidate was frozen. Real Android camera/offline/pairing, physical USB/workstation camera and hosted staging acceptance remain outstanding. The isolated race results cover the seven named scenarios, not every scheduling interleaving or production load. Existing scanner operations preserve response visibility and role checks, but device/server interruption behavior still needs hosted verification.
