# Phone parcel scanning — October 14, 2026

This is the historical browser-scanner implementation report. The phone camera page was subsequently replaced by the Android app; the computer relay was retained and strengthened. See [mobile scanner implementation](mobile-scanner.md) for the current modes, setup, and verification.

Scan Parcel now supports both direct device-camera scanning and a paired phone camera that sends codes to a computer. Existing USB and manual entry remain available for Logistics Admin and authorized Hub Staff.

## How to use

- **Direct phone scanning:** Sign in on your phone, open Scan Parcel, and choose **Use this device’s camera**. Review the parcel and confirm any available operational action on the phone.
- **Phone to computer:** Open Scan Parcel on the computer and choose **Connect phone scanner**. Sign into the same account on your phone, then open the generated QR code/link and start the phone camera. Review each received parcel on the computer; choose **Next phone scan** before displaying another queued code. The phone also has an explicit next-parcel button.

Both devices need a reachable application address. A localhost URL refers to each device itself and cannot connect the phone to the computer. Camera access requires a secure context and camera permission; use a reachable HTTPS address. See [MDN getUserMedia](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia). This task does not provision HTTPS, change hosting, or expose the local application to the internet.

USB preference is preserved. Its camera preview starts hidden, but the explicit device-camera button can open it without changing the stored preference.

## Behavior and boundaries

Pairing uses a random 64-character token and a fixed ten-minute lifetime. Cache keys hash the token; each request checks the authenticated owner, company, hub assignment, and logistics role. The existing Logistics middleware continues to require approved personnel and an operational assigned hub for Hub Staff. Other roles cannot use these routes. Tokens do not replace account authentication. CSRF applies to mutations; creation, polling, sends, and acknowledgements are rate limited. Pairing responses prevent caching and referrer disclosure.

The queue holds at most twenty uncollected codes and refuses further sends rather than discarding them. Cache locks serialize queue operations. Each send has a retry identifier, retained through acknowledgement until expiry; retrying a successfully accepted send does not enqueue it twice. A connection accepts at most 1,000 distinct sends. Disconnect invalidates it and removes queued codes. Parcel codes are checked against the existing company/hub-scoped shipment query before entering the queue and checked again by the existing lookup when collected.

Phone pairing sends decoded codes only, without video or image uploads. Sending/collecting a code does not change shipment status. Receipt, assignment, transfer completion, and status changes still require operator confirmation through existing authorized endpoints. Transient relay requests omit the operational activity middleware; the existing parcel mutation routes retain their activity logs and business rules.

Client code suppresses repeated camera detections, rejects stale lookup responses, clears old actions after a failed lookup, and keeps mutations locked through their refreshed lookup. Untrusted messages and area notes render as text. Manual entry is available when the camera library, permission, or secure context is unavailable.

## Exact files changed in this task

Created:

- `app/Services/PhoneScanner.php`
- `app/Http/Controllers/PhoneScannerController.php`
- `resources/views/logistics/phone-scanner.blade.php`
- `public/css/phone-scanner.css`
- `public/js/parcel-scan.js` — extracted existing scan-page JavaScript with relay and reliability changes.
- `public/js/phone-scanner.js`
- `tests/Feature/PhoneScannerTest.php`
- `tests/JavaScript/phone-scanner.test.cjs`
- `docs/phone-parcel-scanning.md`

Modified:

- `resources/views/logistics/scan.blade.php`
- `routes/web.php` — six additive relay routes; existing scan routes retained.
- `tests/Feature/LogisticsAuthorizationTest.php` — valid token placeholders ensure new route constraints do not bypass the existing every-route authorization checks.

No migrations, dependency changes, `.env` edits, live personnel/parcel changes, deployment, reset, or reseed were performed. Earlier workspace changes remain intact.

## Actual verification

| Check | Result |
|---|---|
| `php artisan test` | Exit 0; 179 passed, 2,506 assertions. Run before the final explicit direct-camera UI choice and two additional rendering assertions. |
| `php artisan test --filter='PhoneScannerTest\|LogisticsAuthorizationTest\|HubTransferWorkflowTest'` | Final run after those changes: exit 0; 58 passed, 1,310 assertions. |
| `node tests/JavaScript/phone-scanner.test.cjs` | Final run: exit 0; six tests passed. VM/DOM simulations, not a real browser. |
| `node --check public/js/parcel-scan.js` and `node --check public/js/phone-scanner.js` | Exit 0. |
| `php artisan view:cache` | Final run: exit 0; Blade compilation passed. |
| `php artisan route:list --path=logistics/scan -v` | Exit 0; ten scan-related routes, including retained operational middleware on parcel mutations. |
| `php vendor/bin/pint --dirty --test` | Final run: exit 0; passed. |
| `git diff --check` | Exit 0; existing line-ending normalization notices. |
| Comparison with pre-task snapshots | Route and authorization-test changes reviewed; existing scan actions retained when extracting JavaScript. |

Backend tests use isolated SQLite fixtures and array cache. They cover owner/role/company/hub restrictions, changed assignment, suspension, closed hubs, expiry, disconnect, queue bounds, retry identifiers, acknowledgement, and unchanged operational records during relay. JavaScript tests cover direct camera selection/HTTPS checks, repeated detections, stale lookups, mutation locking, nonce retries, expiry, and one-at-a-time desktop collection without automatic status writes. No real email was sent.

Initial failures involved header normalization, an authenticated suspension fixture, constrained-token authorization-test URLs, and a cached test-model hub relationship. These were corrected before successful reruns. `node --test` could not spawn its test runner in this sandbox; running the test file directly with Node passed.

## Remaining verification and setup

- The configured cache must persist across requests and support atomic locks. Multiple application instances need a shared cache. Array cache is suitable only for these isolated tests. No live cache configuration was changed or MySQL cache contention test performed.
- Physical phone/computer pairing, camera permission handling, device barcode decoding, responsive appearance, and HTTPS/network reachability still need verification on the intended devices. No browser interaction or MySQL concurrency test was performed for this task.
- Camera and QR libraries use existing CDN versions; a blocked CDN requires manual entry/link use or a later asset-hosting change.
- A code acknowledged by the computer is collected, not proof that a parcel action was completed. Queue contents are temporary and expire; this relay does not provide a durable scan-history store.
