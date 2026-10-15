# Logistics hiring implementation — October 14, 2026

Implemented the approved Phase One hiring plan in the existing Laravel 13.25.0 / PHP 8.5.9 / MySQL 8.0.45 application. Six roles and their existing middleware remain intact. Platform Admin receives no hiring-settings permission. OTP and Google OAuth/TLS corrections remain explicitly outside this task.

## Behavior

Coverage Hubs (`/logistics/hubs`) now provides independent Rider and Hub Staff hiring switches, nullable approved-hire limits, and nullable closing timestamps. Forms show operational/intake status, approved count, limit, remaining vacancies, deadlines, and over-capacity conditions. Settings have server-side validation and revision checks; stale forms fail rather than overwrite newer values. Role settings are audited with actor, target hub record ID, role, before/after values, and timestamp. Optional fields can be cleared; omitted optional fields retain their existing values. Limits accept integers 0–100,000. Deadlines use and explicitly display the configured application timezone, **UTC**; no timezone configuration was changed.

Hiring status distinguishes Closed, Hub not operational, Deadline passed, Full, Open, and Scheduled to close. Opening the switch does not bypass a deadline or full capacity. A zero limit blocks intake/approval; null means unlimited. Lowering a limit preserves existing people and shows over-capacity. Coverage Hubs metrics count hubs accepting each role and hubs accepting either role. Search/status filters and 20-hub pagination preserve company scoping. The existing hub-details form remains available.

Approved-hire counts include only approved personnel assigned to the relevant hub and company with the correct role/flags. Pending, interview, rejected, and suspended accounts consume no approved slots. Remaining vacancies are `max(0, limit - approved count)`. Suspended accounts must pass capacity checks on reactivation.

Public registration performs preliminary eligibility validation before uploads, then checks fresh locked hub data, deadline, capacity, company, duplicate identity/account/document identifiers, and company-vehicle eligibility immediately before the account write. Staff address fallback includes operational status and its selected hub is checked again at the final write. Existing registration document and Rider vehicle validation remain in place. Registration pickers use effective role-specific availability through their existing `is_hiring` payload property, with readable Full/Deadline passed status badges. Hub address/contact details are not added to the public payload.

Approval independently checks capacity. Pending applicants may still be reviewed after intake closes or a deadline passes; they cannot exceed capacity and must have an operational company hub. Enforcement is centralized at the existing Eloquent `User::save()` boundary for Riders/Hub Staff, covering company decisions, Platform Admin's existing generic status actions, direct management creation, status edits, reactivation, and hub reassignment. Existing approved people can still edit profiles when a newly reduced limit puts their hub over capacity. No new global operational permissions were granted.

## Compatibility and application history

The existing stored `logistics_hubs.is_hiring` column and original values are preserved. Migration copies its value into both role switches. Newly created hubs use nullable role flags to inherit the legacy default until explicitly configured. After role settings are configured, the stored shared flag is not an authoritative representation of independent role availability; all active registration/hiring consumers use the new eligibility mechanism. There is no reliable single boolean representation of mixed role settings.

The existing toggle URL is retained, with an explicit transition to validated `role`, `is_hiring`, and `revision` inputs. Old implicit toggle requests fail validation rather than silently changing both roles. The hub page uses the new form contract. Opening/closing one role leaves the other unchanged.

Reapplication no longer deletes rejected users. A returning applicant must match the original email, username, identity, role/account type, and company. Ambiguous matches or attempts to repurpose another account are rejected. A matched rejected account keeps its ID, receives a new pending attempt, and retains old rejection/history records. Existing active duplicate rules and unique email/username/Google-ID constraints remain. The shared registration path also preserves matched rejected Buyer/Seller records instead of deleting them; a Buyer regression verifies account ID and rejection retention without introducing hiring restrictions on Buyer signup.

`hiring_application_history` is append-only: attempt UUID, user/company/hub/role, state, reason, actor, event time, recording time, and interview metadata. It stores no copied identity documents, credentials, or OTP codes. The migration records existing Rider/Staff states as explicitly marked legacy snapshots, using known last-update timestamps; it does not invent missing historical transitions or reviewers. State, assignment, reason, and interview changes append history. Company Logistics Admin can view their managed account's paginated history; foreign-company IDs are denied.

## Transactions and concurrency

Hub locks serialize capacity-changing account writes and settings changes. Multi-hub writes lock hubs in ID order; locked account reads detect changed status, hub, company, or role. Capacity is counted using locking reads so an earlier MySQL REPEATABLE READ snapshot cannot hide a newly committed approval. Account/history failures roll back together. Existing audited operations also roll back when activity insertion fails.

