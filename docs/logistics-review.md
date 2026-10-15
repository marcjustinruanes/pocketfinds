# Logistics review — October 13, 2026

This document records the initial review. Subsequent dependency, media, performance, and verification changes are recorded in [logistics-follow-up.md](logistics-follow-up.md); its results supersede the remaining findings below where addressed.

Reviewed the actual web routes, logistics and rider controllers, registration controller, shared messaging code, middleware, role helpers, shipment/hub/assignment models, migration, Blade layouts/forms, and automated tests against the implementation report. Authorization uses custom middleware and scoped queries; there is no `app/Policies` directory or separate API routes file. The `Policy` model holds terms and conditions, not Laravel authorization policies.

## Verified behavior

- Representative existing pages for all six roles passed local MySQL checks: Platform Admin, Buyer, Seller, Logistics Admin, Hub Staff, and Rider. This verifies page loading and database queries, not every checkout, upload, OAuth, or email workflow.
- Automated tests enumerate every logistics route with a declared HTTP method and verify rejection of Platform Admin, Buyer, Seller, and Rider accounts. All company-admin routes reject Hub Staff. Platform Admin retains its existing `/admin` pages; roles are not merged.
- Tests exercise modified foreign-company IDs on rider/staff decisions, accounts, hubs, fleet, issues, assignments, scan actions, parcel status changes, and messaging. Hub visibility distinguishes same-named municipalities in different provinces and rejects missing, nonexistent, foreign-company, closed, and suspended assignments.
- Rider creation through management, approval, activation, suspension, hub movement, blocked hub movement with active parcels, vehicle eligibility, delivery assignment, transfer assignment, and arrival are exercised. Public registration rejects closed hubs. Successful public registration with document uploads is not comprehensively tested.
- Failed operations and failed activity-log inserts roll back database changes. Approval email invocation is suppressed when the audited transaction fails. Tests use mocked/array mail and send no real emails.
- Fixture-based tests verify dashboard/report counts, history date/search/status/hub filtering, pagination, company-scoped CSV output, and formula escaping. Report/history date filters use parcel creation dates. Hub summaries count parcels touching each hub, so summing hub rows can count a multi-hub parcel more than once.
- The additive migration was applied and rolled back in a randomly named disposable MySQL database. All original column values were preserved across 46 tables and 532 copied rows. The disposable database was removed. The live schema was not reset or rolled back.

## Confirmed defects corrected

1. Unknown logistics subroles were treated as admins. Only `admin` and the documented legacy `null` value now qualify; invalid roles fail the backend gate.
2. Messaging accepted a reply ID from another conversation and exposed that message's preview. Sending now validates the conversation; old malformed references are hidden when messages are formatted. This shared fix also protects the Admin/Rider inboxes using the same trait.
3. Public rider/staff registration accepted a closed hub if it was still marked as hiring. Validation and the registration hub picker now require operational hubs.
4. Suspended or unassigned Hub Staff could not log out because their operational gate ran first. Logout remains available without granting operational access.
5. Management imposed an age minimum of 18 while existing registration uses 16. Management now preserves the existing registration rule, including editing eligible younger accounts. This is consistency with project behavior, not legal validation of that rule.
6. Transfer arrival completed the leg/parcel movement but left its assignment at `hub_transfer`. Arrival now completes the matching current assignment. Reassigning the next hop clears stale pickup/completion timestamps.
7. Transfer selection could choose a later approved leg before an earlier pending leg, or while another leg was in transit. Only the first pending, requested, approved leg can be assigned when no leg is active.
8. Rider/staff decision emails were invoked before the audit transaction committed. They are now deferred until commit, preventing approval emails for a rolled-back decision.
9. A history test silently failed to set its old `created_at` value through mass assignment. The fixture now sets the date explicitly, so date filtering is actually tested.

The original report also overstated transaction coverage and called three vulnerable packages three advisories. Those descriptions have been corrected. No new features or dependencies were added during this review.

