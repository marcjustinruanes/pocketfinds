# PocketFinds Scanner — Android implementation, October 14, 2026

Implemented a first Android scanner release with separate standalone and computer-pairing modes. The Laravel website remains the parcel system of record. Offline barcode recording is supported; offline operational actions are deliberately disabled. This report replaces the browser-phone-camera setup instructions in `phone-parcel-scanning.md`.

The latest app build uses **`https://pocketfindsph.shop/api/v1/mobile` automatically**, without an address-entry screen. See [automatic address and tunnel verification](scanner-automatic-address.md) for the later startup changes and the user-approved laptop tunnel setup.

## Android UI follow-up

The subsequent UI update adds a branded sign-in card, clearly labeled connection settings, compact mode cards explaining where parcel confirmation happens, connection/pending-scan summaries, parcel route/status cards, readable scan-history badges, and a camera aiming frame. Barcode verification is labeled separately from parcel receipt. Server authorization, synchronization, and operation-confirmation behavior were retained. After a temporary USB disconnection, the updated APK was successfully installed on the user's Infinix X695 with `adb install -r` and launched; physical camera/pairing interaction remains unverified.

Created `mobile/pocketfinds_scanner/lib/scanner_ui.dart` and `mobile/pocketfinds_scanner/test/ui_test.dart`; modified `lib/main.dart`, `lib/camera_screen.dart`, and this report. The UI tests cover mode callbacks, busy-state controls, uncertain-action guards, pairing explanations, small-screen layout, and login connection settings. Final `flutter test`: **15 passed**; `flutter analyze`: **No issues found**; Dart formatting and `git diff --check` passed. The first UI test run had finder/scroll timing errors, which were corrected; missing-brace lint was also corrected. The backend was not changed or retested for this UI-only follow-up. Physical visual/camera verification remains outstanding.

## Inspection and architecture

The inspected application runs Laravel 13.25.0 / PHP 8.5.9. Its authentication was web-session based, without registered API routes or Sanctum. Existing roles, approved-account checks, company names, assigned operational hubs, shipment/order models, hub transfer batches/legs, recruitment guards, and activity/history tables remain authoritative. `LogisticsAccess` scopes parcel visibility to the company and relevant hub route. `LogisticsController::scanLookup` determines stages and permitted scan actions; hub transfer receipt delegates to `HubTransferWorkflow`. Seller waybill QR codes contain the existing shipment tracking number; lookup also accepts the existing order number.

The installed Flutter SDK is 3.38.4 / Dart 3.10.3. The separate Android project is `mobile/pocketfinds_scanner`. Dependency resolution selected `mobile_scanner 7.4.2`, `http 1.6.0`, `flutter_secure_storage 11.2.0`, `sqflite 2.4.2+1`, `path 1.9.1`, and `uuid 4.6.0`. Camera input is shared between both modes; SQLite persists only standalone barcode records. Flutter secure storage holds the API token/session identity. No token, password, database credential, or production URL is embedded in the app. SQLite tests use `sqflite_common_ffi`.

Laravel Sanctum 4.3.3 issues eight-hour, scanner-only bearer tokens for existing approved Logistics Admin and Hub Staff accounts. Every protected request checks the token and current account status, role, company, and assigned operational hub when applicable. Web session cookies cannot substitute for a scanner token. Logout revokes the current token, including when the account was subsequently suspended. No registration, recruitment, status-maintenance, or OAuth behavior was changed.

## Modes

**Standalone Scanner:** Login, choose the mode, and scan or enter a parcel code. A standalone barcode is persisted as Pending Sync before lookup. Unknown/unavailable parcels can still be recorded, but their existence is not claimed. Current online lookup returns minimal parcel metadata and available actions. This release supports **Receive and sort** and **Receive at destination hub**, with explicit confirmation. Transfer receipt requires the destination permission and existing rider pickup confirmation. Rider assignment, transfer dispatch, and other management actions remain in the web interface; the app does not invent new transitions.

