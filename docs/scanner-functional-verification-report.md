# PocketFinds Scanner — actual functional verification

12 November 2026. **Result: tested backend receipt, partial-transfer receipt, workstation entry and code-relay operations PASS in the isolated local environment. Physical Android/USB/camera acceptance and real MySQL concurrency are NOT TESTED. This is not staging or production certification.**

No application behavior, existing tests, configuration, migrations or production records were modified. Only this report and temporary private verification artifacts were created. All five owned loopback browser scenarios finished with their temporary server stopped. A hash comparison against all 1,258 files in the frozen source manifest found zero differences before this report was written. The frozen archive was not rebuilt or altered.

## Environment, accounts and evidence

Staging host/key remain unavailable, so verification used local isolation:

- Backend: real Laravel HTTP kernel/controllers/services, SQLite `:memory:`, existing `WorkflowSchema` disposable fixtures; actual HTTP validation, authentication, authorization, model writes, transactions and queries, not mocked controller outcomes.
- Browser: actual headless Chromium through Playwright against PHP on `127.0.0.1:18836`, isolated synthetic SQLite databases, separate session/cache/log/private-storage paths, synthetic approved Staff login and blocked external browser requests. Mail uses array/fake and server HTTP requests are faked/blocked. The temporary testing router is not a deployment configuration.
- Native client: existing Flutter tests, widget/camera mocks and real disposable SQLite queue tests. These did not execute a physical APK against the Laravel listener.
- Accounts: synthetic Origin Staff at Pila, Laguna; separate Destination Staff at Santa Cruz, Marinduque for the batch probes; synthetic Logistics Admin and four-wheel transfer Rider for request/approval/dispatch/pickup setup. Existing suites additionally exercise unrelated hubs, foreign companies, invalid roles, suspended accounts and closed hubs. No production account was used.
- Origin fixture includes synthetic Seller/Rider pickup timestamps before receipt. Its pickup rider pointer is absent, so the initial custody label contains an empty rider-number suffix; a fully populated pickup-rider identity display is not certified by this fixture. Transfer fixtures have explicit rider, batch and authenticated origin receipt evidence.

Evidence directory: [scanner-functional-verification](../storage/app/private/scanner-functional-verification). Important files:

| Evidence | Contents |
|---|---|
| [origin-evidence.json](../storage/app/private/scanner-functional-verification/origin-evidence.json) | Full shipment/history/activity/custody/receipt snapshots before lookup, after lookup, after receipt and after duplicate |
| [transfer-evidence.json](../storage/app/private/scanner-functional-verification/transfer-evidence.json) | Real two-parcel batch before/after native API receipts; membership, events, custody and activity |
| [negative-evidence.json](../storage/app/private/scanner-functional-verification/negative-evidence.json) | Unknown/foreign/wrong-hub lookup and destination-visible unauthorized receipt results |
| [browser-results.json](../storage/app/private/scanner-functional-verification/browser-results.json) | Manual and simulated USB entry browser/database snapshots |
| [transfer-browser-results.json](../storage/app/private/scanner-functional-verification/transfer-browser-results.json) | First and second destination workstation receipt snapshots |
| [relay-browser-results.json](../storage/app/private/scanner-functional-verification/relay-browser-results.json) | Actual API relay → Chromium lookup; duplicate relay result and unchanged database snapshots |
| [verification-summary.json](../storage/app/private/scanner-functional-verification/verification-summary.json) | Existing-suite results, client/browser summary and frozen-source hash comparison |
| `*.xml`, `*Test.txt`, `probe.txt`, `transfer-probe.txt`, `negative-probe.txt` | PHPUnit per-test evidence and counts |
| `manual-before.png`, `manual-after.png`, `usb-before.png`, `usb-after.png`, `transfer-1.png`, `transfer-2.png`, `paired-relay.png` | Actual Chromium screenshots |

Private SQLite files are synthetic test databases, not production backups. They must remain outside public hosting. Temporary probes extend existing fixture classes without changing existing tests; inherited tests rerun in the origin probe are counted as executions, not new independent scenarios.

