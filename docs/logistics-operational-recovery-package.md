# Logistics operational recovery package — A08, A09 and A05

Subsequent work: the separately approved A06/A07 implementation is documented in [Managed onboarding and personnel decisions](logistics-onboarding-decisions-package.md). Statements below describe the recovery package's original boundary.

Implemented 29 October 2026, in the approved order, against `post-security-operational-recovery-review.md` and the owner's attached approval. No new role, additional company operator, emergency delivery override, payroll deduction, financial policy or A04/A06/A07/A10–A15 implementation was introduced.

## Delivered workflows

### A08 — Completion evidence and availability

`AssignmentWork` supplies one read-only active/completed/ambiguous classification. Cancelled/rejected work is closed for availability, not earned compensation. Historical Accepted pickups with matching original rider, two-sided handover and chronologically consistent pickup/origin-receipt timestamps can classify completed without being rewritten. Matching Delivered assignments are terminal for operational availability; their assignment and shipment delivery timestamps must agree. Contradictory or missing evidence stays ambiguous and blocks transfer dispatch rather than inventing completion.

Genuine origin-hub receipt prospectively closes an evidence-complete pickup assignment in the existing receipt transaction, with an audit event. IDs, riders, original timestamps and immutable rates are retained. Existing receipt-triggered compensation recognition still runs; classification alone never recognizes earnings or changes money. Batch availability requires actual completed batch/member/leg evidence. Receiving one parcel does not complete a multi-parcel trip or create per-parcel transfer pay.

Transfer dispatch uses this classifier instead of treating every status except Completed/Cancelled/Rejected as active. Historical operational rows are not mass-normalized. Admin can inspect evidence classifications through the existing Delivery Assignments entry's **Assignment evidence & reassignment** link.

### A09 — Pre-collection reassignment

1. Existing company Admin or appropriately scoped Hub Staff requests a replacement from Assignment Review. The request carries a UUID, expected owner, monotonic revision and reason. It does not change ownership or attest custody.
2. Pickup requires Ready for Pickup, approved pickup, satisfied applicable shipping gate, no handover/pickup/hub receipt/route evidence and no collection or earned source. The actual owning seller must separately attest that the parcel is still physically with them. Normal Seller Confirm Pickup remains the opposite assertion and is not reused.
3. Final delivery requires an assigned, sorted parcel with no final-leg pickup/release/attempt/delivery/collection/earned evidence. Earlier seller-to-hub pickup timestamps remain intact. Actual approved destination Hub Staff must confirm physical hub possession and no release; Admin cannot supply this attestation.
4. Current actor, owner, replacement approval, company, operational hub, two-wheel/vehicle eligibility, source state and revision are checked under the existing transaction/resource locks. Pending possession review blocks the old rider's operational progression. If handover/release wins first, the proposal fails. If review wins first, old-rider progression fails until the review is confirmed or cancelled.
5. Confirmation updates the existing assignment and shipment owner mapping atomically, increments the revision, preserves append-only before/after/attester history and notifies old/new riders and the relevant seller/hub. Only pending unearned snapshots are cancelled/restored using the existing `LogisticsFinance` implementation. Returning to a previous rider restores their original snapshot/rate; current rates do not silently replace it.
6. Cancellation requires a reason and increments the revision without changing ownership or money. Duplicate pending submissions are retry-safe; stale decisions, including A→B→A ownership cycles, fail revision checks. Confirmation is not a physical pickup or delivery.

Dispatched transfer batches, already-carried parcels, uncertain custody, earned work and recorded collections cannot use this path. The server remains authoritative even if a page's state or rider choices become stale.

### A05 — Supervised suspended-rider recovery

Suspended riders remain blocked by existing middleware and current service checks. Pickup claim/confirmation now also rechecks fresh approval, company and ownership; a stale authenticated rider cannot override a committed suspension. No ordinary account reactivation or broad suspended-access exception was added.

Reuse remains the first choice: genuine fully confirmed pickups can be received by original Hub Staff, confirmed transfer arrivals by destination Staff, and already-submitted cash can be counted by assigned Staff even if the original rider is now suspended. Valid completed work can still earn its original approved compensation under those existing authoritative completion flows.

Where those normal parcel paths cannot apply, assigned Hub Staff uses **Issues > Supervised parcel recovery**. The receiver records distinct scanned tracking numbers (up to 100), suspended original rider, pickup/delivery/transfer leg and relevant batch IDs, actual hub/receiver, condition, reason, observation time, recording time and a mandatory custody/evidence reference. Optional JPG/PNG/WebP/PDF proof is private, limited to 5 MB and uses existing evidence lifecycle controls. Observation cannot be future-dated or precede the assignment. Admin monitors the case but cannot attest receipt.

