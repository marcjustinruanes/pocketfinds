# Remaining logistics findings — testing review

29 October 2026. Scope: A04 and A10–A15. **Testing only: no production fix, feature, migration, role, financial policy or business-data change.** This report supersedes the corresponding unresolved assumptions in the original audit where current evidence differs.

Reviewed `logistics-module-audit.md`, the Phase 1–7 reports, critical security package, operational recovery package and latest onboarding/decisions report against current source. Current code, rather than an older report or workbook expectation, determines these results.

## Priority and result

| Finding | Current result | Severity / priority | Implementation necessary? |
| --- | --- | --- | --- |
| A04 RTS | **Fail:** Returned restores inventory before seller receipt evidence | High / critical custody and inventory work | Yes; approve physical RTS receipt/restock design first |
| A10 Lost/damaged | **Pass** for scoped issue recording/history; **Fail** for a complete lost/damaged custody/escalation workflow | Medium / important | Basic escalation/evidence handling needed; automatic refunds are not assumed |
| A11 Company ownership | **Fail:** overlapping founder registrations create two company Admin applications; both can be approved | High / critical authorization boundary | Yes; approve canonical single-owner enforcement and legacy review |
| A12 Operational audit | **Pass** for actor/time and transactional rollback; **Fail** for complete transition context and export audit/no-store | Medium / important | Yes, using existing audit/export patterns |
| A13 Registration | **Pass** for current server name, password and licence rules; minor client guidance gaps | Low / optional UI consistency | No repeat implementation of the fixed backend rules |
| A14 Dashboard | **Pass** for tested current-state counts, scope and layout; **Fail** for approved-pickup destination | Medium / important navigation correction | Fix that link/queue mapping; additional filters are optional |
| A15 Reconciliation | **Pass** for measured totals and company exclusion; **Fail** performance readiness at dense counted-cash volume | Medium / important before larger deployment | Optimize and retest; retain accounting and snapshot semantics |

“Fail” describes the product acceptance criterion, not a failing PHPUnit assertion. New reproduction tests deliberately assert the observed unsafe behavior so evidence is repeatable. They are not approval to retain it.

## Executed evidence and boundaries

| Fresh run | Tests | Passed | Skipped | Assertions |
| --- | ---: | ---: | ---: | ---: |
| Onboarding, hiring, logistics authorization, recovery, reconciliation | 106 | 106 | 0 | 2,036 |
| COD, Cash Desk, shipping, seller payouts, rider compensation, transfers, private documents, fulfillment | 137 | 137 | 0 | 1,039 |
| Current rider workbook server equivalents | 47 | 20 | 27 | 265 |
| New remaining-logistics reproduction tests, filtered to `test_audit` | 8 | 8 | 0 | 90 |
| New current-registration tests, filtered to `test_audit` | 2 | 2 | 0 | 63 |
| **Total of these distinct executed cases** | **300** | **273** | **27** | **3,493** |

No final failure/error remains in these selected PHPUnit runs. Initial audit-harness errors involved missing synthetic tracking numbers, redirect expectations and a missing test import; those were corrected in tests only. The original manual workbook and 27 device/UI cases are not certified. This is not a full-repository-green claim.

SQLite fixtures are isolated in memory. MySQL 8.0.45 tests clone schema only into a randomly named `pocketfinds_audit_test_*` database, use isolated storage/mail/cache and remove their database/files. They do not import source users or financial rows. Fresh MySQL execution here covers founder concurrency and reconciliation volume; prior packages' money-moving MySQL races are prior evidence, not claimed as rerun here. Selected financial/security HTTP regression tests passed freshly.

Synthetic dashboard previews were rendered at 1440px and 390px and inspected in Chromium. Both have no document overflow, Dashboard active/first and no dashboard form-field label failures. This is not physical-device, camera, keyboard or comprehensive accessibility acceptance.

An audit baseline hashed **541 production-source files** under app/routes/resources/public CSS/JS/database/config; the comparison found **zero changed files**. New tracked review outputs are the three test/helper files and this report. Private JSON/XML/HTML/screenshots remain under `storage/app/private/logistics-ui-review/`; do not publish them as application routes.

