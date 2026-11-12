# PocketFinds Scanner — Logistics Admin and Hub Staff verification

12 November 2026. **The executed role, lookup, receiving, offer and synchronization checks PASS within the tested local layers. Physical-device and hosted release readiness remain NOT TESTED.** No scanner functionality was introduced for Buyers, Sellers or Riders. No application code, existing tests, production configuration or production records were changed.

This report combines the immediately preceding [actual functional verification](scanner-functional-verification-report.md) with two additional executed role-specific probes. Previously executed results are explicitly reused, not represented as new runs. The preceding check compared 1,258 frozen-source files with zero differences; the new probes add only private test artifacts and this report.

## Results for every requested function

| Function | Logistics Admin | Hub Staff | Actual evidence and limits |
|---|---|---|---|
| 1. Authentication and permissions | PASS | PASS | Existing executed MobileScannerTest tests approved Admin/Staff login, `/me`, token abilities, revocation/expiry, suspension and closed hubs. Platform Admin, Buyer, Seller and Rider login attempts denied. PhoneScannerTest additionally verifies web relay role restrictions. New Admin probe verifies company-level scope without receipt authority. |
| 2. Barcode/QR scanning — software input handling | PASS | PASS | Executed Flutter camera/manual-fallback tests and JS repeated-detection checks pass; actual Chromium lookup accepted typed and simulated USB-entered codes. Physical optics are separated below. |
| 2a. Physical Android camera, workstation camera and USB hardware | NOT TESTED | NOT TESTED | No new physical barcode/QR scan, torch/permission test or connected USB scanner operation. Tests use simulated capture/input. |
| 3. Parcel lookup and status verification | PASS | PASS | New Admin native lookup returned correct parcel ID, accepted same-company other-hub lookup, rejected foreign-company lookup 404. Prior Staff browser/API tests matched parcel IDs/tracking/status and denied unknown/wrong-hub/foreign-company codes. Native minimal metadata privacy asserted. |
| 4. Origin hub receiving and enforcement | PASS — denied by design | PASS | New Admin: native receipt action absent, attempted operation 422, direct web receive 403, picked_up unchanged, receipt null, no status history/activity. Prior Staff actual Chromium and native API receipts persisted picked_up → sorted, receipt/sort timestamps and status history. |
| 5. Incoming transfer receiving and enforcement | PASS — denied by design | PASS | Executed HubTransferWorkflowTest `test_admin_cannot_record_batch_or_legacy_scan_arrival` denies Admin receipt. Staff destination/no-pickup guard tests pass. Actual Chromium and native probes received a two-parcel batch separately; first receipt left batch in_transit, second completed it. Origin/unrelated receipt blocked in regression tests. |
| 6. Sorting and custody | PASS for company-level lookup; no physical attestation | PASS | Admin lookup retains current status without modifying it. Staff receipt produced two history rows at_sorting_center/sorted and confirmed Pila, Laguna custody; transfer receipts established Santa Cruz, Marinduque custody for received members only. Assignment/lookup alone did not establish possession. |
| 7. Authorized rider offers — website backend | PASS | PASS | New probes exercised scanner lookup on sorted synthetic delivery parcels: stage assign and exactly the covered rider. Existing assignment endpoint persisted offered assignment for both roles; accepted_at/courier_id stayed null and shipment stayed sorted. Wrong-hub Staff offer denied 403; foreign-company Admin offer denied 404. |
| 7a. Rider-offer button click through complete browser UI | NOT TESTED | NOT TESTED | New probe executed the actual lookup and offer HTTP endpoints with database assertions, not the complete dropdown/button click path. Previous Chromium receipt tests did not exercise this offer button. Native app has no offer function; that is existing scope, not a missing new feature. |
| 8. Hub/company boundaries | PASS | PASS | New Admin probe demonstrates company-wide lookup without hub receipt permission. New offer probe demonstrates wrong-hub Staff lookup 404 and offer 403, foreign Admin lookup/offer 404 with no assignment. Existing executed coverage tests enforce company/hub, barangay, availability, approval and vehicle requirements. |
| 9. Duplicate scan and receipt protection — sequential retries | PASS | PASS | Executed API tests replay accepted UUID without extra receipt/history; conflicting payload 409, new invalid repeat receipt 422. Relay duplicate nonce returns duplicate true, same sequence. Intentional independent scan observations can have distinct IDs; these are not duplicate parcel receipts. |
| 9a. Real MySQL concurrent scanner requests | NOT TESTED | NOT TESTED | SQLite/HTTP sequential idempotency and transaction rollback tested; no new disposable MySQL race executed. |
| 10. Offline scan recording/sync — client and backend components | PASS for shared implementation | PASS for shared implementation | Executed Flutter SQLite restart/retry/account-server-isolation tests and Laravel `/scans` idempotency/context checks. Sync creates verification receipt only; shipment/custody/history unchanged. No independent physical offline journey for each role. |
| 10a. Physical Android offline → network recovery → real backend | NOT TESTED | NOT TESTED | No airplane-mode/native APK integration session. Offline operational actions remain disabled. |
| 11. Android/computer relay and workstation integration | PASS for shared protocol; Admin native hardware NOT TESTED | PASS for executed API-to-browser flow | Executed Staff Chromium connection, authenticated mobile API claim, relay and duplicate retry displayed parcel without receiving it. Existing suite covers ownership, browser binding, single mobile token, expiration and disconnect. No new actual Admin pairing browser session or physical two-device scan. |

