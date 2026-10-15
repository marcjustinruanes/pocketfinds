# Logistics review follow-up — October 13, 2026

This follows the findings in `logistics-review.md`. The existing six roles, authentication, routes, report status meanings, and live records are retained. No schema migrations, dependency force upgrades, interface redesign, or live database resets were introduced.

## Dependencies

Applied `npm audit fix --ignore-scripts` after reconfirming the dry run. The final lockfile changes only the three intended package entries: concurrently 10.0.5 → 10.0.6, shell-quote 1.9.0 → 1.12.0, and source-map-js 1.2.1 → 1.2.2. Unrelated npm lockfile metadata changes were removed. `package.json` is unchanged. The installed tree confirms all three patched versions, and both full and production-only audits report zero vulnerabilities.

The actual `composer run dev` command started Laravel, the queue listener, and Vite through concurrently. Verification used Laravel port 8012 and the process-only `DB_QUEUE=logistics-review-verification-only`, avoiding consumption of existing jobs. Laravel `/up` and Vite `/@vite/client` returned HTTP 200. An earlier Vite probe timed out; the subsequent probes succeeded. Only the verification process tree was stopped, and its matching `public/hot` file was removed. The existing application server was preserved.

## Media consistency

`MediaConsistency` wraps upload-bearing web requests, hero-image clearing, and Admin account-update approval/rejection in a database transaction. The shared `MediaLifecycle` service registers new files before writing, removes them on failed writes/rollback, and defers replacement deletion until the outermost commit. It handles nested transactions used by logistics auditing. Exceptions, HTTP error responses, and validation-error redirects roll back.

Registration documents, account-document requests, profile/shop pictures, hero banners, message attachments, report evidence, and product uploads now use that service. Existing profile/hero files are preserved during failed replacements. Replaced pending document drafts and superseded approved user documents are deleted only after commit and only if no known database reference remains. Shared files, category avatar references, remote URLs, and unsafe paths are protected. An optional resume is processed only in registration roles that validate it; riders' unused generic ID uploads are ignored because the license supplies their ID.

Storage cleanup failure does not reverse a committed database change or mask the original failure. Warnings contain disk name, a path hash, and exception class; file paths, request bodies, and customer information are omitted. Cleanup failures can leave orphaned files for later reconciliation. No blanket storage cleanup or deletion of unrelated files was performed. Historical document request references are retained.

Admin account-status emails now defer to the outer transaction commit, matching the existing rider/staff decision behavior.

Failed checkout and seller-confirmation database writes previously exposed SQL exception text in flash messages. They now return a generic retry message and log only exception class; domain validation messages remain available. Failure tests verify database and stock rollback.

## Lists and reports

Added pagination with stable ordering and retained query parameters to pickup requests, delivery assignments, monitoring, rider applications, staff applications, company fleet, hub vehicles, and four individual queues on the hub dashboard. Hub dashboard queues use separate page parameters. Staff and fleet status tabs now filter on the server so results beyond the first page remain accessible; the existing `.tabs` CSS keeps their styling without client-side filtering. Summary badges count the full scoped result, not just the current page. Assignment views eagerly load hub legs.

Rider reports now join grouped delivery counts and fetch only the top ten riders. Hub reports aggregate origin, destination, and transfer endpoints using `COUNT(DISTINCT shipment_id)` per municipality/province. Filters, company isolation, date basis, and multi-hub counting semantics are preserved. Equal rider counts use user ID as a deterministic tie breaker. A regression test adds 25 riders and hubs and verifies that report query count does not increase.

Transfer request lists paginate ten complete route batches at a time, then load all legs of each selected batch. Batch actions keep their existing meaning; no batch is split across pages. Separate page parameters preserve other queue positions. SQL grouping preserves the former case-sensitive PHP grouping, including null/empty province handling. Full parcel and batch totals remain visible. A regression uses 21 batches with two parcels each, checks company isolation/page boundaries, and submits a complete selected batch through the existing request route.

Hub selectors and messaging histories remain unpaginated. Large single batches and report SQL execution costs still need representative load testing. No new indexes were added without evidence from representative query plans.

## Verification coverage

Added HTTP/database regression tests for public rider and hub staff registration with documents, registration rollback, checkout and failed order creation, foreign-address rejection, seller confirmation/preparation/logistics handoff, cancellation and return restocking, payment-account verification/ownership, and committed Admin/rider mail invocation. Existing logistics authorization and municipality/province isolation tests remain in the suite.

Media tests exercise failed and partial uploads, failed profile writes, replacement rollback/commit, nested rollback, pending document replacement, shared JSON references, failed attachment batches, private attachment access, Admin document-approval rollback, wrong-role uploads, and cleanup failures. SQLite workflow fixtures are isolated and contain no live records; they are not a substitute for MySQL migration or concurrency verification. Tests use fake storage/mail and prevent external HTTP requests. No real emails or payments were processed.

## Remaining limits and manual checks

