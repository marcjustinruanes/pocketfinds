# Remaining logistics improvements — A11, A04, A12, A14, A10, A15 and A13

29 October 2026. Implemented in the requested order against the current source and `logistics-remaining-findings-test-review.md`. Existing six roles, one company Admin, private documents, physical Staff/rider attestation, operational recovery, onboarding and Finance Phases 1–7 remain the basis. No automatic deployment or configured-database migration was performed. Existing business records were not merged, deleted, backfilled or rewritten.

The owner explicitly approved two policy boundaries: rejected founders retain their company-name reservation and hubs with same-account reapplication; RTS initially supports only authenticated full sellable Seller receipt, with cross-hub reverse dispatch, partial/damaged restocking and return compensation deferred.

## A11 — Company ownership

- A generated, nullable unique founder key reserves the trimmed, lowercased company name for the founder account, including Pending, Approved and Rejected. MySQL uses the business-name column's collation. Rider/Staff rows can continue sharing their company's name.
- Registration serializes the company key alongside existing identity mutexes and rechecks ownership inside the transaction. Model-level founder persistence and the database unique index also protect approval and other application writers. Legacy NULL-role logistics founders are included conservatively.
- A founder cannot retarget an existing account to another company or remove its founder role through ordinary model updates. Different-owner transfer remains a reviewed operation requiring a separate design.
- Founder rejection preserves hubs and company-name ownership. Genuine rejected-account reapplication uses existing identity/attempt matching, the same user ID and preserved hubs. Preflight name availability explains the reservation; it does not authorize an applicant. TIN/SEC uniqueness excludes only the matched returning founder, with full identity checks still required.

**Existing-record check:** read-only inspection of the configured database found **zero duplicate normalized founder groups / zero affected owners**, including rejected and legacy NULL-role founders. The migration repeats its own preflight on each database and aborts on ambiguity. This result does not certify another presentation machine's records. No account or hub was changed by the check.

**Files:** `app/Services/CompanyOwnership.php` (new); `app/Models/User.php`; `app/Services/HubHiring.php`; `app/Http/Controllers/RegisterController.php`, `AdminController.php`; `public/js/register.js`; `resources/views/auth/register-logistics.blade.php`; migration `2026_10_29_000006_enforce_logistics_company_owner.php`.

**Verification:** model and raw database duplicate denial, personnel sharing, unchanged ambiguous migration candidates, rejection/hub preservation and real HTTP same-ID reapplication pass. Real concurrent MySQL founder requests that both passed initial validation produce one successful application and one clean rejection; only one founder can be approved. The approval route remains Platform Admin guarded. No live founder was created or approved.

**Limit:** no automatic ownership transfer, company rename, duplicate cleanup or account merge. Ambiguous existing ownership requires manual review before migration.

## A04 — Minimal safe RTS

- Generic Logistics Returned requests are rejected; that endpoint no longer restores stock or announces Seller receipt.
- Existing Issues records a return request and revisioned custody events. The original approved assigned rider records return transport. Actual approved destination Hub Staff records receipt/release; company Admin monitors and requests but cannot impersonate the receiver.
- For a local return, the rider can return directly to the Seller, or through the actual destination hub's receipt/release steps. Only the authenticated owning Seller can confirm the entire parcel physically received in sellable condition. That action alone changes the order/shipment to Returned and invokes the existing stock-release behavior in one locked, audited transaction.
- Requests use UUID retry keys, actor checks, revisions and source locks. Replays cannot restock twice. Append-only issue/audit history retains the actual actor, old/new custody state, reason/reference and recording time.
- COD collections are not fabricated. Recorded COD or earned delivery evidence requires exception review. Existing verified GCash/shipping evidence remains; the existing GCash release path keeps payment in manual resolution rather than inventing a refund. Returned orders do not become seller-payout eligible.
- Recovery holds and pending possession reviews still block progression. A completed Seller-received RTS closes that unsuccessful delivery for operational availability without earning successful-delivery compensation.

**Files:** `app/Services/LogisticsRts.php` (new); `app/Services/AssignmentReassignment.php`, `AssignmentWork.php`; `app/Models/LogisticsIssue.php`; `app/Http/Controllers/LogisticsController.php`, `LogisticsManagementController.php`; `routes/web.php`; `resources/views/logistics/partials/rts-custody.blade.php` (new), `issues.blade.php`, `monitor.blade.php`, `partials/issue-management.blade.php`; `resources/views/rider/show.blade.php`, `seller/orders.blade.php`; migration `2026_10_29_000007_add_issue_rts_custody.php`.

