# Logistics managed onboarding and personnel decisions — A06/A07

Implemented 29 October 2026 against the approved compatibility review. Existing Laravel/MySQL services, six system roles and company Admin authority are retained. No new role, company operator, onboarding module, pending-account operational access, financial policy or A04/A10–A15 work was introduced.

## A06 — Complete managed applications


The existing Logistics Admin Create/Edit personnel forms now collect complete role-specific application evidence. `PersonnelApplication` shares document/vehicle requirements and normalization with public registration. Managed writes also reuse `HubHiring`'s identity query and MySQL identity mutexes for normalized mobile, email/username, person identity and licence/plate checks. Existing managed password minimum length is retained and the public complexity requirement is applied; new/pending managed application email/username rules match registration.

- Rider: licence number, non-expired licence, selfie and existing vehicle information. The licence is also the generic identity document, using the existing Driver's License lookup; no separate rider ID upload is required.
- Own vehicle: brand/model/plate and OR/CR are required under the existing rules.
- Company vehicle: current platform-approved, available matching company vehicle at the assigned operational hub is required. Personal OR/CR is not required. Existing documents are not erased when ownership changes.
- Hub Staff: identity type, identity document and selfie; Pending → Interview → Approved/Rejected remains the workflow.
- Region/address checks reuse the existing geography service and assigned hub. Optional suffix is supported with the existing enum. Existing name policy is unchanged.

New managed applications cannot be saved without required data. They start Pending, with messages explicitly distinguishing application creation from approval. Existing Pending/Interview accounts can complete their application without changing account ID or status. Existing history and interview scheduling data remain; completion appends an audit entry under the same attempt. Pending personnel still cannot log in or use operational routes.

Readable private files are retained unless a genuine replacement is uploaded. Missing files require a replacement; a client-supplied path is not an upload. New files use `MediaLifecycle` and the A03 private disk/authorized review endpoint. Genuine replacements use after-commit cleanup with existing reference protection; rollback retains the original and removes newly uploaded files. Document-reference changes record before/after hashes rather than exposing raw storage paths in history.

New approvals require safe, readable regular files physically resolving inside private storage. A nonempty path or surviving public copy is insufficient. Rider licence/identity linkage is checked. Existing approved accounts are not automatically suspended or rewritten because a historical file is missing. Approved-account document changes remain outside managed onboarding; the existing Platform Admin account-update authority is preserved.

Final Hub Staff approval now requires an explicit interview-completed checkbox. Its actor and attestation time are appended to existing hiring history JSON. Scheduling alone is insufficient. No historical interview attestation was fabricated, and no additional interviewer or account role was added.

Public rejected-account reuse remains separate from managed completion. Fixture repair exposed an existing mobile check that rejected a matched rejected account's own number. The check now excludes only that rejected email/username candidate; full locked identity, role/company and attempt matching must still pass. Other users' numbers remain unavailable. Pending or approved accounts cannot be merged through this path.

## A07 — Reasons, decisions and notifications

Rider Reject, Suspend and Reactivate reuse the existing shared reason modal and hiring history. Incoming presets must be from the approved displayed list. Other requires a trimmed explanation of at least three characters containing a letter/number; whitespace or punctuation alone is rejected. Direct status-transition guards also require meaningful reasons, including reactivation, rather than relying on form validation alone.

The existing company Admin can decide company riders. Hub Staff remains unable to manage personnel. Fresh actor status/type/company and source status are checked under locks. Decision state, reason, original attempt, actual actor, hub/company, prior/new status and audit history commit together. Audit failure rolls back the decision. Duplicate/stale decisions do not append another transition.

Reactivation clears the current `status_reason`, while its explanation and prior suspension reason remain in append-only hiring history. It preserves the existing reactivation eligibility policy: it does not require full registration again or newly require historical files/licence renewal. Hub operational/capacity safeguards remain. It does not release recovered-parcel holds or change COD obligations, assignments, earnings, payout reservations or balances.