## Requested scenarios — steps and actual outcomes

PASS applies only to the stated test layer. Where physical capture or full native integration was unavailable, it is explicitly NOT TESTED even when the corresponding backend/client test passes.

| # | Exact steps / preconditions | Expected | Actual and database/status/history evidence | Result |
|---|---|---|---|---|
| 1 | Sign into loopback site as synthetic approved Origin Staff; open `/logistics/scan`. Repeat as Destination Staff in separate transfer fixture. | Authorized Scan Parcel UI loads with entry and contextual actions. | Actual Chromium page returned 200 and rendered functional lookup/receipt controls for both Staff roles. Opening page did not record receipt. | PASS |
| 2 | On Origin Scan Parcel enter `QA-MANUAL` and click Find; separately enter `QA-USB` and press Enter, simulating keyboard-style USB input. | Valid code retrieves its parcel without receiving it. | Correct matching tracking appeared; before/after-lookup shipment, history and activity snapshots identical. Physical barcode optics/USB device not exercised. | PASS for entry/lookup; hardware NOT TESTED |
| 3 | Native API lookup `MOBILE-TRACK`; assert parcel ID/action and omitted personal fields. Chromium lookup each known fixture code and inspect status. | Correct authorized parcel; native minimal metadata, website current status. | Native `mobile-parcel` returned; browser matching tracking/status rendered. Native response omitted buyer contact/address. Buyer contact/address correctness against a fully populated buyer fixture was not separately verified. | PASS for asserted IDs/status/privacy; full address accuracy NOT TESTED |
| 4 | Lookup picked-up origin parcel; inspect actual browser action text. Change a disposable parcel so Staff is destination, not origin, while it remains picked up; request native and web lookup. | Receive only at authorized origin; destination visibility does not permit origin receipt. | Origin browser displayed only `Receive & Sort`; destination-visible native result had `actions: []`, web result `stage: none`, `can_fail: false`. Attempted native receive returned 422. No receipt history/activity written. | PASS |
| 5 | Browser: click `Receive & Sort`. Native backend: submit UUID/action `receive`/expected status `picked_up`; Flutter widget test invokes existing confirmation callback. | Explicit operation records receipt only after accepted server request. | Browser button performed receipt. Native API accepted guarded operation. Flutter confirmation-related widget tests passed. Website button does not add a separate confirmation dialog; native main.dart has Cancel/Confirm, but physically tapping that dialog against Laravel was not performed. | PASS for executed browser/API operations; full APK confirmation NOT TESTED |
| 6 | Query shipment and status-history after origin receive; inspect action receipt. | Real persisted receipt, not redirect-only success. | Native shipment `picked_up → sorted`, `at_sorting_center_at`/`sorted_at` saved, order sorted; one durable mobile operation receipt. Website fixtures independently saved same transition and timestamps. | PASS |
| 7 | Capture `ParcelCustody::describe`, full order history/activity before/after origin receive; replay same native UUID. | Origin custody established from evidence; history/activity once. | After native receipt: `Awaiting Final Delivery`, responsible `Pila, Laguna`; history `at_sorting_center`, `sorted`, both actor 1; one `mobile.receive`, hub 1. Replay left all snapshots identical. Browser origin receipt recorded one `logistics.scan.receive` per parcel and two status-history rows. | PASS |
| 8 | Build real approved/dispatched two-parcel batch using synthetic Admin/Origin/Rider operations; confirm rider pickups. As Destination submit native `receive_transfer`; independently use actual website `Mark Received` for each parcel. | Destination receipt updates only selected parcel and existing transfer records. | Native and browser receipts each passed. Received shipment became sorted; transfer member and leg receipt/completion evidence persisted. Detailed native custody became `Santa Cruz, Marinduque`. | PASS |
| 9 | Existing mobile test attempts receipt from origin; move synthetic actor to destination, remove leg pickup confirmation and retry fresh UUID; restore pickup confirmation and retry. | Wrong side/no rider pickup rejected; valid destination confirmation succeeds. | Unauthorized/unavailable attempts 422; valid attempt 200 and leg completed/parcel sorted. Existing transfer regression also checks no pre-pickup member receipt. Browser physical Rider interaction not exercised. | PASS for backend guards |
| 10 | Receive first of two parcels; query both shipments/member timestamps/batch/events; then receive second. Execute both native API and browser variants. | First receipt partial; second completes; no false receipt for remaining parcel. | First: one received member, batch `in_transit`, first parcel sorted at destination, second `hub_transfer` with Transfer Rider #4. Second: two received members, both sorted at destination, batch `completed`. `parcel_received` only for each selected member; final `received` event once. | PASS |
| 11 | Repeat native accepted operation UUID/payload; change payload under same UUID; submit new receive UUID after already sorted; repeat relay nonce. | No duplicate custody/history; conflicting UUID rejected; real repeated scan remains an observation. | Same receive UUID returned prior result and identical snapshots; conflicting payload 409; new receive after receipt 422. Relay repeat returned `duplicate: true`, sequence 1. Flutter deliberate separate scan records have different UUIDs. Sequential retries verified; concurrent MySQL receipt race not run. | PASS for tested retries; concurrency NOT TESTED |
| 12 | Lookup `QA-UNKNOWN`; change disposable shipment company to Foreign, then both route hubs away from Staff; attempt invalid-status receipt with delivered fixture. | Unknown/inaccessible 404; invalid receipt rejected without operational writes. | Lookup 404 for all three denied variants. Delivered-status receive 422. Receipt timestamp stayed null, order-status history count 0, activity count 0. The fixture's delivered status was setup, not scanner mutation. Rejected mobile operations may create durable error receipts. | PASS |
| 13 | Flutter tests create SQLite pending record, close/reopen queue during Syncing, simulate server failure, then manual reconnect/sync; Laravel test posts `/scans` twice with same UUID/context. | Saved observation survives; retries retain UUID; sync verifies without receiving. | Flutter persistence/retry/account isolation tests passed. Laravel accepted one verification receipt, no activity, shipment remained picked_up; changed payload/context conflicts. No physical phone airplane-mode → real Laravel synchronization session was executed. | PASS for component/backend tests; complete device journey NOT TESTED |
| 14 | Compare snapshots before/after native lookup and browser manual/Enter lookup; synchronize scan without operation. | Scan/lookup never establishes receipt/custody. | Native lookup snapshots exactly equal; browser lookup snapshots equal; sync left status picked_up and activity 0, with only verification receipt persisted. | PASS |
| 15 | Actual Chromium creates connection; same synthetic account logs into native API; claim token, relay `QA-PAIRED`, repeat nonce; wait for workstation parcel card. Do not click Receive. | Relay only passes code; computer lookup/review, no automatic receipt. | Parcel rendered on real browser. Repeat marked duplicate with sequence 1. Full shipment/history/activity snapshots stayed identical; `QA-PAIRED` stayed picked_up and no receipt timestamp/history/activity appeared. Native phone camera was replaced by authenticated API input. | PASS for API-to-browser path; physical two-device flow NOT TESTED |

