# Buyer test-case implementation

User-approved exceptions: buyer minimum 16; manual GCash receipt/seller verification;
Flutter app and app-local API only. Website source must not be edited.
Website-guided approval and contact-step verification remain; cases assuming
instant activation or a payment gateway need corresponding expected results.

Implementation is present across the areas below. Items remain unchecked until
their complete workbook/manual coverage is verified; this is not a claim that
all 173 spreadsheet cases have passed. This continuation preserves current UI/UX.

- [ ] Registration validation, idempotency and interruption/navigation handling.
- [ ] Email codes, five-minute expiry, paste and elapsed-time countdown.
- [ ] Account security, verified contact changes, other-device revocation.
- [ ] Address limit/default/snapshot, location assistance.
- [ ] Search filters, sorting, typo suggestion and safe unavailable states.
- [ ] Product variants, gallery, purchase limits, flash information and links.
- [ ] Wishlist/follow availability and concurrency.
- [ ] Cart availability, selection, stock, persistence and retry consistency.
- [ ] Claimed voucher wallet, stacking, limits, caps and allocation.
- [ ] Authoritative checkout quote, shipping/serviceability and safe retries.
- [ ] Manual GCash continuation and reconciliation; no fake gateway/refunds.
- [ ] Tracking, receipt confirmation, timed completion and notification links.
- [ ] Cancellation cutoff, inventory/voucher restoration and refund requests.
- [ ] Return/refund requests, evidence, window and dispute escalation.
- [ ] Item reviews/photos/edit limits, chat retry and notification delivery.

External credentials/services, device-only checks, and groupmate website actions
must be explicitly recorded as unverified dependencies, never marked passed.

## Validation on November 4, 2026

PHP suites passed: buyer 36, registration 44, website buyer 29, integration 41,
rider 64 and buyer requirements 50: **264 assertions**. Fixtures were rolled
back or used a disposable audit database; mail was mocked. Flutter's **64 tests
passed**, including registration/back navigation, account retries, current cart
prices, variant availability and existing buyer/rider workflows. PHP syntax and
worker startup without scheduled actions passed. Final `flutter analyze` reports
no issues. `flutter build apk --debug` succeeded; APK:
`build/app/outputs/flutter-apk/app-debug.apk` (November 4 build).

Additional backend safeguards cover older review routes, 500-character/30-day
review rules, preference-aware notification deduplication, cancellation coin
restoration exactly once, GCash allocation release and remaining-payable coin caps.

## Setup and remaining dependencies

`php api/install-buyer.php` installs additive app tables. The requested query
artifact is `sql/mysql/2026_11_04_buyer_requirements.sql` and includes commerce DDL.
No website source adapter is installed.

Run `php api/buyer-worker.php --run`, or
`powershell -File scripts/start-buyer-worker.ps1`, for reservation expiry,
cancelled-allocation/coin reconciliation, due reminders, timed returns/completion
and configured push. The worker was not started automatically on live orders.

- Registration retains website administrator approval. Minimum buyer age is 16.
- Phone changes use verified email authorization; they do not establish SMS
  possession. Real email delivery is not established by mocked tests.
- Firebase client configuration and server `FIREBASE_SERVICE_ACCOUNT` are needed
  for real push. Never bundle the private service-account file into Flutter.
  In-app notifications remain available without Firebase.
- Android Google sign-in still needs Android OAuth package/certificate setup.
- Camera/gallery permissions, location matching, closed-app notifications,
  cross-device behavior and the full workbook matrix need device/manual checks.
- HTTPS link association requires the website owner's assetlinks configuration;
  app-local custom `pocketfinds://` links are supported.
- The older adjacent website does not read the new `mobile_commerce_*` tables.
  Groupmates must integrate offer management, advanced GCash verification and
  seller/admin return/refund decisions through the app APIs or a shared schema.
  Native orders/messages/history are reused, but identical seller/admin dashboards
  and commission reports are not claimed. Funding details are retained in
  `mobile_order_quotes.snapshot` for accounting integration.
- Manual GCash verification and actual repayment remain staff actions. No test
  performs a real charge/refund or sends real mail.

The complete expected-result inventory is `buyer-test-case-requirements.md`.
Use age 16, retained approval and manual-payment exceptions when running cases
that otherwise assume age 18, instant activation or a payment gateway.
