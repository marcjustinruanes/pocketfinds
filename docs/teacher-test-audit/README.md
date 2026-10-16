# Teacher test audit — incomplete

**PocketFinds is not certified ready against all 469 workbook cases.**

| Status | Cases |
| --- | ---: |
| PASS | 118 |
| FAIL | 0 |
| PARTIAL | 105 |
| UNVERIFIED | 246 |

PASS is reserved for a fully checked, narrowly defined workbook case. PARTIAL means related assertions or browser steps passed, but the full expected result has not been certified. UNVERIFIED means no complete execution has been recorded. Passing the regression suite is not the same as passing all teacher cases.

The original Excel workbooks were read without modification. Their hashes and complete extracted cells are in `workbook-extract.json`. Workbook notes were treated as test specifications and reference data, not as independent instructions.

## Environment and evidence

- Latest complete PHP regression run: 384 tests, 0 failures/errors. This is a separate count from the 469 teacher cases.
- Browser rerun: 11/16 scripts completed; 0 reported failure. This does not cover all workbook scenarios.
- The 19 existing JavaScript tests also passed. Earlier XML files record prior runs; `regression-final.xml` is the current complete run.
- The existing `pocketfinds` MySQL database is connected at `127.0.0.1:3307`.
- Fresh MySQL migrations and browser workflows ran in the separate database recorded in `mysql-audit-database.json`.
- The existing database was privately backed up before pending migrations were applied. The earlier backup was preserved.
- Existing users, products, orders and shipments matched their pre-migration counts and checksums; see `existing-records-before.json` and `existing-records-after.json`.
- PHP HTTP tests use isolated SQLite fixtures. The separate MySQL race test uses two actual concurrent PHP/Laravel request processes.
- Browser registration and rider activation use Chrome and the local mail log. The authorized external email test was accepted by SMTP, but the recipient reported it had not arrived; inbox delivery remains unverified. See `external-mail-test.json`.
- No external payment was charged or verified. The user retained local payment doubles; external provider success, failure, cancellation, timeout and refund verification remains out of scope until sandbox access is provided.

Latest browser runner: `browser-run-summary.json` records each script, execution time and exit status; corresponding `*-run.log` files preserve its output. A browser-run failure must be investigated even when some individual case assertions passed. Failure screenshots from earlier attempts are retained for diagnosis; use the latest JSON results and run summary to identify current outcomes.

## Implemented changes

- Added buyer/seller/logistics registration with general email addresses, normalized Philippine phone numbers, name/age/password validation, terms acceptance, conditional business documents, and post-registration five-minute email verification.
- Added company-created rider invitations with 24-hour, single-use activation links; disabled public rider signup; retained hub staff registration and legacy personnel management.
- Prevented inactive buyer/seller accounts and inactive Google accounts from accessing working portals; prevented admin approval before email verification.
- Enforced product price/stock precision and bounds, product name/description limits and image constraints, voucher code format, and stronger password changes.
- Made buyer name/photo edits immediate, with name validation and content-checked JPG/PNG photos up to 5 MB; the header now shows the saved photo.
- Added verified email/phone changes, five-minute single-use codes, case-insensitive cross-role email uniqueness, phone normalization, resend invalidation and attempt limits. Phone changes are confirmed through the registered email; no SMS delivery is claimed.
- Added saved-address editing, required address fields, both Philippine phone formats, a local PSGC location snapshot and region/province/city/barangay cascading. Retained the ten-address limit and default selection.
- Preserved order address snapshots after address editing/deletion and used snapshot recipient details in seller views and waybills.
- Added store categories with per-store case-insensitive names, two levels, ordering, visibility, up to three assignments per product, storefront filters, deletion confirmation and product preservation.
- Added seller vacation mode: products remain visible with a label, purchase buttons are disabled, and COD/GCash new-order paths reject vacation stores while existing fulfillment remains available.
- Added unified store settings with validated unique names, description, pickup address, contact details and 5 MB logo/banner uploads; buyer storefronts and store-name search reflect changes.
- Added private product drafts, immediate publishing for approved sellers, duplicate-submit protection, copies, bulk actions, unfinished-order deletion guards, bounded stock editing and threshold-crossing low-stock alerts.
- Added literal product search, typo suggestions, rating/location/price filters, stable pagination and completed-unit best-selling sorting; product prices retain cents.
- Added persistent buyer wishlists with explicit save/remove states, deleted-product snapshots, sold-out availability, a Following list and guest login links.
- Refreshed cart prices from current listings and disabled unavailable, sold-out, vacation and insufficient-stock cart lines while preserving checkout of available items.
- Reserved COD stock transactionally at checkout, prevented a second deduction at seller confirmation, restored stock on cancellation before confirmation, and ignored duplicated cart IDs.
- Changed automatic completion to seven days and measured it from the shipment delivery timestamp rather than unrelated later order updates.
- Sanitized GCash database-failure JSON responses so they do not contain exception details.

## Remaining work

Every unresolved case is individually listed in `case-results.csv` and `case-results.json`. Important remaining areas include remaining product-edit, bulk and inventory browser scenarios; matrix variants; scheduled discounts, flash sales and bundles; voucher wallets, stacking and funding; shipping-rate calculations; payment-provider lifecycle; return/refund/dispute workflows; per-item review media and editing; automatic dispatch and timed job offers; failed-delivery attempts and delivery OTP; COD remittance and earnings/payouts; and report exports. These have not been marked passed based on source inspection or related regression tests.

## Reproduce

```powershell
php -d memory_limit=512M vendor/bin/phpunit --no-progress --log-junit docs/teacher-test-audit/regression-final.xml
node --test tests/JavaScript/*.test.cjs
node tests/Browser/run-teacher-audit.mjs # requires the isolated MySQL audit server on port 8020
php tests/Browser/check_mysql.php
python docs/teacher-test-audit/build_report.py
```

Browser scripts require `playwright@1.64.1` (installed locally with `npm install --no-save --package-lock=false --ignore-scripts playwright@1.64.1`) and Chrome. Prepare a separate MySQL audit database with `php tests/Browser/prepare_mysql_audit.php`, run `tests/Browser/serve_mysql_audit.ps1` in another terminal, set `$env:AUDIT_URL="http://127.0.0.1:8020"`, then run the browser scripts under `tests/Browser`. They create fictional audit accounts and do not target the existing database. The separate audit databases are retained for inspection.

## Excel results and unresolved IDs

Updated review copies are in `excel-results/`; K:M contain actual result, status and evidence/remarks. PARTIAL and UNVERIFIED remain explicitly uncertified. The teacher originals are unchanged and hashes are recorded in `excel-results/manifest.json`. Regenerate copies after updating this ledger with `python docs/teacher-test-audit/export_excel_results.py`.

Every unresolved ID, recorded reason and required next work is listed in [unresolved-cases.md](unresolved-cases.md).