## Actual command results

| Command | Result |
|---|---|
| `php artisan test` | Final run: 97 passed, 1,180 assertions; includes 30 logistics tests. Expanded tests first reproduced the defects before the fixes. |
| `php vendor/bin/pint --dirty` | Formatted changed PHP sources. |
| `php vendor/bin/pint --dirty --test` | Passed. |
| `git diff --check` | Passed. |
| `php artisan view:cache` | Passed. |
| `npm run build` | Passed outside the sandbox. The initial sandbox run failed with Windows subprocess `EPERM`; the permitted rerun succeeded. Optional `fontaine` warning remains. |
| `php database/verify_logistics.php` | 48 page checks, zero failures, all six roles; database/session writes rolled back. |
| `php database/verify_logistics_migration.php` | MySQL migration up/down preserved original values across 46 tables and 532 rows; disposable database removed. |
| `php artisan migrate:status` | All nine current migrations marked Ran. No live rollback/reset was performed. |
| `npm audit --json` | Exit 1: three affected packages, two critical and one high, representing two underlying advisories. |
| `npm audit --omit=dev --json` | Zero reported production advisories. |
| `npm audit fix --dry-run --ignore-scripts` | Proposed compatible-range upgrades listed below; did not install them. |
| `npm ls concurrently shell-quote source-map-js --all` | Confirmed installed versions still unchanged after the dry run. |
| `npm view ... dependencies engines --json`; `node --version` | Patched-version metadata checked; current Node is 24.19.0. |

## Dependency advisories and safe next step

| Installed package | npm severity | Proposed dry-run fix | Compatibility assessment |
|---|---|---|---|
| `concurrently@10.0.5` | Critical, propagated from shell-quote | `10.0.6` | Patch within project range `^10.0.3`; requires Node >=22, satisfied by current Node 24.19.0. Its dependency metadata pins shell-quote 1.12.0. |
| `shell-quote@1.9.0` | Critical | `1.12.0` through concurrently | [Command-injection advisory](https://github.com/advisories/GHSA-pqg4-j6r4-53mv); first patched version is 1.11.0. Fix rejects certain unsafe newline/comment combinations, so rerun developer commands after updating. |
| `source-map-js@1.2.1` | High | `1.2.2` | [Indexed source-map denial-of-service advisory](https://github.com/advisories/GHSA-68fv-2mgg-jv7q). Existing PostCSS/Tailwind dependency ranges allow this patch. |

These are development-tool dependencies, not vulnerabilities reported by the production-only audit. They still affect developer/CI tooling. No dependency files or installed versions were updated. Recommended follow-up: apply the proposed non-force upgrades in an isolated change, review the lockfile diff, then rerun npm audit, the build, the PHP suite, and the actual concurrently developer-server command. Dry-run resolution and engine compatibility do not prove complete runtime compatibility with versions that have not been installed.

## Remaining risks and coverage gaps

- Camera/USB scanning, responsive visual behavior, SMTP delivery, Google OAuth, and complete public document-upload registration were not exercised end to end. No GPS service is configured.
- Database rollback does not compensate filesystem writes/deletions. Profile or attachment operations can leave orphaned files or inconsistent media if a later database operation fails. This needs a separate tested media-lifecycle correction; the report no longer claims filesystem rollback.
- Operational lists often use unpaginated `get()` queries. Reports perform queries per rider and per hub; large datasets need performance/load testing and targeted query improvements.
- The migration is validated for this project's existing MySQL schema (including signed user/hub IDs and compatible collations). MySQL DDL is not transactional, interrupted partial migrations need recovery, and `down()` removes the newly created issue/activity history. Back up and rehearse on the intended host before production deployment; do not treat live rollback as data-preserving for new records.
- Existing application tests and 48 successful page responses do not establish that every Buyer/Seller/Admin mutation remains correct. Complete fulfillment, payment, return/restock, public registration, and mail/upload workflows merit further end-to-end coverage.