## Detailed receipt evidence

### Origin receipt

Native origin fixture before: shipment picked_up, no hub-receipt/sorted timestamp, no status-history or activity, no scanner operation receipt. Authenticated pickup timestamps are fixture setup, not fabricated production history.

After lookup: exact same persisted snapshot. After confirmed API operation: sorted, origin receipt and sort times saved; two history rows (`at_sorting_center`, `sorted`), one `mobile.receive` activity and one durable operation receipt. Custody description identified Pila, Laguna. Same UUID replay: no changes.

Website manual/Enter fixtures followed the same status/history behavior, but their activity action is `logistics.scan.receive`. These are actual browser POST/PATCH/backend results, not simulated JSON response bodies. Existing pickup-job completion and compensation suites supply broader regression coverage; these fixtures do not represent a populated paid pickup and do not certify a new financial journey.

### Transfer receipt

The batch was requested by Origin Staff, approved/dispatched by Admin and pickup-confirmed by the assigned Rider through existing HTTP endpoints before receipt tests. Two same-named municipalities in different provinces were used: Santa Cruz, Laguna → Santa Cruz, Marinduque.

Native first receipt added one `parcel_received` event and one `mobile.receive_transfer` activity; batch remained in_transit. The second added its own `parcel_received`, final `received`, and one additional mobile receipt activity; batch completed. Native mobile activity record IDs identify transfer legs; browser `logistics.scan.complete-hub-transfer` activity record IDs identify shipment IDs. This observed distinction is not counted as a defect without a violated consumer/audit contract. Browser receipts also maintained correct selected member counts.