- Process termination or host failure between filesystem and database operations can still leave files behind. Failed cleanup is logged but has no durable automatic retry queue; reconcile carefully against database references before removal.
- Existing product-media removal retains old files, including images that may still be used by historical orders/messages. No general historical-media purge was added.
- Public registration documents still use the project's existing `public` disk. This change does not migrate identity documents to private storage; review access and retention before deployment.
- Registration tests also exposed an existing email-verification gap: direct `/register` submissions create pending accounts without an OTP verification step. `verifyOtp()` checks/forgets the code but creates no proof consumed by `store()`. Pending status and manual approval remain enforced. Enforcing a new registration gate requires an authentication-focused fix with manual/Google registration coverage; it was not introduced as an untested change to the existing authentication flow.
- Browser/responsive behavior, SMTP delivery, OAuth, physical camera/USB scanning, and production-scale performance still require manual checks. Automated scan tests do not establish hardware operation.
- Payment-account verification and cash-on-delivery order creation were tested; no real payment gateway settlement was attempted.
- New workflows use SQLite fixtures. Local MySQL verification checks representative pages for six roles, not every mutation or concurrent race on MySQL.

## Actual commands and results

| Command | Actual result |
|---|---|
| `npm audit fix --dry-run --ignore-scripts` | Reconfirmed resolution of only the three intended packages before installation. |
| `npm audit fix --ignore-scripts` | Exit 0; changed three installed packages. No force flag. |
| `npm ls concurrently shell-quote source-map-js --all` | Exit 0; installed concurrently 10.0.6, shell-quote 1.12.0, source-map-js 1.2.2. |
| `node storage/app/private/verify-lock.cjs`; `git diff -- package-lock.json` | Verified exact package-entry changes; final lockfile diff is 10 added and 10 removed lines across the three entries. Unrelated npm metadata changes removed. |
| `npm audit --json` | Exit 0; zero vulnerabilities. |
| `npm audit --omit=dev --json` | Exit 0; zero vulnerabilities. |
| `npm run build` | Exit 0 outside the sandbox; Vite 8.2.1 build succeeded. Optional fontaine warning remains. |
| `composer run dev` | Laravel, isolated queue worker, and Vite started successfully. Verification HTTP requests returned 200; the owned process tree was stopped deliberately afterward. This is a startup/HTTP check, not a claim of a natural successful exit from a persistent server. |
| `curl.exe --noproxy '*' ... http://127.0.0.1:8012/up`; Vite `/@vite/client` probes | HTTP 200 for Laravel and Vite. One earlier Vite attempt timed out; later localhost and explicit IPv6 probes passed. |
| `php artisan test --filter=MediaLifecycleTest` | Latest targeted run: 12 passed, 76 assertions. |
| `php artisan test --filter=FulfillmentWorkflowTest` | Latest targeted run: 8 passed, 97 assertions. |
| `php artisan test --filter='test_operational_lists\|test_report_query_count'` | Initial run: one passed, one failed because the test used `/assign` instead of the existing `/assignments` route. Corrected the test URL. |
| `php artisan test --filter='MediaLifecycleTest\|test_operational_lists\|test_report_query_count'` | Subsequent combined run: 12 passed, 95 assertions (before the last two media tests were added). |
| `php artisan test --filter=test_transfer_pagination` | One passed, 24 assertions; verifies complete batches and the existing batch action. |
| `php artisan test --filter=test_operational_lists` | Final targeted view check after restoring existing tab styling: one passed, 20 assertions. |
| `php artisan test` | Final full run: exit 0; **121 passed, 1,411 assertions**, 135,205 ms. Earlier completed full runs also passed at 117, 119, and 120 tests. |
| `php vendor/bin/pint --dirty`; targeted Pint runs | Formatted affected PHP files. Formatting-only changes to unaffected controllers were removed. |
| `php vendor/bin/pint --dirty --test` | Exit 0; passed after final PHP changes. |
| `php artisan view:cache` | Exit 0; Blade templates compiled after pagination changes. |
| `git diff --check` | Exit 0; passed. Git also reports CRLF/LF normalization notices, not whitespace defects. |
| `php database/verify_logistics.php` | Exit 0; 48 page checks, zero failures, all six roles; database/session writes rolled back. |
| PowerShell PHP stdin MySQL `JSON_SEARCH` probes | Three passed, including nested JSON and escaped wildcard characters; no database/file mutations. |

Initial upload test fixtures failed because GD is unavailable in this PHP installation. Tests now use a valid embedded PNG through `createWithContent`, requiring no GD installation. Initial workflow expectations were corrected to the application's existing redirect/error behavior and actual document count. No failed check is presented as a pass before its successful rerun.

The migration verification script was not rerun in this follow-up: no migration or index changed. Its earlier results remain in the initial review. The temporary lockfile comparison helper/snapshots were removed after verification; development startup logs remain under ignored `storage/app/private/logistics-dev.out` and `.err`.

## Changed files in this follow-up

- `package-lock.json`.
- `app/Services/MediaLifecycle.php`, `app/Http/Middleware/MediaConsistency.php`, `app/Providers/AppServiceProvider.php`, and `bootstrap/app.php`.
- Admin, Buyer, Seller, Register, Logistics, and Rider controllers; `Concerns/HandlesMessaging.php`, `Concerns/HandlesAccountUpdateRequests.php`; `app/Models/AccountUpdateRequest.php`.
- Logistics `requests`, `assign`, `monitor`, `riders`, `staff`, `fleet`, `hub-dashboard`, `hub-requests`, and `hub-vehicles` Blade views.
- `tests/Feature/MediaLifecycleTest.php`, `tests/Feature/FulfillmentWorkflowTest.php`, additions to `LogisticsAuthorizationTest.php`, and `tests/Support/WorkflowSchema.php`.
- This document and annotations in the implementation/review reports. Earlier logistics changes already present in the workspace were preserved.