Registration uses 256 pre-existing hashed mutex buckets for short final account/identity/document operations, not uploads or external services. Mutex keys account for the actual MySQL name collation, including accent equivalence. Email/username constraints remain the final protection against conflicts; unique violations return validation errors. Rider license/plate duplicates are checked again under their mutexes. Upload processing continues through the existing media transaction/lifecycle; final failure removes new files and keeps existing account data.

No locks are deliberately acquired during uploads. The existing media middleware still opens the outer transaction before document processing; final locking reads were tested with an earlier snapshot already established. No real email/payment processing was used by tests. Existing decision email callbacks remain deferred until commit.

## Migration and local installation

New migration: `2026_10_14_000002_add_role_hiring_management.php`.

- Hub columns: `rider_hiring_open`, `staff_hiring_open`, `rider_hire_limit`, `staff_hire_limit`, `rider_hiring_closes_at`, `staff_hiring_closes_at`, `hiring_revision`.
- Audit column: nullable JSON `logistics_activity_logs.hiring_changes`.
- History table: signed user/hub/actor foreign keys compatible with the existing schema; user deletion restricted to protect history, hub/actor deletion sets their references null. Indexes on company, attempt UUID, and `(user_id, id)` support scoped history access.
- Mutex table: `hiring_identity_locks`, 256 nonsensitive numeric keys, primary key only.

Before local installation, the migration was rehearsed against **56 cloned local MySQL table definitions** with isolated fixtures and original-column comparisons. It preserved original fixture values and backfilled hiring/history. No live rows were copied into the disposable test database.

`database/apply_hub_hiring_local.php` then created this private SQL backup:

`storage/app/private/hiring-before-20261014-040833-e8e68c06.sql`

The migration was applied locally without `--force`; original business values were confirmed unchanged across **55 tables**, with **539 rows snapshotted** across the 56 original tables including the migration repository. It is now Ran in migration batch 97; all eleven current migrations are Ran. No live reset, truncation, rollback, or production deployment occurred. The helper refuses repeat installation; do not rerun it here.

MySQL DDL is not transactional. Rehearse installation and verify backups on the intended host. Interrupted installation needs explicit forward recovery. `down()` intentionally refuses to destroy application history; a rollback plan must preserve newly recorded history and personnel changes rather than blindly restoring an old snapshot. Restore backups into a separate database for reconciliation first.

## Actual checks and results

| Command/check | Result |
|---|---|
| `php artisan test --filter='HubHiringTest\|HubHiringMigrationTest'` | Latest targeted run: **16 passed, 140 assertions**, isolated SQLite. |
| `php artisan test` | Full run: **156 passed, 1,908 assertions**. |
| `php database/verify_hub_hiring.php` | Exit 0; **nine actual MySQL contention scenarios passed**. Final verifier cloned 58 post-installation table definitions, used isolated rows, observed a real MySQL lock wait in every scenario, and removed its disposable database. |
| MySQL scenarios | Final approval slot; close during registration; stale settings revision; limit reduction during approval; concurrent reapplication; two pending applications; collation-equivalent identities across hubs; duplicate license across hubs; duplicate plate across hubs. Each competing operation failed 422 except the intentionally allowed pair of pending submissions. |
| `php database/apply_hub_hiring_local.php` | Exit 0; backup, additive migration, original-value comparison passed. |
| `php database/verify_logistics.php` | Exit 0; **52 page checks, zero failures**, all six roles; session/database writes rolled back. Representative page coverage, not every mutation. |
| Targeted Pint formatting; `php vendor/bin/pint --dirty --test` | Passed. |
| `git diff --check` | Passed; existing CRLF/LF normalization notices remain. |
| `php artisan view:cache` | Passed. |
| `php artisan route:list --path=logistics/hubs` | Passed; four existing hub routes retained. |
| `php artisan migrate:status` | Passed; all eleven current migrations Ran. |
| `npm run build` | Initial sandbox run failed with Windows `EPERM`/native dependency loading errors; permitted outside-sandbox rerun passed. Existing optional fontaine warning remains. No dependencies changed. |
| PHP stdin rendered-page verification + `node --check` | Rider/staff registration and Coverage Hubs returned 200; five inline scripts passed syntax checks. Temporary files removed; database writes rolled back. This is not browser interaction testing. |

The first combined regression run failed because one workflow fixture omitted the new schema, one foreign-ID test reached validation before ownership lookup, and a malformed-hub fixture attempted an operation the new capacity boundary correctly rejects. These were corrected and the combined run passed. Malformed assignment fixtures now use explicit raw fixture writes; operational checks remain enforced.