## Comparison of input modes

| Mode | Actually exercised | Persisted operational result | Remaining gap |
|---|---|---|---|
| Android standalone | Flutter widget/SQLite tests plus real Laravel API receipt probes | Receive and transfer receipt/status/custody/history passed | APK → actual network → Laravel confirmation and real optics NOT TESTED |
| Android paired | Native API claim/relay to actual Chromium and backend relay tests | Code arrived, duplicate deduplicated, no receipt/custody mutation | Physical phone pairing camera/network/disconnect NOT TESTED |
| Website USB | Actual Chromium tracking field + Enter, real backend writes | Lookup read-only; Receive & Sort persisted correctly | USB scanner hardware/keyboard suffix/configuration NOT TESTED |
| Website camera | Existing JS camera callback/HTTPS/dedup tests and Flutter camera-widget tests | JavaScript lookup/locking behavior passed with simulated camera | Real webcam/phone optics, lighting, permissions and CDN-loaded camera library NOT TESTED |
| Manual entry | Actual Chromium typing/Find and receive; real transfer lookup/receipt | Correct parcel/status, permitted button, real history/activity | Broader mobile browser/accessibility/device combinations NOT TESTED |

## Automated results and exact commands

Final retained runs (not unique workflows):

| Suite | PASS | FAIL | ERROR | Assertions |
|---|---:|---:|---:|---:|
| MobileScannerTest | 9 | 0 | 0 | 134 |
| PhoneScannerTest | 9 | 0 | 0 | 139 |
| HubTransferWorkflowTest | 25 | 0 | 0 | 340 |
| ParcelCustodyTest | 27 | 0 | 0 | 353 |
| HubStaffDefectCorrectionsTest | 33 | 0 | 0 | 352 |
| RiderBarangayCoverageTest | 12 | 0 | 0 | 122 |
| Origin evidence probe, including 9 inherited mobile tests | 10 | 0 | 0 | 147 |
| Native partial-transfer evidence probe | 1 | 0 | 0 | 20 |
| Negative scope/state evidence probe | 1 | 0 | 0 | 15 |
| **PHP final retained total** | **127** | **0** | **0** | **1,622** |
| JavaScript scanner tests | 5 | 0 | 0 | — |
| Flutter existing tests | 24 | 0 | 0 | — |
| Actual Chromium cases | 5 | 0 | 0 | — |

Existing selected suites were not weakened or skipped. Some test classes inherit tests, so these totals are executions. Repeated harness attempts and the repeated transfer export probe are not added as independent coverage. This is focused regression, not a new full-system run; no new complete COD/GCash/payroll/commission regression is claimed.

Commands executed from repository root unless specified:

```text
python storage/app/private/scanner-functional-verification/run_backend.py
```

That runner explicitly sets testing environment, SQLite `:memory:`, empty DB_URL, array cache/session/mail, sync queue and absent private config-cache path. It executes this exact pattern once for each of the six named existing suites, preserving per-suite logs and JUnit:

```text
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit tests/Feature/<Suite>.php --log-junit <private-evidence>/<Suite>.xml
python storage/app/private/scanner-functional-verification/run_probe.py
python storage/app/private/scanner-functional-verification/run_transfer_probe.py
python storage/app/private/scanner-functional-verification/run_negative_probe.py
node --test tests/JavaScript/phone-scanner.test.cjs
node tests/JavaScript/phone-scanner.test.cjs
flutter test --no-pub --reporter expanded
python storage/app/private/scanner-functional-verification/run_browser.py
python storage/app/private/scanner-functional-verification/run_transfer_browser.py
python storage/app/private/scanner-functional-verification/run_relay_browser.py
python storage/app/private/scanner-functional-verification/summarize.py
python storage/app/private/scanner-functional-verification/read_evidence.py
```