Successful parcel writes reuse existing controllers/services inside a transaction, including existing status/transfer history plus an activity log. A durable receipt stores each operation's owner, UUID, payload hash, HTTP result and response. Repeating an accepted operation returns the original result; reusing its UUID for a different payload returns 409. A failed audit insert rolls back parcel/history/receipt writes. Temporary failures do not create a successful receipt. The app retains an uncertain online action's identifier in memory for a retry and blocks another parcel while that result is unresolved; it does not create an offline action queue.

**Pair with Computer:** The computer opens its existing Scan Parcel page and creates a ten-minute connection. The Android app signs into the same account and scans that QR in Pair with Computer. The relay is reused, with the consumer bound to the creating browser session and the producer bound to one claimed mobile token. Another token cannot claim an already paired session. Expired/disconnected sessions cannot be revived. The browser collects one code at a time and preserves its existing review, authorization, and confirmation workflow. A phone scan does not update a parcel. Failed sends retain their nonce in memory for retry. Paired codes are not persisted for offline replay.

The old browser phone-camera JavaScript and its unused CSS were removed. Opening a pairing QR in a browser now displays Android-app instructions. Workstation camera, USB/manual entry, existing URLs, desktop parcel actions, and compatible legacy relay endpoints remain; legacy web sends cannot inject codes into a claimed native pairing session.

## API contract

Base: `/api/v1/mobile`. JSON requests use `Accept: application/json` and `Content-Type: application/json`. Protected routes also require `Authorization: Bearer <token>`. Never include a token in a URL or log a request body containing credentials.

| Method and path | Request | Response |
|---|---|---|
| POST `/login` | `email`, `password` | `token`, `expires_at`, minimal `user` |
| GET `/me` | None | `user`, `offline_actions_enabled: false` |
| POST `/logout` | Empty object | `ok: true`; current token revoked |
| POST `/parcels/lookup` | `code` (1–100 characters) | `parcel`, `actions`, disabled offline-action flag |
| POST `/operations` | UUID `operation_id`, `code`, `action`, `expected_status` | `ok`, `operation_id`, authoritative operation `result` |
| POST `/scans` | UUID `operation_id`, `code`, ISO UTC `scanned_at`, `company`, present nullable `hub_id` | Durable scan-verification receipt; `accepted`, `parcel`, no executable cached actions |
| POST `/pair/{token}` | Empty object | `ok`, epoch-seconds `expires_at` |
| POST `/pair/{token}/codes` | `code`, 32-character lowercase hex `scan_id` | `sequence`, `duplicate` |

`user` exposes ID, display name, logistics role, company, and assigned hub ID. `parcel` exposes shipment ID, tracking/order numbers, status/label, origin/destination hub, relevant transfer-leg/progress metadata and update time. Buyer name/contact/address, identity documents, and credentials are omitted. Action IDs in this release are `receive` and `receive_transfer`.

Operations validate action availability and the current locked shipment status. New scan synchronization checks recorded company/hub context against the current assignment; a changed assignment requires review. A receipt is the result of that submission, not a current-status feed: use fresh lookup before confirming an action. The mobile app does not trust cached scan receipts as permission to act.

Errors use Laravel JSON `message` and validation `errors` when applicable: 401 invalid/expired/revoked/missing token; 403 unsupported role, suspended account, or unauthorized hub/pair; 404 no permitted parcel; 409 stale state, changed assignment or conflicting UUID/pair claim; 410 expired/revoked pairing; 422 invalid payload/transition; 429 rate/queue limit; 5xx transient failure. Login is limited to five requests/minute per middleware key; protected API requests to sixty/minute. Desktop relay limits remain separate. Authenticated permission errors must not be blindly retried as parcel operations.

## Offline queue and synchronization

Each SQLite row stores UUID, server namespace, owner ID, raw code, original company/hub context, UTC scan time, state, retry count, next retry time, last error and authoritative response. No buyer personal details are cached. Android backup is disabled for this app.

