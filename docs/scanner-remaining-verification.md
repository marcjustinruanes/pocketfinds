# Hub Scanner — remaining verification

12 November 2026. Scope: outstanding scanner verification only. No application code, production configuration or production records were changed during this verification. No new release candidate was frozen.

| Requested item | Result | Actual evidence / remaining work |
|---|---|---|
| Android camera scanning | BLOCKED | `adb devices` listed no attached devices. No physical scan was performed. A dedicated staging endpoint and staging-targeted build are still required before connecting a phone. |
| Offline scan and network recovery | NOT TESTED | No physical phone or staging backend available. Previous Flutter SQLite and backend sync component checks passed, but they do not establish an end-to-end airplane-mode/recovery result. |
| Phone-to-computer pairing | NOT TESTED | Previous authenticated native API → real Chromium relay passed without parcel mutation; no real phone pairing or network interruption was performed in this verification. |
| Physical USB barcode scanner | NOT TESTED | No actual scanner input was demonstrated. Previous Chromium keyboard-style Enter simulation passed, which does not certify hardware. |
| Workstation camera scanning | NOT TESTED | The available device query returned no camera; no real camera permission, barcode or QR capture was exercised. Previous camera callbacks were simulated. |
| Complete rider-offer browser interaction | PASS for Hub Staff / NOT TESTED for Admin browser | Real Chromium, isolated loopback Laravel and synthetic SQLite fixture: Staff entered the parcel tracking number, pressed Find, selected the listed eligible rider and clicked Send Rider Offer. Lookup did not mutate the shipment. Database then contained exactly one `offered` assignment, null `accepted_at`, null shipment `courier_id`, and shipment remained `sorted`. Admin-specific full browser interaction was not executed; earlier HTTP/database probe passed for both roles. |
| MySQL concurrent parcel receiving and duplicate protection | NOT TESTED | MySQL client is present, but no disposable scanner-specific concurrency fixture was prepared or run in this verification. Previous SQLite tests established sequential idempotency, not MySQL race behavior. Do not treat this as a pass. |

## Executed browser check

The Staff test used synthetic approved accounts, synthetic barangay coverage and parcels in a disposable SQLite database. The temporary Laravel router overrode database, cache, mail, sessions and storage to private test locations; browser requests outside loopback were blocked. The PHP server was terminated after the run. The existing [rider-coverage fixture](../tests/Feature/RiderBarangayCoverageTest.php) was extended only in private temporary probes; application code and existing tests were not edited.

Commands:

```text
adb devices
python storage/app/private/scanner-role-verification/prepare_browser_fixture.py
python storage/app/private/scanner-role-verification/prepare_runner.py
python storage/app/private/scanner-role-verification/run_browser_fixture.py
python storage/app/private/scanner-role-verification/prepare_router.py
python storage/app/private/scanner-role-verification/run_offer_browser.py
```

The fixture probe passed 1 test / 26 assertions. The actual Chromium scenario passed. Evidence: [browser result and before/after database state](../storage/app/private/scanner-role-verification/offer-browser-result.json), [screenshot](../storage/app/private/scanner-role-verification/offer-browser.png), and [fixture test output](../storage/app/private/scanner-role-verification/ScannerOfferBrowserTest.txt). These are not physical scanner tests.

## Remaining issues before a new freeze

1. Dedicated staging server/domain and existing approved staging application key remain unresolved. Android's default API points to production; an explicit isolated staging build and verified endpoint are necessary before device testing.
2. Execute real Android camera/offline/pairing tests, physical USB and workstation-camera tests, and the Admin browser offer path using synthetic parcels and authorized separate accounts.
3. Run scanner-specific simultaneous receipt/duplicate requests against a verified disposable MySQL database, checking receipt/member/history/activity counts and custody after both requests. No conclusion about concurrent MySQL scanner safety is drawn here.
4. The municipality fallback text correction in `public/js/parcel-scan.js` is outside the existing frozen source snapshot. Preserve it and include it in a future candidate after the remaining checks. The prior JavaScript suite passed five tests after that correction; it was not rerun during this verification.

No new confirmed application defect was observed in the one newly executed browser scenario. Remaining checks are incomplete, so full scanner operational reliability is not yet established.