**Verification:** COD and real synthetic buyer-GCash application flows keep stock reserved after failure/generic Returned denial and release it only after genuine Seller confirmation. Tests cover wrong Seller, Admin physical receipt denial, Staff custody, suspended rider denial, cross-hub hold, duplicate receipt, audit rollback and unchanged collections/earnings. Concurrent MySQL Seller receipt records one restock and one receipt event. Seller HTTP rendering explicitly checks that its confirmation control exists.

**Limits:** cross-hub returns may be received and held at the destination hub but cannot be released through an invented reverse route. Partial/damaged parcels, uncertain custody, unavailable/suspended assigned riders and return compensation remain separate approved-design work. No new paid return assignment, automatic refund, deduction or return fee was introduced. Physical assertions are authenticated statements, not independent proof of possession.

## A12 — Operational audit and CSV

- Existing shipment mutations capture whitelisted old/new status and current-rider fields, with the available reason, in existing activity records. Existing issue decisions also record structured old/new assignment/status/outcome context and revisions. RTS has its own custody context in existing issue and application audit events.
- Failure/retry requests require a reason; monitor, Issues and scanner retry actions collect it. Existing specialized hiring, transfer and financial histories remain authoritative rather than being replaced.
- Mutating logistics requests recheck current actor approval, role and company before the transaction commits. Audit failure still rolls back operations. No credentials, full request bodies or private evidence paths are added to activity context.
- Operational CSV retains company/filter scoping and formula protection, adds explicit private/no-store and nosniff, and records exporter/company/filters/time/row count. Exports above 20,000 rows require narrower filters rather than silently truncating. Authorization/audit occur before the response is returned; failed audit prevents export.
- Activity UI distinguishes structured context from legacy entries that never recorded it. Older history is not reconstructed.

**Files:** `app/Http/Middleware/LogisticsActivity.php`; `app/Models/Shipment.php`; `app/Http/Controllers/LogisticsController.php`, `LogisticsManagementController.php`; `resources/views/logistics/activity.blade.php`, `monitor.blade.php`, `partials/issue-management.blade.php`; `public/js/parcel-scan.js`; migration `2026_10_29_000008_add_operational_audit_context.php`.

**Verification:** old/new statuses, real actor/time/reason, revoked-actor rollback, issue/audit rollback, export company exclusion/formula safety, no-store and export-access records pass. Injected export-audit failure prevents download. This is improved application audit, not a tamper-proof external archive or retroactive enrichment of every historical action.

## A14 — Actionable dashboard destinations

Awaiting Pickup Rider now opens the existing Pickup Requests workspace's approved/unassigned queue, with eligible origin-hub riders and the existing assignment action. The original review queue remains separate. Transit/delivery monitor and Sorted assignment links carry matching stage filters. Counts and company/hub scopes retain their existing current-state definitions; no second dashboard or date-based reinterpretation was introduced.

**Files:** `app/Http/Controllers/LogisticsController.php`; `resources/views/logistics/dashboard.blade.php`, `requests.blade.php`, `monitor.blade.php`.

**Verification:** state-matrix totals, foreign-company exclusion, approved-pickup queue inclusion, unapproved-review exclusion and matching stage navigation pass. Desktop/mobile previews have no document overflow. Additional dashboard period selectors/online dispatch remain outside scope. No migration is needed.

## A10 — Existing Issues investigation

- Added explicit Lost alongside Damaged and existing kinds. Existing Issues supports custody observations, optional private JPG/PNG/WebP/PDF evidence using existing upload limits/lifecycle, revision checks and clear resolution outcomes: located, damage documented, escalated or documented only.
- Updates append actor/time, prior/new status/assignee/outcome, reason and observation to existing issue events. Concurrent stale resolution fails instead of overwriting investigation history. A custody note is not a physical parcel handover.
- Existing protected evidence access permits company Admin and relevant assigned Hub Staff, retaining private/no-store, nosniff and sandbox protections. Riders, customers and unrelated staff do not gain access to internal proof.
- Only company Admin may explicitly confirm a resolved Lost/Damaged public milestone for generic buyer/Seller notification. Internal allegations, resolution detail and attachments are not copied to customer notifications. A replayed stale resolution does not notify twice.
- Issue resolution does not mutate parcel progress, inventory, recovery holds, collections, refunds, earnings or settlement balances. It does not decide financial liability.

