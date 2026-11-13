# Hub Scanner — MySQL concurrency verification

12 November 2026. **FAIL: concurrent identical mobile receipt requests can return HTTP 500.** The observed races did not create duplicate physical receipt, custody, history or activity records. This is limited evidence from four executed races, not a general proof of all concurrency behavior. No application code or production database/configuration was changed.

## Isolation and method

A separate MySQL 8.0.45 instance was initialized with a fresh private data directory and no existing user data. It listened only on `127.0.0.1:23306`. Its independently queried UUID was `a72d8dad-de7f-11f1-a024-ec63d7c42295`; `@@datadir` pointed to `storage/app/private/scanner-mysql-isolated/data`. Before creation it listed only MySQL system schemas. The test used a new `scanner_disposable_qa` database, a fixed non-secret test application key, test environment, array mail/cache/session and synthetic fixtures. The configured production database was never connected.

Existing migrations were applied **only** to this fresh isolated database. Two independently bootstrapped PHP processes issued actual Laravel HTTP-kernel requests to `/api/v1/mobile/operations`. Each process signaled readiness before the test released both barriers. The test captured each response and snapshots before and after each race. This tests concurrent application requests and real MySQL, without a network web server; it does not test physical devices.

Synthetic fixtures: two approved Staff accounts at Pila hub, one synthetic transfer rider, two picked-up same-hub parcels and a three-parcel in-transit batch from Cavinti to Pila with rider pickup evidence. The origin pickup fixtures omit a pickup-rider pointer; their pre-receipt rider identity label is therefore incomplete. No production custody evidence was changed or fabricated.

## Results

| Scenario | HTTP responses | Persisted outcome | Result |
|---|---|---|---|
| Same Staff submits same origin receipt UUID/payload concurrently | **200, 500** | Parcel picked_up → sorted; one origin receipt timestamp, custody Pila; exactly two status-history entries (`at_sorting_center`, `sorted`), one `mobile.receive` activity, one durable operation receipt. | **FAIL** — identical retry should return the stored success; duplicate-key exception produced 500. |
| Two different Staff at the same hub concurrently receive a second origin parcel with different operation IDs | **200, 409** | Parcel picked_up → sorted; one hub receipt/custody transition, two status-history entries, one `mobile.receive` activity. The loser received stale-status conflict. | **PASS** for this race |
| Two Staff concurrently receive different parcels in one three-parcel transfer batch | **200, 200** | Two members got individual received timestamps and received_by actors 1 and 2; two legs completed; two parcels became sorted with Pila custody; each had one history and one `mobile.receive_transfer` activity. Batch remained `in_transit`, third member unreceived, with exactly two `parcel_received` events. | **PASS** for this race |
| Two Staff concurrently receive the same third transfer parcel with different operation IDs | **409, 200** | Third member received once, with one timestamp/actor, one completed leg, one status-history entry, one `mobile.receive_transfer` activity; batch completed only when all three members were received. Total transfer events: three `parcel_received`, one `received`. | **PASS** for this race |

The origin received timestamps were recorded on shipments. Transfer receipt timestamps and actors were recorded on transfer members and completion timestamps on legs; the origin sorting timestamp was not used as a destination-receipt marker. Before transfer receipt, custody identified the transfer rider; after each receipt it identified Pila. Full response bodies and before/after snapshots are preserved in [race evidence](../storage/app/private/scanner-mysql-isolated/race-evidence.json) and [readable summary](../storage/app/private/scanner-mysql-isolated/summary.json).

## Confirmed defect

The 500 response is backed by an actual MySQL `1062 Duplicate entry` for the unique `(user_id, operation_id)` key in `mobile_scanner_operations`. The losing transaction attempted to insert a 409 result after the winning transaction had already inserted the same operation ID. The controller's pre-lock read and later receipt lookup use ordinary reads under MySQL's repeatable-read snapshot; the observed result is consistent with the second request not seeing the winning receipt after waiting. The duplicate-key exception is confirmed; the precise isolation-level explanation is a source-based inference requiring a targeted fix/test.

Affected code: [MobileScannerController.php](../app/Http/Controllers/MobileScannerController.php). Impact: a legitimate simultaneous identical retry can report a server error even though receipt succeeded. Recorded state remained single and correct in this run. Do not infer every duplicate-request combination is safe: the tests did not cover same-UUID different payload, cross-user same UUID or physical device interruptions under MySQL.

## Commands and cleanup

The instance was initialized with `mysqld --no-defaults --initialize-insecure` using the isolated directory, then started as a separate hidden process on loopback port 23306. An explicit MySQL query verified `@@port`, `@@datadir`, `@@server_uuid` and the initial database list before creating the disposable schema. Commands from the repository root:

```text
python storage/app/private/scanner-mysql-isolated/migrate_fixture.py
python storage/app/private/scanner-mysql-isolated/setup.py
python storage/app/private/scanner-mysql-isolated/add_snapshot.py
python storage/app/private/scanner-mysql-isolated/run_races.py
python storage/app/private/scanner-mysql-isolated/summarize.py
python storage/app/private/scanner-mysql-isolated/cleanup.py
```

`migrate_fixture.py` explicitly sets the isolated port/schema and invokes `php artisan migrate --force --no-interaction`; it did not read or run migrations against the configured production connection. Each harness worker independently checks the exact port, server UUID, data-directory marker, schema name and testing environment before processing a request. Synthetic scanner tokens were stored temporarily in private files and removed after the run.

Before cleanup, the script rechecked the exact instance UUID, port and data directory, dropped **only** `scanner_disposable_qa`, verified it was absent and shut down the isolated MySQL process. [Cleanup evidence](../storage/app/private/scanner-mysql-isolated/cleanup.json). The private data directory and non-secret test evidence remain for audit. The one error also appeared in the testing Laravel log with the duplicate-key details; no secrets are included in this report.

## Remaining work

Fix and retest the confirmed same-UUID race before declaring scanner receipt retries reliable. Preserve existing eligibility/custody/financial behavior and verify same/different UUID, same/different Staff and concurrent partial receipts against a new disposable MySQL instance. Physical Android, USB, camera and hosted staging acceptance remain outside this test. No new release candidate was frozen.
