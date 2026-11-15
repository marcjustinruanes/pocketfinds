# Buyer update — 13 November 2026

Compared the installed website revision `eded8bae` with GitHub main
`f4158032a1f568bd238e1390ddd93dd440f1c086`. The app now renders the updated
buyer Blade templates, CSS and JavaScript through its existing authenticated
WebView rather than approximating them in Flutter.

Affected areas include the storefront/header, product options and reviews,
shop tools, cart quotations and voucher selection, order filters, delivery
information, return/refund requests, review follow-ups, profile photos, account
security, saved addresses, voucher wallet, following shops, messages and
announcements. These use the website controllers and ownership checks.

## Preserved app behavior

- App-only Settings remain native, including preferences and dark mode.
- The shopping assistant remains hidden and its embedded endpoints return 404.
- GCash still opens the existing native QR/receipt flow configured in
  `api/config/gcash.php`. Website GCash purchase endpoints remain redirected.
- The new commerce checkout endpoint also uses the native payment handoff.
- Account Google email verification uses the app's existing native Google
  account picker and server-side Google token verifier. It does not attempt
  OAuth inside the embedded browser or replace the signed-in buyer account.
- Selected website vouchers reach native checkout; quotation and allocation
  use the pinned website's `BuyerCommerce` service. The admin verification
  service continues to guard fulfillment. Order snapshots preserve website
  delivery policy and discounts.
- Native legacy and rider flows retain the original runtime. Native checkout
  requests explicitly marked `website_checkout` use the updated runtime.

## Installation and local data

Run `powershell -File scripts/update-buyer-website.ps1`, then
`php scripts/install-buyer-website-schema.php`. The source installer pins a
reviewable revision and can clone the public repository if the reference is
missing. The generated runtime is ignored by Git; deploy it on the backend,
not as an Android asset. Re-export into a new empty directory when changing
revision to avoid retaining upstream deleted files.

The schema installer applies only reviewed buyer dependencies. It does not
import the repository's database dump, reset tables, remove payment accounts,
convert historic timestamps, or install unrelated logistics/commission changes.
It preserves existing wishlists in the new table with a one-time import.
Before applying these updates locally, a full database backup was saved under
the ignored `.tmp/before-buyer-update.sql` path. The existing connection's
timezone is retained so current tokens, payments and orders stay valid.

The backend still requires the existing Laravel checkout's `vendor`, `.env`
and storage. Start it with `scripts/start-local-api.ps1`.

## Validation

- 29 live HTTP checks cover 15 page/filter routes, assets, multipart photo
  upload, native GCash handoff, ownership, CSRF, assistant exclusion and session
  replay/revocation. Test buyers/uploads are removed afterward.
- 10 handoff security assertions pass with rolled-back fixtures.
- `api/tests/buyer-website-checkout.php` checks shared quotation, pending
  admin verification, stock reservation, duplicate checkout and fulfillment
  guards, plus Google email verification using a temporary test signing key.
  All temporary database changes roll back.
- All 81 Flutter tests pass, including the bridge and retained Settings widget
  tests; Dart analysis is clean. The Android debug APK builds successfully.

Page rendering and transaction tests do not establish real GCash transfers,
live Google account selection, mail delivery, staff approval, rider delivery,
or push delivery. Those still
require the configured services and staff workflow during acceptance testing.