**Files:** `app/Http/Controllers/LogisticsManagementController.php`, `SellerPayoutController.php`; `app/Models/LogisticsIssue.php`; `resources/views/logistics/partials/issue-management.blade.php`; migration `2026_10_29_000009_add_issue_investigation_context.php`.

**Verification:** private upload/delivery, scoped Staff access, Rider denial, invalid proof, audit/file rollback, revision conflicts, generic confirmed notifications and unchanged financial/parcel state pass. A real MySQL competing-resolution race produces one resolution event and one stale conflict.

**Limit:** recording an investigation outcome is not an approved refund, permanent financial-loss adjudication or permission to release recovered goods. Further claim/payment action must use an approved workflow. Photos are optional; no mandatory-photo policy was invented.

## A15 — Reconciliation indexing

`app/Services/LogisticsReconciliation.php` now pre-indexes/group-loads allocations, handovers, custody movements, treasury sources/accounts, evidence, payouts, obligations and source links. Repeated per-record full-collection scans were removed. The report still reads one consistent snapshot, preserves all-history balances versus activity dates, excludes mirrors and keeps funding buckets/reservations/transit distinct. No ledger, accounting policy or balance was added.

Fresh isolated MySQL measurements (PHP 8.5.9 / MySQL 8.0.45, one worker/sample per volume, 512 MB limit; bootstrap included, seeding excluded):

| Synthetic profile | Records | Time | Peak process memory | Queries | Exact totals / residual |
| --- | ---: | ---: | ---: | ---: | --- |
| Unremitted collections | 100 | 141 ms | 28 MiB | 37 | Pass / zero |
| Unremitted collections | 1,000 | 229 ms | 32 MiB | 37 | Pass / zero |
| Unremitted collections | 5,000 | 903 ms | 46 MiB | 37 | Pass / zero |
| Unremitted collections | 10,000 | 1,727 ms | 64 MiB | 37 | Pass / zero |
| Counted allocation/custody per collection | 100 | 84 ms | 30 MiB | 37 | Pass / zero |
| Counted allocation/custody per collection | 1,000 | 290 ms | 36 MiB | 37 | Pass / zero |
| Counted allocation/custody per collection | 3,000 | **748 ms** | 54 MiB | 37 | Pass / zero |

The prior same 3,000-counted profile took **42,496 ms**: about 57 times slower. Foreign-company canaries remain excluded. Financial HTTP tests also retain linked COD/GCash/voucher/payout and multi-account totals and consistent snapshot behavior. No migration is needed.

**Limits:** these performance fixtures deliberately omit order/shipping basis and generate unknown-basis review entries. They are not a fully populated settlement-history benchmark, provider-balance check or production concurrent-load SLA. Per-order eligibility work and all-history memory still warrant observation at larger fully linked volumes; no persisted summary or changed closing policy was added.

## A13 — Frontend feedback only

Managed personnel forms now give immediate accessible feedback for current Unicode/multi-word names, licence pattern and password/confirmation rules. Public rider steps validate the current licence pattern early. Public password feedback uses the same Unicode categories as the server and does not trim password input. Shared account-page JS preserves a stronger declared password minimum instead of lowering it.

**Files:** `public/js/logistics-personnel-address.js`, `register.js`, `role-account-details.js`; `resources/views/auth/register-rider.blade.php`. No backend name/password/licence rule was weakened or reimplemented. A11 founder-specific ownership/reapplication validation is separate.

**Verification:** current public/managed backend cases and all 20 executable rider-workbook cases pass. Browser checks accept `José María` and `李 明`, reject digits/markup and retain the managed minimum of 12. The 27 existing device/UI workbook skips remain unexecuted; no manual workbook pass or licence authenticity verification is claimed. No migration is needed.

## Final verification

| Final run | Result | Assertions |
| --- | --- | ---: |
| Finance/security/fulfillment/commerce/logistics/onboarding/registration/navigation regression | **387 passed; zero failures/errors** | 5,040 |
| New ownership/RTS/audit/issue/current-audit cases, explicitly filtered | **28 passed; zero failures/errors** | 367 |
| Rider workbook server equivalents | **20 passed, 27 skipped; zero failures/errors** | 265 |