PASS denotes the exact executed layer, not universal device/role coverage. The common scanner client/API code is shared between supported roles, but shared implementation does not prove an unexecuted physical journey. No Buyer/Seller/Rider scanner interface is supported or requested.

## Additional tests executed in this review

Separate private probes reuse existing disposable fixture classes and force testing environment, SQLite `:memory:`, array sessions/cache/mail, sync queue, empty DB_URL and a private absent config-cache path. Existing test files were not edited. No production database was connected.

### Admin scope and receipt boundary

`ScannerRoleTest::test_admin_company_lookup_cannot_attest_hub_receipt` — **PASS, 15 assertions**.

1. Create approved synthetic Admin from local mobile fixture; issue/use its scanner token.
2. Native lookup picked-up company parcel: 200, exact parcel ID, no receive action.
3. Submit native receive operation: 422; open web Scan Parcel: redirect to monitor lookup; submit direct web receive: 403.
4. Move disposable parcel route to another hub in the same company: Admin lookup still 200.
5. Change disposable parcel company to Foreign: lookup 404.
6. Query final state: picked_up, null origin receipt, zero order history and activity. Rejected native attempt may persist an error operation receipt; no physical parcel mutation occurred.

Evidence: [admin-evidence.json](../storage/app/private/scanner-role-verification/admin-evidence.json), [test output](../storage/app/private/scanner-role-verification/ScannerRoleTest.txt), corresponding JUnit XML.

### Scanner rider choices and persisted offer

`ScannerOfferTest::test_scanner_choices_and_persisted_offers_for_both_roles` — **PASS, 26 assertions**.

1. Create synthetic company, operational hub, approved Admin/Staff and online own-two-wheel Rider; configure existing destination barangay coverage using the normal coverage endpoint.
2. For each role, create a separate sorted delivery parcel addressed to the covered barangay; POST web scanner lookup.
3. Assert stage assign and rider list contains exactly the covered Rider; PATCH existing assignment endpoint.
4. Query database: assignment offered; accepted_at null; parcel courier_id null; parcel remains sorted. Sending an offer does not confirm acceptance or physical handover.
5. Assign disposable Staff account to another operational same-company hub and attempt lookup/offer: 404/403 respectively.
6. Use a separate approved foreign-company Admin and attempt lookup/offer: 404/404. No assignment on denied parcel.

Evidence: [offer-evidence.json](../storage/app/private/scanner-role-verification/offer-evidence.json), [test output](../storage/app/private/scanner-role-verification/ScannerOfferTest.txt), corresponding JUnit XML.

The offer probe initially lacked `unserviceable_areas` in its minimal fixture, causing a 500. Its private fixture was completed from the existing baseline schema. A wrong-hub offer initially expected 404; inspection confirmed the existing `LogisticsAccess::requireLocation` contract returns 403, while lookup returns 404. The temporary expectation was aligned to that explicit guard, with persisted-state checks preserved. Finally, changing an existing Admin's business_name invoked the existing ownership-transfer guard; a separate foreign Admin was created instead. These were probe setup/contract errors, not confirmed scanner defects; no existing assertion or authorization was weakened. Final retained probes both pass.

Executed command:

```text
python storage/app/private/scanner-role-verification/run.py
```

The runner records exact commands in [results.json](../storage/app/private/scanner-role-verification/results.json). For each probe it executes:

```text
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit <private-probe.php> --filter <named-test> --log-junit <private-result.xml>
```

New final retained results: **2 tests PASS, 41 assertions, 0 FAIL, 0 ERROR**. Repeated harness iterations are not additional coverage. The preceding report retains **127 PHP executions / 1,622 assertions, 24 Flutter tests, 5 JavaScript tests and 5 Chromium cases**, all passing in their final runs. Those prior results were not rerun or added as unique functions in this review.

## Role boundaries and remaining acceptance

Logistics Admin may use existing company-level lookup and eligible offer/dispatch capabilities, but cannot attest physical origin/destination hub receipt. Hub Staff may attest authorized assigned-hub receipt and offer eligible riders at its permitted hub; it cannot dispatch transfer batches as Admin. Native app only exposes receive/sort and destination-transfer-receive operations when allowed; web management capabilities do not become native actions.

The existing three-combined-job, barangay, approval, online, vehicle and company restrictions remain unchanged. Four-wheel transfer eligibility remains separate. No financial calculations or commission activation were performed.

No confirmed application defect was reproduced by these additional checks. Complete hosted/device release readiness is still **NOT TESTED**: staging target/key, TLS/shared cache, real phone optics/offline/pairing, USB hardware, complete offer-button UI and MySQL concurrency require separate acceptance. Build a staging-targeted APK before device tests: the current default API address points to production. Do not send synthetic fixtures to production or infer receipt from a scan/lookup/sync result.