## A04 — Return to sender

**Current implementation:** `LogisticsController::STATUS_TRANSITIONS/updateStatus` permits `delivery_failed -> returned`. It changes shipment and order status, invokes `Order::restoreStock`, writes history and tells the seller that the parcel was returned and stock restored. There is no seller receipt attestation or reverse-custody prerequisite in that transition. A01's Staff-only hub receipt and A02's rider-only Delivered protection remain separate and intact.

| Scenario | Expected safe result | Actual executed result | Status |
| --- | --- | --- | --- |
| Uncollected COD order, failed delivery, then Admin Returned with no seller action | Return progress must not establish seller custody or sellable inventory | Stock **4 → 5** immediately; order Returned; no seller action required | **Fail** |
| Order and operational history | Real actor/time recorded; required custody/reason evidence distinguishable | Actor and timestamps present. Failure note recorded; Returned accepts no reason and its note is null. No physical seller receipt is recorded | **Pass** for attribution; **Fail** for custody evidence |
| Replay Returned | No second restock/history transition | Redirect with validation error; stock stays 5; order-history count unchanged | **Pass** |
| COD obligation and collection | Do not invent collection, settlement or cancellation of shipping liability | Failed order has zero COD collections; shipping obligation remains `unsettled`; existing unrelated collection unchanged | **Pass** |
| Seller eligibility after RTS | Returned order must not qualify as a delivered COD seller payout | Eligibility false | **Pass** |
| Real synthetic buyer GCash verification, handoff, shipping verification, pickup/hub receipt, release, failure, RTS | Preserve payment evidence; no fabricated refund; stock waits for seller receipt | Stock returns to original 5; purchase becomes `manual_resolution` / reservation `released`; shipping receipt stays verified; no refund inferred | **Fail** inventory; **Pass** payment exception boundary |

The GCash test uses actual application services for payment/stock allocation and normal handoffs, replacing the final successful delivery with failure/RTS. It does not simply rename a COD fixture to GCash. The COD test starts a separate undelivered synthetic order; it does not erase an existing delivered collection to reach the transition.

**Exact unsafe operation:** `LogisticsController::updateStatus` calls `$order->restoreStock()` solely because requested status is Returned. `Order::restoreStock` increments normal listing stock or delegates prepaid allocation release to `GcashWorkflow::releaseOrder`. `releaseOrder` correctly preserves unresolved payment as manual resolution; that does not establish physical seller receipt.

**Affected files/functions:** `app/Http/Controllers/LogisticsController.php::updateStatus`, transition map and seller notification; `app/Models/Order.php::restoreStock`; `app/Services/GcashWorkflow.php::releaseOrder`; `resources/views/logistics/monitor.blade.php`; existing seller return receipt in `CommerceController::returnAction` as a design reference, not an interchangeable workflow.

**Minimal recommendation:** separate return authorization/transit from actual seller receipt. Keep current stock/payment evidence unchanged until an authenticated seller receipt with idempotent item-level restock provenance. Define refused/partial/damaged return evidence and prepaid exception handling before implementation. Do not add automatic full refunds, RTS fees, wage deductions or Admin physical attestation.

## A10 — Lost/damaged parcels

**Current implementation:** Issues support `delivery_failed`, `damaged`, `transfer_problem`, `other`; company and route-hub scoping; authorized assignment; Open/In progress/Resolved; required resolution text; and appended issue events. Generic Issues are bookkeeping, not parcel custody transitions. A05 additionally supplies private proof and immutable recovery metadata for suspended-rider recovery; it is not a generic loss-claim upload feature.