Notifications run after successful commit. Rider decision notifications use generic account/recovery wording; investigation details are not copied into email or rejected-rider login responses. The documented reason remains visible to authorized personnel reviewers. Existing Platform Admin rider suspension/reactivation authority is retained, with the same required rider reason and generic notification treatment. Other account types' existing review authority is unchanged.

Application history now displays recorded transitions, completion actions and explicit interview attestation alongside existing actor/attempt information. No parallel decision or finance ledger was created.

## Schema and deployment

**No migration or new table is required.** Existing hiring history `review_details` JSON stores action/transition, attestation and document-reference hashes. `User::$hiringDecision` is transient operation context, not a mass-assignable or persisted account column; it is cleared after save.

Deploy the complete code, including both new services and the new Blade partial. Existing hiring/registration/security migrations and the configured Driver's License/vehicle lookups must already be installed. Private storage must be writable and remain outside web exposure. Each presentation machine still needs its own database and authorized private files; Git does not distribute those files or repair missing references.

No live application was created, approved, rejected, suspended, reactivated or completed during verification. No original document, financial record, role or historical interview was fabricated. MySQL checks use their own random schema-only database and isolated files.

## Verification

- Selected finance/security/fulfillment/commerce/logistics/onboarding regression: **370 tests / 4,606 assertions passed**, zero failures/errors. Artifact: `storage/app/private/logistics-ui-review/onboarding-final.xml`.
- Final focused hiring/onboarding/authorization run: **69 tests / 1,655 assertions passed**, including final malformed-reason input checks. It overlaps the selected regression; do not add totals.
- New `PersonnelOnboardingTest` covers managed private uploads, licence-as-ID, missing/forged documents, own/company vehicle rules, Pending/Interview completion, file retention/replacement, explicit Staff attestation, invalid/empty/punctuation-only reasons, reactivation history, wrong company/Hub Staff/revoked Admin denial, identifier normalization, after-commit generic notification and account/document/decision audit rollback.
- Existing A01–A03, A05/A08/A09 and Phase 1–7 tests remain in the selected run. Reactivation with missing legacy files/expired historical licence is tested to preserve the approved unchanged reactivation policy; this is not certification of that rider's real-world fitness to work.
- Real multi-process MySQL checks passed for competing managed identity/licence applications, completion versus approval, duplicate suspension/reactivation and competing Staff approval with one recorded interview attestation. Existing finance, cash, transfer, payout, recovery and reporting snapshot races also passed. The runner now clones the existing hiring/lookup schemas and uses isolated private document storage/cache/mail; it removes its test database and files afterward.
- Five synthetic browser views checked at **1440px and 390px**: Create Rider, Create Staff, Pending Edit, rider reason modal and Staff interview confirmation. No document overflow or unlabelled new form fields. Visible managed text/select/file controls are at least 44px; reason selections use padded rows and the action button uses the shared button style. Expected Personnel parent/active links and Dashboard-first ordering remain. Mobile evidence editing/reason selection and desktop interview confirmation screenshots were visually inspected.
- Browser previews serve synthetic HTML/geography; they do not bypass the real private-document endpoint or certify real document contents. Laravel HTTP tests cover actual authorization/storage behavior. Physical-device camera/file-picker and comprehensive accessibility acceptance were not claimed.
- PHP syntax checks and `git diff --check` passed. No complete-repository-green claim is made.

## Fixture corrections and remaining workbook results

`HubHiringTest` now has valid registration region/password data, controlled geography with stray requests blocked, the current registration schema, private file fixtures, real interview attestation and valid documented decisions. Managed-creation fixtures supply the newly required evidence. Existing history/role/capacity assertions remain; application completion now correctly expects an additional history event. **All 20 hiring tests pass.**