Recovery appends an existing issue/event and financial audit event. It does not rewrite original assignment, parcel, batch or order progress, create normal pickup/delivery evidence, restore stock, recognize compensation or move cash. A recovery hold blocks progression, transfer pickup/receipt and reassignment, including after reactivation or generic issue resolution. Redispatch of carried work remains separately deferred. The original operational status may therefore still say Out for Delivery/In Transit while the separate recovery record establishes actual hub custody; this distinction is intentional and shown in Issues.

For existing recorded COD, assigned Staff uses **Cash Desk > Suspended rider cash recovery**. Collections must belong to that suspended rider and their original recorded receiving hub. Amounts cannot exceed currently unreserved outstanding collections. The original rider remains cash holder (`actor_id`); the actual Staff submitter is separately recorded (`submitted_by`) with reason/reference and a true-actor audit event. Submission reserves amounts only.

Staff then uses the unchanged physical Count action with explicit allocations. Same-Staff submission/counting is allowed as approved, with separate audits. Partial counts, shortages, overages, outstanding liabilities, custody and treasury provenance retain their existing rules. Admin cannot count, submissions cannot self-verify, and no shortage offsets rider earnings. Staff recovery submissions cannot be withdrawn through the normal rider withdrawal action, even following reactivation.

## Schema and deployment

One additive migration: `2026_10_29_000005_add_logistics_operational_recovery.php`.

| Existing table | Added data |
| --- | --- |
| `delivery_assignments` | Revision and current pending possession-review JSON; append-only decisions remain in existing audit events. |
| `logistics_cash_handovers` | Nullable real submitter FK and recovery reason/reference. Historical submitter attribution is not guessed. |
| `logistics_issues` | Nullable unique recovery UUID and recovery manifest/evidence metadata. |

No parallel dispatch, cash or compensation ledger was created. Destructive rollback is rejected to preserve attribution/history. Per-column guards support resuming MySQL's nontransactional DDL. During local installation, a signed/unsigned submitter-FK mismatch was detected; the migration was corrected to match the existing signed user IDs and resumed successfully. `migrate:status` confirms it installed. No historical status, rider, rate, paid flag, cash balance, document reference or legacy assignment was backfilled.

Other machines must pull all required new files and run `php artisan migrate` against their intended database. This local result does not install the migration, transfer private evidence/documents or certify a groupmate's presentation machine. Existing private-document deployment/missing-file limitations in the preceding review remain unchanged.

## Verification

- Final selected regression: **337 tests / 4,241 assertions passed**, zero failures/errors. Result: `storage/app/private/logistics-ui-review/operational-recovery-final.xml`.
- New `OperationalRecoveryTest`: **18 tests / 154 assertions passed**. These overlap the selected regression; do not add totals.
- Coverage includes historical read-only classification, live pickup closure, Delivered evidence consistency, partial/full batch availability, old-rate restoration, seller-only/destination-Staff attestation, Admin denial, cross-company/hub scope, suspended/ineligible replacements, stale/duplicate requests, old-rider progression, A→B→A staleness, source earnings protection, separate recovery submission/count, shortage/overage reconciliation, private recovery evidence and recovery holds after reactivation.
- Audit-failure tests prove reassignment owner/revision/snapshot rollback, cash recovery reservation rollback and removal of rolled-back parcel recovery issues/private proof. Existing financial and A01–A03 tests remain in the selected run.
- Real multi-process MySQL checks passed again using only the runner's random schema-only database and private fixture files. New races cover competing proposals, identical retries, competing seller attestations, reassignment versus rider pickup, seller handover and final-delivery release, suspension versus pickup, competing Staff physical recovery, competing recovery cash reservations and physical count versus the remaining recovery submission. Commit-order outcomes retain one owner/custody event and separate verified/reserved balances.
- Existing MySQL cash, shipping/reference, treasury, seller settlement/dispute, rider whole-trip earning/payroll/hold/acknowledgement and consistent report-snapshot races also passed. Recovery counting preserved the conservation equation without inventing a second collection or offset.
- Five isolated browser fixture views at **1440px and 390px**: Admin assignment review, seller possession confirmation, destination Staff confirmation, parcel recovery/Issues and recovery Cash Desk. No document overflow or unlabelled new controls; text/select/file controls and labelled checkbox hit rows meet 44px. Expected sidebar active groups and Dashboard/My Hub ordering remain. Desktop recovery and mobile Staff screenshots were visually inspected. No claim of real-device scanning or complete accessibility acceptance is made.
- New PHP services/controller/migration passed syntax checks; `git diff --check` passed. Private rendering/browser helpers and fixture results remain under `storage/app/private/logistics-ui-review/`, outside product routes.

