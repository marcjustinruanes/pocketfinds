# PocketFinds Scanner — complete feature and function inventory

12 November 2026. Scope: existing Flutter Android application, Laravel mobile API, computer pairing and website workstation scanning. **Source inspection only: no new automated, browser, physical-camera or scanner tests were executed for this report.** Implemented means present in inspected code, not certified working on the staging host. No application/configuration/record changes were made.

## Architecture and entry points

The Android app has two modes: standalone barcode recording/online parcel receipt, and code relay to a computer. Laravel remains the system of record. Both modes reuse existing parcel authorization and operational workflows; the app is not a separate logistics system.

| Component | Source | Responsibility |
|---|---|---|
| Android entry/state | [main.dart](../mobile/pocketfinds_scanner/lib/main.dart) | Startup, login/logout, modes, synchronization, lookup, confirmed actions, pairing and relay |
| Android interface | [scanner_ui.dart](../mobile/pocketfinds_scanner/lib/scanner_ui.dart) | Sign-in, account context, connection/mode controls, parcel cards and scan history |
| Camera | [camera_screen.dart](../mobile/pocketfinds_scanner/lib/camera_screen.dart) | Barcode/QR capture, torch and manual fallback |
| Connection/session | [scanner_config.dart](../mobile/pocketfinds_scanner/lib/scanner_config.dart), [session_startup.dart](../mobile/pocketfinds_scanner/lib/session_startup.dart) | Build-time server configuration, saved session checks and offline startup |
| Client API/queue | [api.dart](../mobile/pocketfinds_scanner/lib/api.dart), [scan_queue.dart](../mobile/pocketfinds_scanner/lib/scan_queue.dart) | HTTP requests, local SQLite records and synchronization |
| Mobile backend | [MobileScannerController.php](../app/Http/Controllers/MobileScannerController.php), [MobileScannerAccess.php](../app/Http/Middleware/MobileScannerAccess.php), [api.php](../routes/api.php) | Authentication, scope, lookup, synchronization, operations and mobile pairing |
| Relay | [PhoneScanner.php](../app/Services/PhoneScanner.php), [PhoneScannerController.php](../app/Http/Controllers/PhoneScannerController.php) | Expiring locked relay state, browser binding, code sequencing and acknowledgments |
| Workstation | [scan.blade.php](../resources/views/logistics/scan.blade.php), [parcel-scan.js](../public/js/parcel-scan.js), [LogisticsController.php](../app/Http/Controllers/LogisticsController.php) | USB/camera/manual lookup and existing authorized parcel actions |

Hub Staff uses the dedicated website Scan Parcel page. Logistics Admin's `scan()` entry currently redirects to the monitor with lookup enabled. Do not assume both roles see the same workstation page or that the native app exposes all website actions.

## Android account and connection functions

| ID | Function | Existing behavior |
|---|---|---|
| AC01 | Sign in | Existing email/password credentials; field/backend validation and failed-login feedback |
| AC02 | Password visibility | Hidden initially; Show password / Hide password toggle |
| AC03 | Role eligibility | Approved logistics-company Admin or Hub Staff only; excludes Rider, Seller, Buyer and platform-admin identities |
| AC04 | Hub validation | Staff requires an authorized operational assigned hub; current account/company/hub restrictions checked by backend |
| AC05 | Token issuance | Eight-hour Sanctum token with scanner ability |
| AC06 | Secure session storage | Saved token and identity in Flutter secure storage; no password persistence feature |
| AC07 | Restore session | Server namespace/expiry checked; online `/me` validates identity; invalid/revoked/suspended sessions require sign-in |
| AC08 | Offline startup | Known unexpired saved session can restore limited offline context on temporary server failure; no offline authorization to mutate parcels |
| AC09 | Logout | Revokes current API token when reachable; client sign-out preserves saved scan records; API logout remains available after account suspension |
| AC10 | Server selection | Build-time `API_BASE_URL`; normal login has no editable address field; release configuration rejects HTTP and malformed paths/URLs |
| AC11 | Connection status | Reachability/last-check feedback, errors and Retry connection |
| AC12 | Account context | PocketFinds Logistics/Hub subtitle, user identity and company or assigned-hub municipality context |
| AC13 | Mode navigation | Scan on phone and Connect to computer; busy/uncertain-action guards restrict overlapping operations |

The configured default API is `https://pocketfindsph.shop/api/v1/mobile`. This is a source setting, not a new connectivity verification. **A staging app build must explicitly target the separate staging API; never use this default for disposable staging acceptance.**

## Capture, standalone lookup and parcel operations