Flutter ran in `mobile/pocketfinds_scanner`. Private probe runners use the same forced isolated environment and PHPUnit command pattern; transfer/negative probes use explicit `--filter` for their single new probe. Browser runners start `php -S 127.0.0.1:18836 -t <repo>/public <private-router>` themselves and stop only their owned process in `finally`. Preparation scripts only generated the private probes/routers/fixtures. No Composer setup, application migrations against the configured database, production key generation, payment, external app login or production endpoint probe was run.

## Failures, harness corrections and warnings

No confirmed application defect was reproduced in the final tested paths. Execution problems were distinguished from application failures:

- Initial `node --test` failed to spawn its child process (`EPERM`). Running the unchanged test file directly executed all five tests successfully; no assertion was skipped.
- Sandboxed Flutter stalled and was interrupted. A permitted SDK-access run finished all 24 tests. It emitted a `path_provider_windows` default-plugin warning. This is a dependency/toolchain review item; no lockfile or dependency was changed and it did not fail the executed tests.
- Initial Playwright process creation was denied (`WinError 5`); permitted rerun used Chromium successfully.
- Browser login harness initially tried typing into readonly-until-focused fields; corrected temporary harness clicks the fields first. Transfer evidence initially queried a nonexistent `hub_transfer_parcels` table; corrected to existing `hub_transfer_members`. Relay harness initially read an empty asynchronous pairing link; corrected to wait for its value. These were harness defects, not production application failures.
- Origin API probe initially omitted guard reset between requests and received 401; corrected its temporary auth setup to match existing API test practice. Negative probe initially collided with a framework method name; renamed the temporary helper. No existing test/application code was changed.
- One PowerShell quoting attempt to edit the temporary browser harness failed; the subsequent file-based edit succeeded. A process-metadata inspection was denied; this does not establish a scanner failure.

Logs for final runs are retained. Earlier failed-attempt outputs are described here and in tool execution history; some temporary harness log files contain the final rerun rather than every initial attempt. Do not interpret the final PASS totals as evidence those environment/harness problems never occurred.

## Remaining risks and release decision

1. **NOT TESTED — physical full journey:** Android standalone confirmation, offline/reconnect against real backend, torch/camera permissions, USB scanner and real phone-to-computer pairing. Local automation does not certify a physical scan or possession.
2. **NOT TESTED — real MySQL races:** selected tests exercise SQLite transactions and sequential idempotency, not concurrent MySQL scanner operations. No production database or disposable MySQL concurrency run was used here.
3. **NOT TESTED — hosted security/operations:** staging TLS/certificate, persistent shared cache locks, process restarts, token expiry across devices, CDN connectivity and network interruptions. The local HTTP test router is intentionally non-production and cannot establish secure-cookie/HTTPS readiness.
4. **NEEDS REVIEW — client lifetime/retention:** pending online-action/paired-send identifiers are in memory; app termination requires fresh authoritative review. Background sync is not implemented and older local records remain retained. No new feature is proposed or implied.
5. **NEEDS REVIEW — presentation:** existing municipality fallback wording may imply broader rider eligibility than the backend actually allows. Native and website confirmation UI differs. No proven unauthorized assignment or invalid custody result was observed; wording/SOP acceptance remains separate.
6. **BLOCKED — staging acceptance:** separate staging server/domain and existing dedicated staging APP_KEY remain prerequisites. Scanner default is the production API; use explicit staging build configuration before any real-device disposable testing. Do not generate/copy a production key or point synthetic tests at production.

The tested Hub Staff receipt and relay paths actually performed the expected database operations, including partial membership and physical-evidence custody interpretation. This supports proceeding to isolated hosted/device acceptance, not declaring all scanner modes production-ready. No defects were fixed during this task. Documentation and private disposable probes are the complete changes; approved application source and frozen candidate remain intact.