States are Pending Sync, Syncing, Accepted, Rejected, and Retry Required. Restart recovers interrupted Syncing records to Pending Sync using the same UUID. Sign-out preserves records; account/server switching selects only that namespace's owner queue. `/me` verifies API reachability and identity before synchronization. A running app checks periodically (twenty seconds) and offers manual retry; it does not infer reachability from Wi-Fi state. Up to twenty rows synchronize in chronological order per pass. Temporary errors use exponential backoff, with a cap and five automatic attempts per record; manual retry is available. Rejections stay visible and do not block independent scans. Auth failures pause synchronization and preserve records for reauthentication. Accepted verification does not mean a parcel was received.

No durable offline action queue or dependent action replay is enabled. Real MySQL concurrency verification, account-switching tests for queued actions, and conflict/dependency handling must pass before a later release enables that feature.

## Local installation and run

The additive migration `2026_10_14_000003_add_mobile_scanner_api.php` creates `personal_access_tokens` and `mobile_scanner_operations`, using signed user IDs compatible with the existing MySQL schema. It does not alter existing tables. It was applied locally as batch 98 without force/reset/reseed. Before/after fingerprints confirmed unchanged contents of **57 original business tables / 704 rows**, and both new tables were initially empty. Fingerprints are in `storage/app/private/mobile-scanner-before-20261014-085521.json`; these are comparison evidence, not a SQL backup. `down()` refuses destructive rollback of receipts. For another host, back up and rehearse the additive migration there before installation. MySQL DDL is not transactional; interruptions require explicit forward recovery.

Flutter and Android tools are installed on this computer. On another computer, install the stable Flutter SDK, Android Studio/SDK and compatible JDK; run `flutter doctor` and accept the required SDK licenses. Then run:

```powershell
composer install
# On a new local installation only, after backup/rehearsal:
php artisan migrate --path=database/migrations/2026_10_14_000003_add_mobile_scanner_api.php

php artisan serve --host=192.168.1.47 --port=8001
```

A local development listener was started at that Wi-Fi address. An unauthenticated `/api/v1/mobile/me` probe returned the expected 401. This verifies the local listener and JSON authorization response, not access from a physical phone. Do not expose the development server publicly.

In a second terminal:

```powershell
cd mobile/pocketfinds_scanner
flutter pub get
flutter run --dart-define=API_BASE_URL=http://192.168.1.47:8001/api/v1/mobile
flutter build apk --debug
```

The latest app no longer accepts an address at login; normal builds automatically use the verified HTTPS default. Developers can explicitly override `API_BASE_URL` in a debug build. For local testing on a physical phone on the same Wi-Fi, use the computer's current LAN IP; on the Android emulator, `http://10.0.2.2:8001/api/v1/mobile` is the host alias, but the Laravel server must listen on the corresponding host interface (use `--host=0.0.0.0` for emulator/LAN development). A phone's localhost is the phone. LAN IPs can change. Avoid guest Wi-Fi client isolation; allow the specific development port on the private network if Windows Firewall blocks it. No firewall rule was changed by this task.

The universal debug APK is `mobile/pocketfinds_scanner/build/app/outputs/flutter-apk/app-debug.apk`. Copy it to the Android phone and open it, allowing installation from that file source, or use `adb install -r <APK path>` with authorized USB debugging. This is a large debug artifact, not a signed production distribution. At the user's subsequent request, installation on the connected Infinix X695 succeeded via `adb install -r`; the app launch command succeeded and its installed package path was verified. This is installation/launch verification only, not camera, login, or pairing interaction testing.

Debug Android builds permit HTTP only for local development. Native camera access does not require browser HTTPS. Production API requests must use HTTPS; release-mode URL validation rejects HTTP and the release manifest does not grant cleartext access. Provision a server certificate trusted by the Android/Dart client, with a matching hostname, and use that HTTPS URL on both devices for pairing. For local HTTPS, use a local reverse proxy and development CA and explicitly configure debug-only client trust if necessary; do not disable certificate checks or carry a development CA trust override into release builds. HTTPS/certificate setup was not implemented or tested here. Release signing and store distribution remain separate work.

## Exact files changed

Backend created: `app/Http/Controllers/MobileScannerController.php`, `app/Http/Middleware/MobileScannerAccess.php`, `routes/api.php`, `database/migrations/2026_10_14_000003_add_mobile_scanner_api.php`, `tests/Feature/MobileScannerTest.php`, and this report.

