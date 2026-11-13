# Hub Scanner — physical device verification

12 November 2026. Tests used the connected **Infinix X695** and a synthetic local test environment. The physical phone QR/barcode, receipt, reconnection and computer relay scenarios passed within the scope below. A dedicated hosted staging environment, physical USB barcode scanner and workstation camera were unavailable.

## Isolation

The installed production-configured scanner was not launched, replaced or cleared. A private copy of the existing Flutter project was built with separate Android application ID `com.pocketfinds.scannerqa` and `API_BASE_URL=http://127.0.0.1:18836/api/v1/mobile`. Debug cleartext is limited to this loopback test setup; this is not a production build or hosted staging APK. APK SHA-256: `717407af005d534c03d959773116f31b86fc1bf72a2db245973c13785d726586`.

The physical phone reached a loopback-only PHP server via `adb reverse`; the server used a copied synthetic SQLite fixture, separate storage/session/cache, testing environment, synthetic test key and fake outbound HTTP/mail. Test account `hub@gmail.com` / `hub123` exists only in that isolated fixture. No actual email was sent and the production API was not contacted. The separate test app was installed alongside the existing app. A synthetic QR and Code 128 barcode encoded `QA-PAIRED`.

After verification the owned local server was identity-checked and stopped, the test-specific adb reverse was removed, and the test app was force-stopped. The test app remains installed under its separate package ID; its endpoint is no longer running. The existing installed scanner and its data were preserved. No application source, production configuration, production records, financial settings or release candidate was changed.

## Actual results

| Requested item | Result | Evidence / limit |
|---|---|---|
| Android camera QR scanning | **PASS** | User physically scanned the synthetic QR on the connected phone; Android UI subsequently displayed `QA-PAIRED`. |
| Android camera barcode scanning | **PASS** | User physically scanned the Code 128 image and reported the parcel appeared. Subsequent database check showed no second receipt. |
| Flashlight | **PASS** | User physically toggled the light on and off and reported both worked. |
| Camera permission | **PASS for grant** | User allowed access and Android `dumpsys package` reported CAMERA `granted=true`. Denial/revocation flow **NOT TESTED**. |
| Manual tracking-number fallback | **PASS** | User entered `QA-PAIRED` in the camera manual field and reported the parcel appeared. |
| Offline recording and network recovery | **PASS for observed interruption** | During physical QR scanning the adb reverse tunnel was interrupted; phone displayed one pending scan and unable-to-connect message. After restoring the tunnel, user confirmed lookup worked; SQLite contained accepted scan-verification receipts. The test did not deliberately exercise airplane mode, process termination or extended outages. |
| Phone-to-computer pairing and scan relay | **PASS** | User physically scanned the isolated pairing QR, then scanned and sent the synthetic barcode. Actual Chromium received the code and displayed “Phone scan collected.” Before/after database snapshots remained `sorted`, same receipt timestamp, 2 history rows and 1 activity; relay caused no new custody transition. [Pairing evidence](../storage/app/private/scanner-device-qa/physical-pairing-result.json) and [workstation screenshot](../storage/app/private/scanner-device-qa/paired-workstation.png). The local browser QR was rendered from the same pairing link using a private test generator because its CDN was blocked; physical scan/relay and workstation lookup were real. |
| Physical USB barcode scanner | **NOT TESTED** | User confirmed the available scanning device is the phone; no separate USB barcode scanner was available. The phone relay result does not certify USB hardware. |
| Workstation camera scanning | **BLOCKED** | User confirmed no workstation camera is available. |
| Physical phone parcel lookup and receiving | **PASS** | User scanned `QA-PAIRED`, chose Receive and sort and confirmed “Server accepted the operation.” Isolated database changed `picked_up → sorted`, set `at_sorting_center_at` and `sorted_at` to `2026-11-12 18:25:23`, saved exactly two status-history entries and one `mobile.receive` activity. Later scans/relay did not add another receipt. [Database evidence](../storage/app/private/scanner-device-qa/physical-database-evidence.json), [redacted phone screenshot before receipt](../storage/app/private/scanner-device-qa/phone-test-redacted.png). |

The user noted the phone parcel information felt incomplete. The synthetic record has minimal data, while the existing native interface intentionally exposes limited fields. This observation is **NEEDS REVIEW**, not proof of a defect. Suggested operational fields were tracking number, status, sender/recipient names as authorized, hub route, destination barangay/municipality, latest custody, permitted next action and exception warning; full address/contact should depend on role and operational need. No new fields or features were implemented.

## Commands and setup notes

```text
adb devices -l
python storage/app/private/scanner-device-prepare.py
flutter build apk --debug --no-pub --dart-define=API_BASE_URL=http://127.0.0.1:18836/api/v1/mobile
python storage/app/private/scanner-device-qa/prepare_environment.py
adb reverse tcp:18836 tcp:18836
adb install -r <private-test-apk>
python storage/app/private/scanner-device-qa/pair_browser.py
python storage/app/private/scanner-device-qa/capture_final.py
adb reverse --remove tcp:18836
adb shell am force-stop com.pocketfinds.scannerqa
```

Flutter build ran from the private source copy. Initial `flutter pub get --offline` failed because the cached `path_provider_windows` dependency was unavailable. Reusing the existing resolved local package metadata and building with `--no-pub` succeeded; the existing Windows plugin warning remained. Python QR/barcode tools were installed only in the private test directory. The fixture initially stored a non-bcrypt password, causing a synthetic sign-in error; the fixture was corrected to bcrypt and the isolated login returned HTTP 200. This was a test-fixture issue, not an established application defect.

Phone screenshots were redacted to remove a personal floating overlay and device status area. The paired-workstation screenshot shows only the synthetic test page. Screenshots and fixture evidence remain in the private directory and should not be exposed through the public web root.

## Remaining acceptance

Repeat on a dedicated HTTPS staging host and staging-targeted build; verify TLS, cookies, authentication, shared cache and restart behavior. Test camera denial/revocation, deliberate longer offline recovery, physical USB and workstation camera when available. Verify transfer receipt physically on device and compare Admin/Staff scopes on-device. The existing backend MySQL concurrency evidence remains separate. No new release candidate was frozen.