| Scenario | Expected result | Actual result | Status |
| --- | --- | --- | --- |
| Create damaged issue on company parcel and route hub | Scoped issue and actor/time event | Created Open with actual Admin actor; no parcel-status mutation | **Pass** |
| Foreign-company or unrelated-hub access | Denied; records concealed | Foreign mutation 404 in new test; existing fresh authorization tests cover other-hub visibility/mutation and route/assignee restrictions | **Pass** |
| Resolve without text; then progress and resolve with text | Reject empty resolution; retain event history | Empty resolution 422; three events retain Open, In progress and Resolved with actor/time/notes | **Pass** |
| Create literal `lost` kind | Explicit lost classification/escalation if operating a loss workflow | 422; `other` can describe loss but has no dedicated controlled outcome | **Fail** for complete loss workflow; generic notes still exist |
| Attach proof to generic damaged issue | Evidence retained if advertised/supported | Generic action has no evidence field/storage handling; submitted extra proof is not persisted as generic issue evidence | **Not Tested** for a supported upload contract; absence confirmed by inspection and request behavior |
| Customer notification on issue creation/resolution | Deliberate applicant/customer-safe escalation policy | Notification count delta **0**; generic issue actions do not notify buyer/seller | **Fail** for complete escalation, not evidence that every internal note should be emailed |
| Automatic refund, wage charge or shipment Lost transition | Only if separately approved | None produced; issue resolution alone does not settle a claim, refund, cash balance or recovery hold | **Pass** approved financial boundary |

**Severity:** Medium. The issue tracker works and is authorized; it does not provide the complete custody/investigation outcome the original audit described. A mandatory-photo rule and automatic refund are workbook/design assumptions, not established requirements.

**Affected files/functions:** `LogisticsManagementController::issueScope/issueAssignees/storeIssue/updateIssue/issueEvent`; `app/Models/LogisticsIssue.php`; `resources/views/logistics/issues.blade.php`; existing `LogisticsRecovery` and private finance-evidence lifecycle for reuse where appropriate.

**Minimal recommendation:** extend existing Issues with deliberate lost/damaged escalation, custody observations, evidence references/private attachments and clear operational resolution outcomes. Agree which milestones notify customers and how an incident reaches existing buyer complaint/return/payment review. Do not infer that an internal damaged issue automatically authorizes a refund or seller-payout hold. Reuse evidence protection, not recovery-only custody attestations.

## A11 — One company owner

**Current implementation:** Company identity is `users.business_name`, shared with personnel/hubs; no canonical owner table/unique company-owner key was found. `RegisterController::store` checks company-name uniqueness during validation. `HubHiring::lockApplicationIdentity/register` serializes personal identifiers but does not reserve/recheck company ownership. The hub unique index protects company/province/municipality, not one founder per company.

| Scenario | Expected result | Actual result | Status |
| --- | --- | --- | --- |
| Two distinct founders, exact same company name, both validate before either ownership write; different legitimate coverage hubs | One founder application owns/reserves the normalized company identity; other fails cleanly | Both registration responses `success:true`; **two Pending company Admin applications** | **Fail** |
| Existing approval function applied to those synthetic founders | Second owner cannot become Approved | `AdminController::approveUser` accepts both; **two Approved company Admin rows** | **Fail** |
| Same hub in the first overlap probe | Must enforce owner policy, not accidentally rely on hub collision | One attempt deadlocked, or later hit hub uniqueness; this did not prove safe ownership | **Not a passing ownership control** |

The decisive test starts separate processes and calls the real registration controller with valid distinct identity/TIN/SEC/mobile/upload data. A test-only barrier pauses at the existing hiring-service boundary **after validation**. Both requests overlap; the second resumes persistence after the first commits. No production validator is disabled. Distinct hubs avoid an unrelated composite hub-key collision. The name is identical, so these are also identical normalized names; no spelling/case-normalization assumption is needed to reproduce it.

Approval was tested at the existing controller/service boundary with fake mail, not by claiming an unauthenticated HTTP approval exploit. The production Platform Admin route remains guarded. This finding is missing ownership enforcement even after legitimate review, **not** public self-approval.

**Severity:** High. If both applications are approved, company-name scoping treats them as the same company, undermining the approved single-operator boundary. No live duplicate account was created.