| ID | Function | Existing behavior |
|---|---|---|
| SC01 | Camera barcode/QR capture | Shared native camera screen for standalone scanning and computer pairing |
| SC02 | Aiming frame | Visible framing/instruction overlay |
| SC03 | Flashlight | Torch toggle; hardware behavior requires device testing |
| SC04 | Manual entry | Enter parcel code or pairing link; submit with button/keyboard |
| SC05 | Camera fallback | Permission/unavailable message; manual input remains available |
| SC06 | Capture deduplication | Scanner no-duplicates mode plus one-result selection guard; later intentional standalone scans receive new record UUIDs |
| SC07 | Save standalone scan | Persist local scan before lookup; unknown codes can be recorded without claiming a valid parcel |
| SC08 | Parcel lookup | Tracking number, then existing order-number lookup, subject to backend scope |
| SC09 | Parcel display | Shipment/tracking/order IDs, status/label, origin/destination hub, current leg/progress and update time |
| SC10 | Refresh lookup | Request fresh authoritative parcel/action state |
| SC11 | Receive and sort | Native action `receive`, only when backend advertises and permits it; origin physical receipt is not an Admin shortcut |
| SC12 | Transfer receipt | Native action `receive_transfer`, requiring destination permission and rider pickup confirmation under existing workflow |
| SC13 | Explicit confirmation | Cancel/Confirm dialog before operation; success only after server acceptance |
| SC14 | Stale-state protection | Expected shipment status and currently available action checked inside the guarded transaction |
| SC15 | Operation retry | Durable server receipt by actor/UUID and payload hash; identical accepted request returns prior result; conflicting payload rejected |
| SC16 | Uncertain result | In-memory pending operation retained for retry; another parcel blocked while unresolved; not a durable offline action queue |
| SC17 | Post-operation refresh | Clears/refetches parcel details after accepted action |
| SC18 | Audit/rollback | Delegates to existing receipt/transfer services; operation, history, receipt and activity writes participate in transactions |

Native lookup omits the website's buyer name, contact and delivery-address fields. Successful scan verification is distinct from physical receipt. Neither scanning nor pairing alone assigns a rider, changes custody or completes delivery.

## Offline records, synchronization and history

| ID | Function | Existing behavior |
|---|---|---|
| OF01 | Persistent scan records | SQLite UUID, server namespace, owner, raw code, original company/hub context and UTC timestamp |
| OF02 | Queue states | Pending Sync, Syncing, Accepted, Rejected, Retry Required |
| OF03 | Automatic sync | Periodic check while app is running (20 seconds); no Android background service |
| OF04 | Manual sync | Sync saved scans control/manual retry pass |
| OF05 | Identity check | `/me` verifies current identity before queue processing |
| OF06 | Account/server separation | Only active server/account records selected; tokens are not restored to a differently configured server |
| OF07 | Context validation | Backend rejects scan context after incompatible company/hub assignment change |
| OF08 | Batch selection | Up to 20 candidate rows, ordered by recorded scan time and ID per pass |
| OF09 | Retry policy | Backoff and maximum five automatic attempts per record; manual pass can retry eligible Retry Required records |
| OF10 | Permanent rejection | 400/404/409/422 classified Rejected rather than repeatedly replayed as parcel operations |
| OF11 | Authentication pause | 401/403 returns current scan to Pending Sync and stops pass for reauthentication/review |
| OF12 | Restart recovery | Interrupted Syncing records return to pending using original UUID |
| OF13 | Scan receipts | Server records verification result; accepted receipt returns no executable cached actions |
| OF14 | History | Pending summary, recent state/error/status feedback and local time display with AM/PM |
| OF15 | Retention behavior | Recent UI selects at most 100 records; older SQLite rows remain retained; sign-out does not erase them |

**Offline actions are disabled.** The queue stores barcode observations, not future receipt/dispatch/assignment/delivery/cash actions. Paired codes are not persisted for offline replay. Accepted historical receipt data is not a current-status feed or current permission to act.

## Computer pairing and relay

| ID | Function | Existing behavior |
|---|---|---|
| PR01 | Create connection | Workstation creates 64-character pairing token with ten-minute lifetime |
| PR02 | Pairing display | QR, connection link, instructions and connection status on workstation |
| PR03 | Claim from Android | Same signed-in user/company/hub/role; one mobile API token may claim session |
| PR04 | Browser binding | Only creating browser session can consume/acknowledge/disconnect its computer relay |
| PR05 | Relay code | Phone sends tracking/order code after backend visibility check; no parcel write |
| PR06 | Send idempotency | Scan nonce reused for retry; same nonce/different code rejected |
| PR07 | Sequencing | Monotonic sequence, computer polling and acknowledgments; consumed events removed |
| PR08 | Review pacing | Computer reviews one parcel and chooses Next phone scan; no automatic receipt/status write |
| PR09 | Bounded queue | At most 20 uncollected events and 1,000 seen scan IDs per connection |
| PR10 | Session status | Both devices can observe connection/explicit disconnection |
| PR11 | Disconnect | Explicit phone or computer disconnect clears pending relay events and closes connection |
| PR12 | Expiry | Closed/expired session cannot be revived; abandoned sessions expire rather than treating Wi-Fi loss as explicit disconnect |
| PR13 | Contention protection | Lock-capable cache required; timeout/unavailable feedback; role/company/hub changes invalidate incompatible use |
| PR14 | Legacy endpoint isolation | Web relay sends cannot inject codes into a claimed native-mobile pairing |