Existing files modified: `composer.json`, `composer.lock`, `app/Models/User.php`, `bootstrap/app.php`, `app/Services/PhoneScanner.php`, `app/Http/Controllers/PhoneScannerController.php`, `resources/views/logistics/scan.blade.php`, `resources/views/logistics/phone-scanner.blade.php`, `public/js/parcel-scan.js`, `public/css/phone-scanner.css`, `tests/Feature/PhoneScannerTest.php`, `tests/JavaScript/phone-scanner.test.cjs`, and `docs/phone-parcel-scanning.md`. Removed: `public/js/phone-scanner.js`.

The new Flutter project contains:

```text
mobile/pocketfinds_scanner/
  .gitignore, .metadata, README.md, analysis_options.yaml, pubspec.yaml, pubspec.lock
  lib/api.dart, lib/camera_screen.dart, lib/main.dart, lib/scan_queue.dart
  test/camera_test.dart, test/scanner_test.dart
  android/.gitignore, android/build.gradle.kts, android/settings.gradle.kts
  android/gradle.properties, android/gradle/wrapper/gradle-wrapper.properties
  android/app/build.gradle.kts
  android/app/src/debug/AndroidManifest.xml
  android/app/src/profile/AndroidManifest.xml
  android/app/src/main/AndroidManifest.xml
  android/app/src/main/kotlin/com/pocketfinds/pocketfinds_scanner/MainActivity.kt
  android/app/src/main/res/drawable/launch_background.xml
  android/app/src/main/res/drawable-v21/launch_background.xml
  android/app/src/main/res/values/styles.xml
  android/app/src/main/res/values-night/styles.xml
  android/app/src/main/res/mipmap-{mdpi,hdpi,xhdpi,xxhdpi,xxxhdpi}/ic_launcher.png
```

Flutter-generated local SDK paths, Gradle wrapper launchers/cache, build artifacts, and IDE files remain ignored. Existing Laravel work was preserved; no `.env`, web route name, role definition, recruitment rule, or authentication/OAuth controller was changed. Composer lock changes add Sanctum only; installation reconciled pre-existing vendor/lock drift for other packages.

## Verification and remaining work

Tests use isolated SQLite fixtures and mocked camera/API input, not real parcel processing on a phone. No production deployment, real SMTP, or destructive database operation occurred.

| Command/check | Actual result |
|---|---|
| `php artisan test` | Final run: exit 0; **188 passed, 2,609 assertions**, 79,911 ms. |
| `php artisan test --filter='MobileScannerTest\|PhoneScannerTest\|LogisticsAuthorizationTest\|HubTransferWorkflowTest'` | Earlier combined run: exit 0; **66 passed, 1,401 assertions**. Final full run additionally includes the later transfer-receipt and cached-response visibility assertions. |
| `flutter test` | Final run: exit 0; **10 passed**, including real SQLite reopen/queue tests and mocked camera widget tests for both modes. |
| `flutter analyze` | Exit 0; **No issues found**. |
| Dart formatter and targeted Pint | Exit 0; affected files formatted. |
| `php vendor/bin/pint --dirty --test` | Final run: exit 0; passed. |
| `flutter build apk --debug` | Final build: exit 0; `app-debug.apk` produced (197,215,564 bytes). |
| `node tests/JavaScript/phone-scanner.test.cjs` | Final run: exit 0; **4 passed** for retained workstation/desktop behavior. |
| `node --check public/js/parcel-scan.js` | Exit 0. |
| `php artisan view:cache` | Exit 0; Blade compilation passed. |
| `php artisan route:list --path=api/v1/mobile` | Exit 0; eight API routes. |
| `php artisan migrate:status` | Exit 0; mobile migration Ran in batch 98. |
| Local migration/fingerprints | Exit 0; 57 original business tables / 704 rows unchanged; new tables initially empty. |
| Unauthenticated LAN API probe | HTTP 401 with JSON request headers; no account credentials used. |
| `git diff --check` | Exit 0; existing line-ending notices. |
| Final diff inspection | User model change adds Sanctum trait only; bootstrap adds API registration only; Composer lock adds Sanctum only; prior recruitment and web operations remain unchanged. |