Authorization/transfer fixtures now supply the real application preconditions and decision reasons. Transfer fixture setup explicitly documents its suspension/reactivation reset instead of bypassing the new model guard. Production validation was not relaxed to preserve an old fixture.

Rider workbook server-side rerun: **47 cases: 18 passed, 2 failed, 27 skipped; 243 assertions**. Fixtures now include private uploads, controlled geography, valid region/email, independent identifiers and current route aliases. The old Pending Activation/email-invitation expectation was adapted explicitly to the approved Pending application workflow. Public rider registration returning 200 is intentional, not a management authorization bypass.

Remaining failures:

| Case | Remaining mismatch |
| --- | --- |
| `TC-RID-005` | Workbook accepts multi-word given names; the existing registration/managed name regex does not. The boundary fixtures are now isolated. Name-policy expansion is A13 and was not silently implemented. |
| `TC-RID-012` | Workbook demands a particular licence-number pattern. Current public/managed rules require a number, length and case-insensitive uniqueness but do not enforce that particular pattern or verify a government record. A stricter identifier policy needs explicit agreement. |

The 27 existing device/UI/activation workbook skips remain unexecuted. Skip copy no longer incorrectly says managed document controls are absent. These automated adaptations are not a manual pass of the original workbook. Other repository suites outside the selected run were not certified.

## Changed files

| Area | Files |
| --- | --- |
| Shared application/decision requirements | `app/Services/PersonnelApplication.php` (new), `app/Services/PersonnelReasons.php` (new), `app/Services/HubHiring.php`, `app/Models/User.php` |
| Existing controllers | `app/Http/Controllers/LogisticsManagementController.php`, `app/Http/Controllers/LogisticsController.php`, `app/Http/Controllers/RegisterController.php`, `app/Http/Controllers/AdminController.php` |
| Managed onboarding UI | `resources/views/logistics/partials/personnel-application-fields.blade.php` (new), `resources/views/logistics/partials/account-form.blade.php`, `resources/views/logistics/account-edit.blade.php`, `resources/views/logistics/riders.blade.php`, `resources/views/logistics/staff.blade.php`, `public/css/logistics-ui.css` |
| Shared/Platform decision UI | `resources/views/admin/partials/reason-modals.blade.php`, `resources/views/admin/users.blade.php` |
| Tests | `tests/Feature/PersonnelOnboardingTest.php` (new), `tests/Feature/HubHiringTest.php`, `tests/Feature/LogisticsAuthorizationTest.php`, `tests/Feature/HubTransferWorkflowTest.php`, `tests/Feature/RiderWorkbookTest.php`, `tests/Support/cash-desk-mysql.php` |
| Documentation | This report and a follow-up reference in `docs/logistics-operational-recovery-package.md`. |

No new product route, schema migration, account role or finance service change was needed. Earlier workspace edits belong to prior packages and are not claimed as changes from this implementation. Private verification helpers/results/screenshots are under `storage/app/private/logistics-ui-review/` and are not product routes or identity assets to commit.

## Remaining risks and approval boundaries

- File readability, field validation and interview attestation are application controls, not independent verification of document authenticity or physical interview attendance.
- Existing approved accounts were not mass-audited or suspended. The previously missing historical documents remain a genuine-source/re-upload investigation; this package does not invent replacements.
- Managed onboarding completion is limited to Pending/Interview. Approved-account document review remains under existing Platform Admin authority; no new company-Admin replacement privilege was added for approved personnel.
- Generic notifications deliberately omit potentially sensitive reasons. Authorized decision history is the record; failed mail delivery does not undo the committed decision.
- Restored operational access does not resolve recovery custody, outstanding cash, payout holds or financial exceptions. No automatic penalties, refunds, deductions or earnings changes were added.
- No decision remains necessary for the implemented A06/A07 scope. Multi-word names, stricter licence-number format, changed reactivation eligibility and the deferred A04/A10–A15 findings require separate approval before changes.