Pairing requires the same account on the phone and its computer session. This does not authorize sharing credentials between Origin and Destination Staff. Each operator retains a separate account and hub responsibility.

## Website workstation functions

| ID | Function | Existing behavior |
|---|---|---|
| WS01 | USB scanning | Keyboard-style scanner input into tracking field, form submission |
| WS02 | Workstation camera | Start/Stop camera; HTTPS/permission checks and repeated-code suppression |
| WS03 | Manual lookup | Tracking/order field and Find button |
| WS04 | Scanner preference | Existing USB preference affects camera presentation; phone option remains available |
| WS05 | Lookup feedback | Loading, found/not-found, failed-request and session-expiry feedback; stale responses ignored |
| WS06 | Shipment card | Tracking/status/custody, order, last update, buyer/contact/address, delivery area/courier within website scope |
| WS07 | Origin receiving | Existing authorized receive/sort action with backend physical-custody requirements |
| WS08 | Transfer receipt | Mark received at current receiving hub, with rider-confirmation guidance and permission checks |
| WS09 | Transfer guidance | Awaiting request/approval, leg progress and pickup-confirmation messages |
| WS10 | Approved-batch dispatch | Existing authorized Admin-context transfer-rider selection/dispatch action; not a Staff dispatch permission |
| WS11 | Delivery offer | Eligible delivery rider selection and Send Rider Offer; barangay/availability/capacity checks reused |
| WS12 | Serviceability feedback | Unserviceable area and no eligible rider messages |
| WS13 | Status changes | Only existing permitted next transition; rider out-for-delivery action remains with Rider |
| WS14 | Failed delivery/retry | Reason prompt, guarded attempt token and existing exhaustion/retry restrictions |
| WS15 | Action locking | Buttons guarded while request and refreshed lookup run; safe error feedback |
| WS16 | Phone connection | Create QR/link, collect scans, Next phone scan, status and disconnect |
| WS17 | Permission/holds | Hub-scoped permitted actions; condition/recovery/RTS holds suppress assignment as implemented |

Workstation source contains municipality-match fallback wording saying “showing all approved riders”; its underlying rider list is built with the shared eligible-delivery service, so this wording must not be interpreted as a barangay bypass. This is a source-level wording review item, not a demonstrated authorization defect. Native app receipt controls and workstation status controls have different scope.

## API inventory

Base `/api/v1/mobile`. Protected functions require scanner bearer token; session cookies alone are insufficient.

| Method | Path | Function |
|---|---|---|
| POST | `/login` | Validate credentials/role and issue token |
| POST | `/logout` | Revoke current token |
| GET | `/me` | Current scoped identity and offline-actions-disabled flag |
| POST | `/parcels/lookup` | Fresh permitted parcel and available native actions |
| POST | `/scans` | Idempotent barcode verification/sync receipt, no parcel action |
| POST | `/operations` | Confirm `receive` or `receive_transfer` under current guards |
| POST | `/pair/{token}` | Claim connection for mobile token |
| GET | `/pair/{token}/status` | Read authorized connection state |
| POST | `/pair/{token}/disconnect` | Close paired connection |
| POST | `/pair/{token}/codes` | Relay visible parcel code |

Web relay also has create, phone instruction page, events poll, codes, acknowledgment and delete/disconnect routes under `/logistics/scan`. Login throttle is 5/minute; mobile protected group 60/minute; logout 30/minute. Web create is 10/minute and poll/code/ack each 60/minute with their configured limiter keys. These are configured limits, not load-test results.

Expected error categories: 401 authentication/token; 403 role/company/hub/pair authorization; 404 unavailable parcel; 409 stale state, conflicting UUID or pairing; 410 expired/closed connection; 422 validation/transition; 429 throttling/relay queue; 5xx temporary/server errors. A response redirect or HTTP success alone is not sufficient operational evidence: verify persisted authorized state.

## Persistence, authorization and financial boundaries