The first dependency download was blocked by the sandbox proxy/cache permissions; the permitted retry succeeded. Flutter's generated counter test initially referenced the removed starter app and was replaced with meaningful scanner tests. A login test exceeded the real rate limit; its fixture now advances the test clock. Browser binding tests initially omitted credential cookies from JSON requests; they now carry the creating session cookie and separately verify that another session is denied. A transfer fixture initially omitted the corresponding order status and was corrected. Dart lint initially found nine missing braces; these were fixed and analysis passed. No failing check is reported as a success.

Remaining: physical-device camera/flashlight/permission checks; phone/computer network and pairing checks; trusted HTTPS; MySQL concurrent API/idempotency testing; durable offline operational actions; production signing/distribution; production load/retention design for receipts. The periodic sync runs while the app is running, not as an Android background job. Uncertain online actions and paired sends retain retry identifiers only in memory; after app termination, refresh authoritative state before taking another action. Only standalone barcode records are persisted. Recent scan UI displays at most 100 records; older SQLite records remain retained. Scope changes require review/reauthentication for old-context records.

Composer audit reports five advisories in four existing dependencies (`laravel/framework`, `league/commonmark`, `league/flysystem`, `phpseclib/phpseclib`); none names Sanctum. These were not repaired by unrelated dependency updates. Review them before deployment; this task does not certify production readiness.


## Two-way pairing disconnect ? October 14, 2026

The Android paired screen now has **Disconnect from computer**. Computer Disconnect and phone Disconnect both invalidate the same locked session and clear queued codes. The first disconnecting device is retained until the original ten-minute expiry, and repeated disconnects do not replace it. Both devices display who disconnected. Closed sessions cannot be claimed or used for more codes.

The phone checks status every five seconds while running; the desktop checks every two seconds, including while reviewing a collected parcel. Detection depends on connectivity and foreground execution; these intervals are not a guaranteed delivery deadline. Network errors retain the pairing and show retry guidance instead of claiming a remote disconnect. An already displayed desktop parcel remains available for the existing explicit review/action workflow. Choosing another mode from a paired phone now requires server-confirmed disconnect; logout also attempts disconnect before revoking the token. Automatic standalone barcode synchronization continues after disconnect without overwriting its notice.

New API routes: authenticated GET `/api/v1/mobile/pair/{token}/status` and POST `/api/v1/mobile/pair/{token}/disconnect`. They enforce current account/role/company/hub eligibility and the claimed mobile token. Desktop polling and disconnect retain the creating browser-session restriction, including for closed-session status. No schema, parcel workflow, password storage, or TLS changes.

Files changed for this follow-up:

- `app/Services/PhoneScanner.php`
- `app/Http/Controllers/PhoneScannerController.php`
- `app/Http/Controllers/MobileScannerController.php`
- `routes/api.php`
- `public/js/parcel-scan.js`
- `mobile/pocketfinds_scanner/lib/main.dart`
- `mobile/pocketfinds_scanner/lib/scanner_ui.dart`
- `tests/Feature/MobileScannerTest.php`
- `tests/Feature/PhoneScannerTest.php`
- `tests/JavaScript/phone-scanner.test.cjs`
- `mobile/pocketfinds_scanner/test/ui_test.dart`
- `docs/mobile-scanner.md`

Verification: `php artisan test --filter='MobileScannerTest|PhoneScannerTest|LogisticsAuthorizationTest'` passed **53 tests / 1,241 assertions**. `node tests/JavaScript/phone-scanner.test.cjs` passed **5 tests**, including remote disconnect during desktop review without another lookup or parcel write. `flutter test` passed **24 tests**, including explicit/disabled phone disconnect controls; `flutter analyze` reported no issues. Targeted Pint check, Blade compilation, JavaScript syntax and whitespace checks passed. Final debug APK build succeeded.