The selected tests use isolated SQLite; MySQL races are a separate check. They do not certify real-world handovers, cash, bank transfers, every repository test or the original manual workbook. Known hiring/rider-workbook fixture failures and device skips were not repaired or relabelled as passing in this package. No failing selected tests remain.

## Changed files for this package

| Area | Files |
| --- | --- |
| New services/controller | `app/Services/AssignmentWork.php`, `app/Services/AssignmentReassignment.php`, `app/Services/LogisticsRecovery.php`, `app/Http/Controllers/LogisticsOperationsController.php` |
| Schema/models | The additive migration above; `app/Models/DeliveryAssignment.php`, `app/Models/Shipment.php`, `app/Models/LogisticsIssue.php` |
| Existing operational/financial integration | `app/Services/HubTransferWorkflow.php`, `app/Services/LogisticsFinance.php`, `app/Services/LogisticsCashDesk.php`; controllers `LogisticsController.php`, `RiderController.php`, `LogisticsManagementController.php`, `LogisticsCashDeskController.php`, `SellerPayoutController.php` |
| Routes/navigation | `routes/web.php`, `resources/views/logistics/partials/sidebar.blade.php`, `resources/views/seller/partials/sidebar.blade.php` |
| New UI | `resources/views/logistics/assignment-review.blade.php`, `resources/views/logistics/partials/recovery-custody.blade.php`, `resources/views/logistics/partials/recovery-cash.blade.php` |
| Existing UI entry points/history | `resources/views/logistics/assign.blade.php`, `resources/views/logistics/account-edit.blade.php`, `resources/views/logistics/hub-dashboard.blade.php`, `resources/views/logistics/issues.blade.php`, `resources/views/logistics/cash-desk.blade.php`, `resources/views/seller/orders.blade.php`, `public/css/logistics-cash-desk.css` |
| Tests | `tests/Feature/OperationalRecoveryTest.php`, `tests/Feature/HubTransferWorkflowTest.php`, `tests/Feature/RiderCompensationTest.php`, `tests/Support/cash-desk-mysql.php` |
| Documentation | This report; a follow-up reference in `docs/post-security-operational-recovery-review.md`. |

The workspace also contains earlier Phase 1–7/security changes and unrelated existing edits. They are not claimed as new changes from this package. The transfer compensation fixture now uses a separate transfer rider instead of recycling a delivery rider whose fixture deliberately erased authoritative shipment completion evidence; production eligibility was not weakened to accommodate the fixture.

## Remaining limitations and approval boundaries

- Recovered parcels intentionally remain held. Redispatch, dispatched-batch reassignment, unavailable-rider delivery correction, recovered transport compensation and final financial corrections require separate approval/design. Marking an issue resolved is not such approval.
- Real no-release/possession assertions are authenticated staff/seller statements. The application cannot independently establish what happened physically. Conflicting statements or missing source evidence require investigation, not cleared timestamps or edited rider IDs.
- Reference-only recovery evidence is allowed as approved; this is not a mandatory-photo policy. Existing private file and audit protections apply when proof is attached. Lost/missing documents from the prior review remain unresolved and unchanged.
- A submitted recovery remittance reserves its selected amount until the existing physical count. No new recovery withdrawal/financial reversal tool was introduced; incorrect submissions/counts need authorized exception review. Unrecorded cash, another receiving hub or disputed liability must not be disguised as a collection or silently relabelled.
- Historical ambiguous work is visible for manual review and can conservatively block availability. No bulk legacy normalization or automatic earning backfill was run. Original status/timestamps remain the historical source.
- Recovery availability holds apply to the recovered rider/leg. A completed earlier pickup on the same parcel remains completed; recovering final delivery does not retroactively make its original pickup rider's work active.
- Existing suspension decision-reason/onboarding gaps, RTS, single-owner enforcement and broader report performance remain their separately deferred audit findings. No new roles, penalties, commissions, offsets, payouts or emergency custody bypasses were added.

The approved package is complete within these boundaries. A04, A06, A07 and A10–A15 have not been started.