**Affected files/functions:** `RegisterController::store/checkBusinessName`; `HubHiring::lockApplicationIdentity/register`; `LogisticsCompany::nameTaken/approved`; `AdminController::approveUser`; users/company-related schema and company-name scope consumers.

**Minimal recommendation:** approve a canonical normalized company ownership/reservation invariant, enforce it transactionally with database uniqueness or a suitably constrained existing ownership source, and recheck on approval. Serialize the company key alongside identity keys. Preserve employee sharing of the name. Review existing duplicate/ambiguous ownership before any migration; do not delete/merge accounts or invent a second Admin role. A preflight-only validation change is insufficient.

## A12 — Operational audit and export

**Current implementation:** `LogisticsActivity::handle` wraps mutating logistics requests and inserts actor/company/hub/route/record/time. Order status history records new status, actor/time and optional reason; issues, hiring, transfers and Finance have richer specialized histories. Safe GET requests, including operational CSV, skip this middleware audit.

| Scenario | Expected result | Actual result | Status |
| --- | --- | --- | --- |
| Failure and Returned transitions | Actual actor/company/record/time | Present in activity rows and order status history | **Pass** |
| Complete old/new/reason context | Explicit transition and decision basis | Generic activity has no old/new state or operational reason; order history gives new state; Returned note may be null | **Fail** |
| Audit insertion fails during issue creation | Roll back issue/event | Injected SQLite trigger failure leaves **zero issues and zero issue events** | **Pass** |
| Export ownership, filters and formula safety | Company scope, valid filter constraints, escaped formula input | New foreign-row exclusion plus existing fresh export/filter/formula tests pass | **Pass** |
| Operational CSV privacy | Explicit private/no-store, like Finance exports | Actual header: **`Cache-Control: no-cache, private`**; no `no-store` | **Fail** stronger privacy criterion; not a public-cache header |
| Export access audit | Actual exporter/company/time recorded | Activity count delta **0** | **Fail** |

**Affected files/functions:** `app/Http/Middleware/LogisticsActivity.php::handle`; `LogisticsController::updateStatus/filteredShipments/exportReport`; order status history; `LogisticsManagementController::issueEvent`; `LogisticsReconciliationController` export is the existing reuse reference.

**Minimal recommendation:** append structured old/new state and appropriate reason to existing operational audit/history; retain transactional failure behavior. Apply Finance-style explicit private/no-store and scoped export auditing to operational CSV. Avoid logging credentials, account numbers, raw document paths, proof contents or full request bodies. Do not create another transaction ledger or claim existing specialized financial audit is missing.

## A13 — Current registration consistency

**Current implementation differs from the original finding.** Public and managed backend names now allow Unicode letters, hyphens and single internal whitespace; shared personnel licence validation now requires the displayed uppercase `N01-23-456789` style. Managed passwords retain minimum 12 characters plus mixed case/number/symbol; public passwords require minimum 8 plus the same complexity. The managed minimum was deliberately retained by A06, so its stronger length requirement is not a newly reproduced defect.

| Scenario | Expected result | Actual executed result | Status |
| --- | --- | --- | --- |
| Managed names `José María`, `李 明`, `Anne-Marie` | Accept complete valid application | Accepted in isolated transactions | **Pass** |
| Public rider names `José María`, `李 明` | Same server Unicode support | Registration responses success | **Pass** |
| Digits, markup, double-space names | Reject inappropriate characters/format | Managed rejects all tested values; public rejects tested digits/markup | **Pass** |
| Missing symbol/case/number; short managed password | Preserve complexity and managed minimum | Tested managed variants rejected; public missing-symbol rejected | **Pass** |
| Valid patterned licence versus arbitrary/malformed/lowercase identifier | Current explicit format enforced; do not claim government verification | Valid fixture accepted; managed malformed/lowercase rejected; public arbitrary identifier rejected | **Pass** |
| Original workbook server cases 005/012 | Rerun current code before repeating old findings | Both now pass; all **20 executable cases pass** | **Pass** |
| Early frontend feedback | Ideally match server without treating browser checks as authorization | Registration JS has current Unicode-name/complexity checks; managed form browser constraints accept invalid name strings until server submission; public rider step checks nonempty/availability but not complete licence pattern | **Needs improvement**, Low |