- SQLite holds standalone observations, not buyer personal details. Android backup is disabled by existing app configuration.
- Secure storage holds API session identity/token. Do not export it into reports/logs or use production tokens in staging.
- `personal_access_tokens` and `mobile_scanner_operations` support authentication and durable receipts; cache holds expiring relay state. Existing shipment/order/transfer/custody/activity tables remain authoritative.
- Mobile operation processing uses actor locks, current shipment checks and existing operation locking. Matching retries return durable outcomes; payload reuse conflicts. Concurrency guarantees still require actual MySQL verification for the target workload.
- Company/hub visibility does not grant physical receiving, dispatch or other mutation authority. Current backend permission checks remain decisive even if a client displays a stale action.
- No standalone native finance screen, COD cash counting, remittance, GCash verification, commission activation, payout or compensation-setting function exists in this app. Existing reused parcel workflows can affect operational/financial state indirectly and must retain regression coverage.

## What is not implemented or not certified

| Item | Classification | Operational implication |
|---|---|---|
| Durable offline operational actions | NOT IMPLEMENTED | Online confirmation required; recording a scan is not receipt |
| Background Android sync | NOT IMPLEMENTED | Keep app running or manually synchronize later |
| Native transfer approval/dispatch, rider management/assignment, final-delivery proof/OTP, financial management | NOT IMPLEMENTED in scanner UI | Use authorized existing web/Rider workflows |
| Persistent uncertain online-action/relay identifier across termination | LIMITATION | After termination refresh authoritative state before deciding another action; do not assume replay safety from local scan queue |
| Record retention/cleanup policy | NEEDS REVIEW | Older records remain in SQLite; operational receipt/history retention needs owner review |
| Pairing cache across processes/restarts | NEEDS MANUAL VERIFICATION | Dedicated persistent shared lock-capable cache required; array cache is not a cross-request staging solution |
| Physical Android camera/torch/permissions and USB device behavior | NEEDS MANUAL VERIFICATION | Test actual devices/labels/lighting and denied permissions |
| Two-device session expiry, disconnect and network interruption | NEEDS MANUAL VERIFICATION | Inspect both devices and persisted outcomes; mocked UI tests are insufficient |
| Hosted TLS, tokens, private data and company/hub restrictions | NEEDS MANUAL VERIFICATION | Execute on isolated staging host, including suspended/wrong-hub/foreign-company cases |
| Mobile-operation MySQL races and failure injection | NEEDS MANUAL VERIFICATION | Disposable MySQL only; no new race execution claimed here |
| Workstation external camera-library dependency | NEEDS MANUAL VERIFICATION | Test CDN failure and USB/manual fallback on target network |
| Signed release distribution/update process | NEEDS REVIEW | Historical debug APK installation is not production signing/distribution approval |
| Staging endpoint | BLOCKED until provided | Default targets production; dedicated stage build/configuration required |
| Dedicated staging APP_KEY | BLOCKED | Owner reports unknown/unavailable; no key generation or production-key copying authorized |

## Existing tests and evidence boundaries

Inspected test sources include [MobileScannerTest](../tests/Feature/MobileScannerTest.php), [PhoneScannerTest](../tests/Feature/PhoneScannerTest.php), [JavaScript scanner tests](../tests/JavaScript/phone-scanner.test.cjs) and [Flutter tests](../mobile/pocketfinds_scanner/test). They contain scenarios for role/token scope, privacy, idempotency, stale state, audit rollback, destination receipt, pairing ownership/expiry/disconnect, queue persistence/retries, camera fallback, configuration/startup and interface guards.

These test sources establish intended coverage, **not new PASS results**. Historical installation/build/test observations are recorded in [mobile-scanner.md](mobile-scanner.md); its original eight-route table predates status/disconnect additions (current API source has ten routes), and its early statement about no embedded production URL is superseded by current automatic endpoint configuration. Historical successful APK installation/launch does not verify physical camera/pairing operation. No tests or external endpoint probes were run for this inventory, and no staging instance was started.

## Recommended verification order

1. Supply dedicated staging host/domain and existing approved key; build with explicit staging API URL and isolate DB/cache/mail/storage.
2. Verify login/logout/session restoration and all supported/unsupported roles; verify account/server switching never mixes queued data or tokens.
3. Test actual camera/torch/manual input, unknown labels, offline recording, restart, reconnect and manual retry with disposable data.
4. Verify origin receipt and destination transfer receipt independently, including wrong hub/company, no pickup witness, stale state, duplicate requests and rollback failures.
5. Verify pairing ownership, browser binding, queue sequencing, disconnect/expiry and interrupted sends using a real phone and workstation.
6. Re-run existing backend/JavaScript/Flutter suites in isolated environments and add execution evidence for MySQL concurrency/device acceptance before calling the scanner release-ready. Do not weaken, suppress or infer passing tests.

This inventory adds documentation only. The previously frozen source candidate remains unchanged; this later report is not retroactively included in its manifest. See [staging plan](staging-deployment-plan.md) and [acceptance register](staging-acceptance-checklist.md) for the separate release gates.