## Exact files created or changed in this task

Created:

- `app/Services/HubHiring.php`
- `database/migrations/2026_10_14_000002_add_role_hiring_management.php`
- `database/apply_hub_hiring_local.php`
- `database/verify_hub_hiring.php`
- `tests/Feature/HubHiringTest.php`
- `tests/Feature/HubHiringMigrationTest.php`
- `docs/logistics-hiring-implementation.md`

Changed:

- `app/Models/User.php`
- `app/Models/LogisticsHub.php`
- `app/Http/Controllers/RegisterController.php`
- `app/Http/Controllers/LogisticsController.php`
- `app/Http/Controllers/LogisticsManagementController.php`
- `app/Http/Middleware/LogisticsActivity.php`
- `routes/web.php`
- `resources/views/logistics/hubs.blade.php`
- `resources/views/logistics/account-edit.blade.php`
- `resources/views/auth/register-rider.blade.php`
- `resources/views/auth/register-logistics-staff.blade.php`
- `tests/Support/WorkflowSchema.php`
- `tests/Feature/LogisticsAuthorizationTest.php`

Earlier workspace changes remain intact. AdminController itself was not modified; its existing Eloquent account updates go through the shared model boundary. No `.env`, dependency manifest/lockfile, OTP method, or SocialAuthController change was made by this task.

## Remaining limitations

### Coverage Hubs visibility follow-up

The subsequent UI cleanup consolidates the repeated hub table, hiring cards, and operational-details table into one hub card per record. Role settings use readable badges, approved/limit/vacancy summaries, full-width inputs and save buttons, and a responsive two-column layout. Operational settings are expandable; Add Hub remains visible at the top and has its own labeled form. Company identity, empty-state guidance, filters, and pagination remain visible. This UI-only cleanup changed `resources/views/logistics/hubs.blade.php`, `resources/views/logistics/partials/hub-management.blade.php`, and this document; no backend or database change was made. `HubHiringTest` passed 15 tests / 127 assertions; Blade compilation and whitespace checks passed. Rolled-back MySQL rendering checks passed for all three approved local Logistics Admin accounts (zero, zero, and 63 company hubs), including 40 role forms on the populated company's first page. Browser visual verification remains outstanding; no browser automation tool was available.

The hiring forms were moved out of the wide summary table into responsive Hiring Management cards. A visible **Manage Hiring** button at the top links to that section. An empty view now explains that the company needs a municipal hub or its search/filter cleared before hiring can be configured.

Read-only local inspection found zero hubs for approved admin companies `PocketFinds Logistics` and `testinglast`; all 63 existing hubs belonged to `logisticstest`. No company assignments or hub records were changed. Rolled-back local checks verified the entry point for a company without hubs and 40 role forms for the first 20 hubs of the company with hubs. The targeted `HubHiringTest` run passed 15 tests and 127 assertions; Blade compilation and whitespace checks passed. This follow-up changed only `resources/views/logistics/hubs.blade.php` and this report. No browser visual check was performed.

- The identified OTP proof gap, submitted Google identity binding, stateless OAuth flow, and disabled callback TLS verification remain unresolved by explicit scope. Pending status/manual approval remains enforced; do not interpret hiring tests as proof of secure email ownership.
- Public reapplications require matching original identity/credentials identifiers, role, and company. Changed email/username or ambiguous records need authorized manual reconciliation; records are not deleted to make them fit.
- Existing missing historical decisions cannot be reconstructed. Interview/rejection snapshots contain only information actually present.
- Raw SQL/bulk query-builder writes bypass Eloquent enforcement. Future personnel writers must use the model/service and its locking order; direct database maintenance requires its own reconciliation plan.
- MySQL tests verify the listed races, not every possible deadlock, failure, or concurrent legacy operation. Alternate target collations and production-sized datasets need rehearsal. Identity mutexes can serialize unrelated requests that share a bucket.
- Coverage Hubs pagination bounds displayed forms, but availability filtering still loads company hubs and uses aggregate counts before slicing. Capacity locking reads can inspect many personnel rows; production load/query-plan testing remains advisable.
- Reapplication uploads use existing storage behavior; prior files are not broadly purged. The existing public-document storage/retention concerns remain. No cleanup of unrelated or historical files occurred.
- Manual browser/responsive/date-input checks, SMTP delivery, target-host deployment rehearsal, and broader OAuth end-to-end checks remain outstanding. No production deployment was performed.