Earlier intermediate checks found a controller syntax error, an outdated expectation that closed-session polling returns 410, and a widget test that needed scrolling to build an offscreen control. These were corrected before the passing runs above. Backend tests use isolated SQLite/array-cache fixtures, and JavaScript/Flutter tests use simulated UI input. Physical two-device disconnect behavior and cache concurrency have not been verified. Removing a USB cable, closing the browser, or losing Wi-Fi is not an explicit Disconnect action; abandoned sessions still expire after ten minutes.

The rebuilt debug APK was installed successfully with `adb install -r` on the connected Infinix X695, preserving app data, and its activity was launched. Installation is not evidence of successful real-device pairing/disconnect.

## Scanner time display ? October 14, 2026

`mobile/pocketfinds_scanner/lib/scanner_ui.dart` now formats Last checked and recent scan times in local 12-hour time with AM/PM (for example, 2:30 PM), including 12:00 AM at midnight and 12:00 PM at noon. Stored UTC scan timestamps and API synchronization are unchanged.
Verification: Flutter tests passed (24 tests), Flutter analysis found no issues, the debug APK build succeeded, and the updated APK was installed on the connected phone. Whitespace check passed. No physical time-display inspection was performed.

## Login password visibility ? October 14, 2026

The login Password field now has an eye button with Show password / Hide password tooltips. Passwords start hidden; tapping toggles visibility without changing the entered value or login behavior. The toggle is local UI state; it introduces no password storage or logging. Changed `mobile/pocketfinds_scanner/lib/scanner_ui.dart` and this report. Flutter tests passed (24 tests).
Flutter analysis found no issues, the debug APK build and whitespace check passed, and the update was installed successfully on the connected Infinix phone. No physical password-entry verification was performed.

## Role-specific scanner header ? October 14, 2026

Below PocketFinds Scanner, the signed-in app header displays PocketFinds Logistics for the API logistics role `admin` and PocketFinds Hub for Hub Staff. The API continues to restrict the app to those existing authorized roles. This changes only `mobile/pocketfinds_scanner/lib/scanner_ui.dart` and this report; the company name and account identity remain visible.
Verification: 24 Flutter tests passed; Flutter analysis found no issues; debug build and whitespace check passed. The updated APK was installed successfully on the connected phone. Physical verification with both account roles was not performed.

## Duplicate heading cleanup ? October 14, 2026

Removed the company-name heading above Ready to scan, which repeated the role subtitle for the PocketFinds company. PocketFinds Logistics / PocketFinds Hub now appears only below PocketFinds Scanner. The existing user name and role remain visible. Changed `mobile/pocketfinds_scanner/lib/scanner_ui.dart` and this report.
Verification: 24 Flutter tests passed; analysis found no issues; APK build and whitespace check passed. The update was installed on the connected phone. No physical visual check was performed.

## User identity label cleanup ? October 14, 2026

The line below Ready to scan now displays only the signed-in user name. Removed the repeated Logistics Admin / Hub Staff suffix; the role-specific PocketFinds Logistics / PocketFinds Hub subtitle remains below PocketFinds Scanner. Changed `mobile/pocketfinds_scanner/lib/scanner_ui.dart` and this report.
Verification: final Flutter tests passed (24 tests), analysis found no issues, and APK build succeeded. The update was installed successfully on the connected phone; no physical visual check was performed.

## Company / municipality context ? October 14, 2026

The line below Ready to scan now shows the logistics company name for Logistics Admin and the assigned hub municipality for Hub Staff. The mobile identity response includes `hub_municipality`, loaded from the assigned hub within the same company. Existing cached sessions without this optional field fall back to company name until online session refresh. No new permission or address/contact data is exposed. Changed `app/Http/Controllers/MobileScannerController.php`, `mobile/pocketfinds_scanner/lib/scanner_ui.dart`, `tests/Feature/MobileScannerTest.php`, and this report.
Verification: MobileScannerTest passed 9 tests / 134 assertions; Flutter tests passed 24 tests; analysis found no issues; APK build succeeded and update installed on the connected phone. An intermediate Pint check required import formatting, which was applied. No physical account-specific display inspection was performed.