The managed synthetic browser preview retained password minimum 12. It has no client name-pattern/custom-validity rejection for tested digit/markup input; server rejection is independently confirmed. This is delayed feedback, not successful storage or an injection bypass. Public complete wizard/device camera execution was **Not Tested**; frontend conclusions beyond the synthetic managed form are source inspection.

**Affected files/functions:** `RegisterController::store`; `LogisticsManagementController::accountData`; `PersonnelApplication::rules/normalize`; `public/js/register.js::validateRegistrationName` and field validators; `resources/views/auth/register-rider.blade.php::validateRiderStep2`; `resources/views/logistics/partials/account-form.blade.php`, `personnel-application-fields.blade.php`; `public/js/role-account-details.js` applies to separate account pages, not the managed Create form.

**Minimal recommendation:** no backend reimplementation or relaxed fixture validation. Optionally align inline client guidance with current server rules. Apostrophes, combining marks, alternative government formats and changed password minimums require an explicit policy decision; none is silently imposed here. A format match is not licence authenticity verification.

## A14 — Dashboard

**Current implementation:** The newer dashboard has clear current-state descriptions, company-scoped queries, pipeline links and responsive CSS. The old literal question-mark copy is no longer reproduced. Current balances/workload are not period activity figures.

| Scenario | Expected result | Actual result | Status |
| --- | --- | --- | --- |
| Synthetic state matrix | Counts match documented current populations | Total13, Active6, Completed2, Failed1, Awaiting review1, Awaiting rider1, Sorted1, Transit3, Delivery progress2 | **Pass** |
| Foreign-company parcel | Not counted/displayed/exported | Excluded in new tests | **Pass** |
| Awaiting pickup rider with one approved/unassigned pickup | Click reaches that actionable parcel | Card shows1 but links to `logistics.requests`; that queue explicitly requires `pickup_approved_at IS NULL` and excludes it | **Fail** |
| Transit, Sorted and Delivery progress cards | Destination clearly corresponds to metric | Existing monitor/assignment destinations work, but are broader lists without matching stage query filters | **Needs improvement**, Low |
| Desktop/mobile dashboard | Readable layout without horizontal document scrolling | 1440px/390px checks and visual inspection pass | **Pass** |
| Period selector / online workload dashboard | Only if approved/needed for current-state product | Not implemented; not a count-integrity defect or reason for a second dashboard | **Not Tested / optional feature** |

**Affected files/functions:** `LogisticsController::dashboard/shipmentCounts/sidebarShipmentFilters/requests/monitor/assignments`; `resources/views/logistics/dashboard.blade.php`; `public/css/logistics-dashboard.css`.

**Minimal recommendation:** route the approved-pickup count to an existing appropriate actionable surface or add the smallest matching queue/filter in the existing workspace. Do not merely send it to the unapproved review queue. Preserve current metric definitions; optional stage filters should match the population. No new dashboard is needed.

## A15 — Reconciliation performance

**Current implementation:** `LogisticsReconciliation::snapshot/build` reads all company history in a repeatable-read snapshot, then filters/groups collections, allocations, movements and handovers in PHP. The activity date/export cap does not bound current-balance computation. Per-collection and per-handover repeated Collection filtering creates a dense-history cost even with a fixed SQL query count.

Fresh synthetic MySQL measurements: PHP **8.5.9**, MySQL **8.0.45**, local workstation, measurement-worker memory limit512MB. Each measurement is a separate PHP process using `hrtime`; peak figures include Laravel/bootstrap. One sample per volume; no SLA, production concurrency/load guarantee or statistically robust benchmark is claimed. Seeding time is excluded.