New test classes reuse fixture parents, so some inherited cases overlap other runs. Do not add these totals or earlier exploratory runs together. The selected suites do not establish that every repository test passes. Earlier fixture errors and unsafe-shortcut expectations were corrected in isolated tests; production validation/security was not weakened.

- Fresh full `tests/Support/cash-desk-mysql.php --run` passed existing cash/custody, shipping/reference, treasury, seller payout/dispute, rider whole-trip earning/payroll/hold/acknowledgement, consistent report snapshot, recovery and hiring races. New schema is installed only in its random test database. Concurrent sessions use the same company Admin, not fabricated second operators.
- `tests/Support/remaining-logistics-audit-mysql.php --run race-only`, `--run rts-only`, `--run issue-only` and the synthetic volume run passed. Test databases clone schema only, use synthetic users/files/mail/cache, and are removed afterward. No source financial/customer rows are imported or modified.
- Nine synthetic UI views at **1440px and 390px** cover Admin/Staff/rider/Seller RTS, pickup queue, dashboard, activity, investigation and personnel feedback. All have no document overflow and the inspected fields have labels. New RTS/investigation/queue controls meet 44 px; existing personnel controls include 42 px controls, which were not redesigned. Active parent/navigation and Dashboard/My Hub ordering remain. Mobile Seller receipt and desktop pickup queue screenshots were visually inspected.
- Browser previews are synthetic HTML with local assets/geography. HTTP tests exercise real authorization/file responses. Physical devices, camera capture, comprehensive accessibility and real handovers/bank transfers are not certified.
- PHP syntax passed for 27 affected service/controller/model/migration/test-helper files; four changed JS files passed Node syntax checks. `git diff --check` passed, with existing line-ending notices only.

Private artifacts are under `storage/app/private/logistics-ui-review/`: `improvements-regression-final.xml`, `improvements-new-final.xml`, `improvements-workbook-final.xml`, matching output JSON, `improvements-mysql-cash-final.txt`, `remaining-mysql-race.json`, `improvements-mysql-rts.json`, `improvements-mysql-issue.json`, `improvements-mysql-performance.json`, preflight aggregates and browser results/screenshots. They are not product routes or identity assets to publish.

## Test/support changes

New: `tests/Feature/CompanyOwnershipTest.php`, `RtsWorkflowTest.php`, `OperationalAuditTest.php`, `IssueInvestigationTest.php`.

Updated: `tests/Feature/RemainingLogisticsAuditTest.php`, `LogisticsAuthorizationTest.php`, `FulfillmentWorkflowTest.php`, `GcashPurchaseTest.php`, `RiderCompensationTest.php`, `NavigationTest.php`, `RegistrationValidationTest.php`; `tests/Support/WorkflowSchema.php`, `cash-desk-mysql.php`, `remaining-logistics-audit-mysql.php`.

Fixtures retain the one-owner invariant and current application requirements. The deliberately unknown logistics-role test now corrupts only its isolated fixture directly, rather than expecting an ordinary founder role-transfer operation to succeed. Older generic Returned/restock tests now assert denial/no stock release; positive Seller receipt tests cover the replacement workflow.

## Migration and deployment boundary

Four additive migrations are required: **000006 ownership, 000007 RTS custody, 000008 operational audit context, 000009 investigation context**. They were exercised against isolated SQLite/MySQL schemas, **not applied to the configured business database**. No migration/deployment action was performed automatically. Their down methods reject destructive removal of controls/history; use reviewed forward changes.

Before operator deployment: review that environment's owner preflight, back up its database, deploy all required files and install the existing additive migration chain with `php artisan migrate` during a controlled window. Ownership migration stops on ambiguous founders; do not merge/delete accounts to force it through. Do not serve new write flows against incomplete schema. Each presentation machine needs its own migration and authorized private files; Git does not distribute them or repair the ten previously missing documents.

Still deferred: cross-hub reverse dispatch, partial/damaged Seller restocking, paid return transport, permanently unavailable-rider corrections, ownership transfer/rename and financial corrections/refunds. Existing missing-document investigations and external manual-transfer limitations remain. No new role, automatic penalty, payroll offset, financial backfill or emergency physical-attestation bypass was introduced.