| Profile | Collections | Time | Peak process memory | SQL queries | Totals / conservation |
| --- | ---: | ---: | ---: | ---: | --- |
| Recorded, unremitted COD | 100 | 65ms | 28MiB | 37 | Pass |
| Recorded, unremitted COD | 1,000 | 87ms | 32MiB | 37 | Pass |
| Recorded, unremitted COD | 5,000 | 284ms | 46MiB | 37 | Pass |
| Recorded, unremitted COD | 10,000 | 593ms | 64MiB | 37 | Pass |
| One counted handover/allocation/custody movement per collection | 100 | 98ms | 28MiB | 37 | Pass |
| Same counted profile | 1,000 | 4,688ms | 36MiB | 37 | Pass |
| Same counted profile | 3,000 | **42,496ms** | 50MiB | 37 | Pass |

Each collection is10000 centavos. Pending-profile collected/outstanding equal N×10000 with zero custody. Counted-profile collected/verified/on-hand equal N×10000, outstanding zero, residual zero. Foreign-company canary transactions are excluded in the pending-volume profile. Finance regression also covers normal fully linked COD/GCash/voucher/payout and multi-account reconciliation.

These performance fixtures intentionally omit order/shipping basis and therefore generate expected `unknown_seller_basis` review entries. They are not fake eligible seller proceeds or a complete settlement-history benchmark. Counted fixtures use Staff attribution and the actual `cash_received` movement kind. No claim is made that every fully populated production history will be as fast as this profile. The performance risk is reproduced even on this narrower valid custody-accounting profile.

| Criterion | Expected result | Actual result | Status |
| --- | --- | --- | --- |
| Amounts, separate custody and company scope as volume grows | Exact totals, no double-counted cash | All measured totals exact; residual0; foreign canaries excluded | **Pass** |
| Increasing counted-cash history | Reasonable growth for interactive reporting | 3× records from1000 to3000 costs about9× time; 42.5-second single report | **Fail** readiness for larger dense histories |
| Actual production load, web/proxy timeout and simultaneous reports | Environment-specific measured capacity | Not exercised or inferred from CLI timing | **Not Tested** |

**Affected files/functions:** `app/Services/LogisticsReconciliation.php::snapshot/build`, particularly repeated allocation/movement/obligation collection filters; report controller/view and export paths consume the same snapshot.

**Minimal recommendation:** pre-index/group already loaded records by collection/handover/account, or use carefully scoped database aggregates while preserving one consistent snapshot, funding provenance and mirror exclusions. Benchmark fully linked histories and concurrent reporting before introducing persisted summaries. Retain all-history balance semantics, activity date semantics and export limits. No accounting policy, opening balance or ledger correction is warranted by this performance result.

## Approval boundary and review outputs

Necessary next priorities: **A04 physical RTS/restock evidence and A11 company ownership**, then A12 audit/export protections, A14 approved-pickup navigation and A15 aggregation performance. A10 should extend existing Issues after custody/escalation decisions; it does not authorize automatic refunds. A13 backend findings are resolved in current code; remaining early feedback is optional.

New review files:

- `tests/Feature/RemainingLogisticsAuditTest.php`
- `tests/Feature/RemainingRegistrationAuditTest.php`
- `tests/Support/remaining-logistics-audit-mysql.php`
- `docs/logistics-remaining-findings-test-review.md`

Repeat new PHP tests with `--filter test_audit` because their shared fixture parents also contain existing tests. MySQL helper: `php -d memory_limit=512M tests/Support/remaining-logistics-audit-mysql.php --run`; `--run race-only` repeats only ownership. It requires permission to create/drop its random isolated schema. Its test-only barrier instruments scheduling, not financial or validation policy.

Final artifacts: `remaining-audit.xml`, `remaining-registration.xml`, `remaining-regression.xml`, `remaining-financial-security.xml`, `remaining-workbook.xml`, `remaining-mysql-final.json`, `remaining-mysql-race.json`, latest observations and source comparison, plus synthetic dashboard HTML/screenshots, under the private review directory. Earlier exploratory runs are not added to final test totals.

No fix has been applied. No migration or deployment action is required for this report. A01–A03, A05/A08/A09, A06/A07 and Finance Phases1–7 remain in place. Implementation, structural ownership changes and RTS/claim financial treatment require separate approval.
