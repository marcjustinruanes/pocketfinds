# PocketFinds Hub-to-Hub Transfer Audit

Audit date: 11 November 2026. Scope: Logistics Admin and Hub Staff transfer operations, with the existing rider pickup callback inspected only as the required handoff. **Audit/report only: no application implementation, migrations, configured-database writes or live record changes were performed.** Existing workspace changes were preserved.

## Executive assessment

The main transfer workflow is implemented, with strong company/hub authorization and guarded state transitions. Request, approval, dispatch, rider pickup and actual hub receipt are distinct recorded events. Partial receipt, multi-hop progression and final sorting work in isolated fixtures.

It is not clean enough to label fully verified. Reproduced problems affect cross-province route generation, offline rider eligibility, partial-receipt activity logs and historical pickup display. Further confirmed gaps concern legacy/inconsistent custody evidence, notifications and discrepancy retries. No cross-company or wrong-hub transfer mutation bypass was demonstrated in the tested cases.

## Evidence and limits

- Read controllers, transfer/locking/custody/financial services, models, route middleware, request/dispatch/receipt forms, scanner adapters and existing tests.
- Inspected the configured MySQL table definitions/indexes and read-only relationship/integrity counts using **SELECT/SHOW only**.
- Ran **39 existing tests, 496 assertions: passed**, covering `HubTransferWorkflowTest`, `HubTransferMigrationTest` and `ParcelCustodyTest`. Their schemas/migrations operate only on isolated SQLite `:memory:` fixtures; no configured database migration was run.
- Ran **9 isolated audit probes, 65 assertions: passed**. These assert observed behavior, including the problematic behavior below; they are reproductions, **not nine claims that the implementation is correct**.
- Did not run any disposable-MySQL verifier, create/drop a database, perform a live transfer, send real notifications/mail, alter a rider/vehicle, or execute a financial transaction. Current real-MySQL concurrency and browser/camera behavior were not exercised.
- Existing Pest test-history permission warnings occurred without preventing test completion. An initial temporary probe file had a Windows UTF-8 BOM parsing failure; that audit artifact was corrected before the reported successful run.
- Evidence artifacts are in ignored `storage/app/private/hub-transfer-audit/`: `read-only-data.json`, `schema.json`, `existing-tests.xml`, `probes.xml` and the isolated probe source. No new production or repository test implementation was added.

## Ten-stage workflow assessment

| Stage | Assessment | Current behavior / qualification |
| --- | --- | --- |
| 1. Origin Staff select parcels/request transfer | Working with qualification | Origin is fixed to the assigned staff hub. Service checks company, distinct operational hubs, matching next leg, no active/conflicting earlier leg, aligned order/shipment stage, and no existing request/batch. Route generation and missing historical custody evidence have findings below. |
| 2. Admin review/approve/reject | Working | Only Logistics Admin can decide. Approval rechecks every member and route. Rejection requires a reason, retains historical membership/events and releases pending legs for a new request. |
| 3. Admin select four-wheel rider/vehicle | Partly working | Company, origin hub, approval, four-wheel type, ownership, busy work and company vehicle checks exist. **Online availability is missing.** Assignment is not a separately persisted pre-dispatch stage. |
| 4. Admin dispatch approved batch | Working with eligibility qualification | Rider/vehicle selection and dispatch are one atomic operation. Only approved batches with approval evidence dispatch. Per-batch financial snapshot, leg/shipment/order changes and dispatch event are transactional. |
| 5. Rider confirms pickup | Working | Existing callback requires assigned approved rider, matching dispatched company batch and active unconfirmed leg. Records per-parcel pickup evidence and alerts destination staff. Repeated confirmation rejects. No Rider module changes are proposed here. |
| 6. Destination Staff view incoming/receive | Working | Batch visibility is company plus origin/destination hub. Receipt requires assigned operational destination Hub Staff and recorded rider pickup; admin/origin/unrelated hub receipt rejects. |
| 7. Partial/complete receipts | Working operational state; activity-log bug | One tracking number receives one parcel. Remaining members stay unreceived and batch stays in transit. Final member completion closes the batch. Generic activity logs incorrectly include all member legs for a partial batch endpoint call. |
| 8. Intermediate hub onward transfer | Working | Completed current leg unlocks the next pending leg at that hub. A fresh request, approval, dispatch, pickup and receipt are required per hop. Received parcels can move onward while other members of the earlier batch are still in transit. |
| 9. Final hub delivery readiness | Working | Final transfer receipt clears transfer rider, sets shipment and order to `sorted`, records completion/sorting timestamps and history. Normal delivery assignment/offer eligibility, including barangay coverage, remains separate and applicable. |
| 10. History, alerts, tracking and custody | Partly working | Member receipts, batch events and final status history are recorded. Archived pickup display, generic partial-receipt activity and incomplete notification coverage need correction. Legacy imported evidence is explicitly incomplete. |

## Confirmed findings and recommended fixes

### F1 — High: matching municipality names in different provinces generate no transfer route

**Sources:** `app/Models/LogisticsHub.php:108`, `app/Models/Shipment.php:182`, `app/Models/Shipment.php:236`, `app/Http/Controllers/LogisticsController.php:831`.

`Shipment::needsHubTransfer()` correctly compares municipality and province, but `LogisticsHub::buildRoute()` returns `[]` whenever municipality names match, before considering their provinces. An isolated reproduction using Santa Cruz, Laguna → Santa Cruz, Marinduque returns an empty route although the shipment requires transfer. The ordinary origin-receipt code then has no generated next leg available for a request.

**Impact:** affected parcels can become stuck at `at_sorting_center`, missing from actionable transfer requests. Existing multi-hop tests construct their legs directly, so they do not catch this route-builder defect. The current configured hub data has no same-name cross-province pair, so this was reproduced in memory rather than observed on a currently stuck live parcel.

**Recommended fix:** use municipality **and province** for the same-location check; generate the normal province/regional route for different provinces. Add a receipt-to-generated-legs regression instead of only manually constructing the route fixture. No unrelated delivery or financial change is needed.

### F2 — Medium: offline transfer riders can be selected and dispatched

**Sources:** `app/Http/Controllers/HubTransferController.php:42`, `app/Services/HubTransferWorkflow.php:243`.

The dispatch dropdown lists approved four-wheel origin-hub riders without an `is_available` predicate. `dispatch()` validates company/hub, role, approval, type, ownership and workload, but likewise never checks online availability. The isolated probe explicitly sets `is_available=false`, sees the rider in the dropdown and successfully dispatches the batch.

The dropdown also lacks some backend workload/ownership checks, so other apparently selectable riders can fail only after submission. The backend does correctly reject conflicting active work and unsuitable company vehicles.

**Recommended fix:** centralize **transfer-specific** rider eligibility for both dropdown and dispatch, including current online availability under the existing rider lock. Preserve exclusive active-batch/work protection and four-wheel rules; do not reuse the two-wheel three-job rule indiscriminately. If offline dispatch is intended as an override, require an explicit authorized override/reason instead of silently ignoring availability.

### F3 — Medium: partial receipt writes receive activity for unreceived parcels

**Sources:** `app/Http/Controllers/HubTransferController.php:82`, `app/Http/Controllers/HubTransferController.php:125`, `app/Http/Middleware/LogisticsActivity.php:35`.

`HubTransferController::result()` puts **all member leg IDs** in `logistics_record_ids` for every action. The activity middleware emits a `logistics.transfers.receive` log for each ID. A two-parcel probe received only the first tracking number: the second member still had `received_at=null`, but its leg nevertheless received a receipt activity entry.

**Impact:** batch member rows and transfer events remain accurate, but the generic company activity log can falsely appear to record staff receipt of parcels not actually received.

**Recommended fix:** supply action-specific affected IDs for receipt, or log the batch action once with an explicit selected-member list. Preserve the existing one-parcel `parcel_received` events and atomic receipt behavior. Test that an unreceived member has no parcel-specific receipt activity.

### F4 — Medium: rejected/cancelled batch details can display pickup evidence from a later batch

**Sources:** `app/Services/HubTransferWorkflow.php:204`, `app/Http/Controllers/HubTransferController.php:42`, `resources/views/logistics/transfer-show.blade.php:45`.

Rejection/cancellation intentionally retains old member rows and releases the route leg for reuse. The old member's `leg_id` still resolves to that same live leg. The view correctly overrides its **status** for rejected/cancelled batches, but reads `rider_confirmed_pickup_at` unconditionally from the leg. The probe rejected batch A, re-requested/dispatched the leg in batch B and confirmed pickup; batch A's pickup column then showed batch B's pickup timestamp.

**Impact:** historical rejection/cancellation screens attribute later pickup evidence to a batch that never dispatched. Current parcel custody may legitimately be shown separately, but must not be confused with that historical batch's evidence.

**Recommended fix:** display pickup evidence only when the leg still belongs to the displayed batch and that batch legitimately dispatched; show not-applicable for never-dispatched rejected/cancelled batches. Use batch/member/event evidence for immutable history. Do not erase the retained historical membership or rewrite live leg history.

### F5 — Medium validation gap: dispatch can proceed despite unknown prior hub custody

**Sources:** `app/Services/HubTransferWorkflow.php:142`, `app/Services/HubTransferWorkflow.php:192`, `app/Services/HubTransferWorkflow.php:243`, `app/Services/ParcelCustody.php:11`.

`nextEligible()` checks stage, leg order and hub locations, but not an authoritative receipt establishing that the parcel is physically at the proposed origin. A fixture with `at_sorting_center` status and a valid pending route leg but no origin receipt timestamp is described by `ParcelCustody` as **Custody needs review** and is nevertheless approved/dispatched.

**Qualification:** this is a confirmed gap for legacy/inconsistent records, not a demonstrated external ability to create arbitrary valid stages through normal routes. The ordinary origin receipt path does record custody. Historical imported data may legitimately lack witnesses, which is why silently inventing receipt evidence would also be wrong.

**Recommended fix:** validate the authoritative preceding origin/member receipt before new dispatch, with a separately reviewed legacy exception/recovery policy for genuinely missing historical evidence. Do not infer physical custody solely from the status string or fabricate old receivers/timestamps.

### F6 — Low: receipt/completion notifications and transfer deep links are incomplete

**Sources:** `app/Services/HubTransferWorkflow.php:159`, `app/Services/HubTransferWorkflow.php:204`, `app/Services/HubTransferWorkflow.php:243`, `app/Services/HubTransferWorkflow.php:294`, `app/Http/Controllers/CommerceController.php:67`.

Implemented notifications cover request → company admins, decision → requester, dispatch → rider, and rider pickup → approved destination staff. `receive()` records member/batch events but emits no receipt/completion notifications. The probe completed a batch and found no increase in notification rows. Generic notification opening also sends logistics users to the monitor rather than routing each hub-transfer notification to its batch/parcel; references vary by notification kind and are not consistently batch IDs.

**Impact:** origin staff/admins must poll the screens to learn arrival/completion, and alerts do not reliably open the exact transfer record. Database receipt state itself is not lost.

**Recommended fix:** agree on the required receipt/completion recipients and add deduplicated, post-commit/in-transaction notification records through existing patterns. Give each transfer notification type a consistent batch/parcel reference and an authorized deep link. Preserve company/hub recipient scope and do not notify unrelated hubs.

### F7 — Low: discrepancy submissions have no retry identity

**Source:** `app/Services/HubTransferWorkflow.php:345`.

Two identical discrepancy submissions append two events; there is no idempotency key or retry token. The probe confirmed two duplicate reports. This does **not** double-receive parcels, pay compensation or bypass hub authorization.

**Recommended fix:** protect accidental form retries using a request identity while allowing genuinely distinct later observations. Do not deduplicate every matching reason forever, because two real observations can legitimately have the same text.

## Design/UX observations, not proven authorization exploits

- **Selection and dispatch are combined.** Choosing the rider/vehicle is part of the dispatch form and transaction. There is no independently saved assigned-but-undispatched stage. Steps 3 and 4 of the requested checklist are therefore supported as one atomic operation, not two separate persisted actions. Introduce another stage only if that is an explicit business requirement.
- **Company versus personal vehicle history:** company dispatch reserves a specific fleet vehicle ID; personal-vehicle dispatch records the rider with `vehicle_id=null`. The details page shows a personal/legacy placeholder rather than an immutable personal plate/vehicle snapshot. If audit policy requires a permanent personal-vehicle identity, define that requirement before changing the data model.
- **Picker versus service eligibility:** the request picker filters route/stage/previous-leg conditions but does not reproduce every service recovery-hold/order-alignment check. Invalid/stale choices are rejected server-side; the UI can still advertise an option that fails on submit. Keep the server authoritative and show the same exclusions where practical.
- **Scale:** request form validation caps explicit selected parcels at 2,000, while legacy grouped route requests can collect a whole matching group and the shared service has no equivalent size cap. Full-member processing and `GcashLocks` can expand connected batch footprints despite paginated screens. This is an unmeasured load/contention risk, not a reproduced denial-of-service exploit. Consider a shared service cap and representative load tests before production scaling.
- **Final delivery:** final receipt creates sorted readiness. It does not itself call the delivery-offer generator used by the local direct-receipt path; existing delivery pages/rider availability processing or manual staff offers initiate the next offer. This is not a failure to enter the delivery assignment queue.

## Working authorization and security controls

- Same role/features for all approved Hub Staff; authorization is derived from the assigned operational hub, not a special named sender/receiver account. The second-destination-staff probe successfully received and was recorded as receiver.
- Company names/requester/status fields are taken from the authorized actor/service, not accepted from injected request fields.
- Origin staff cannot request from another hub; unrelated/foreign-company batch IDs and filter parameters are scoped/blocked.
- Destination-only receipt, operational hub validation and per-parcel rider pickup evidence apply to both batch endpoints and legacy scan adapters.
- Hub Staff cannot use admin approval/rejection/dispatch routes; a company admin cannot impersonate physical destination receipt.
- Distinct hubs, unambiguous municipality/province records, correct next-hop route and no earlier unfinished/active/conflicting leg are enforced.
- Duplicate selected parcels, reused current membership, stale statuses, duplicate approval/dispatch/pickup/receipt and conflicting rider/vehicle work are rejected. Repeated state-changing requests do not create extra dispatch/receipt history in the existing tests. Discrepancy retries are the explicit exception above.
- Dispatch requires an approved matching company vehicle at the origin hub for company-vehicle riders, prevents concurrent batch reservation and rejects a company vehicle supplied for a personal-vehicle rider.
- Transactions and the operational lock footprint protect batch/member/leg/order updates; event/audit failures roll back the operation in existing isolated tests.
- Web mutation routes retain CSRF and role middleware; query parameters use bound queries; reasons/identifiers shown in the inspected Blade transfer views are escaped. No arbitrary cross-company mass assignment or direct status-skip endpoint was found in this transfer path.
- This is not a full penetration test or a fresh MySQL lock-contention test. No database-level unique active-rider/vehicle constraint exists; those reservations depend on the service's locking/rechecks. Do not bypass that service in future transfer actions.

## State and custody semantics

| Action | Batch | Current leg | Shipment/order | Custody meaning |
| --- | --- | --- | --- | --- |
| Request | `pending` | `pending`, requested/batch IDs set | Remains `at_sorting_center` or intermediate `hub_transfer` | Request does not move a parcel. |
| Approve | `approved` | Still `pending`, approval evidence set | Unchanged | Approval does not attest pickup. |
| Reject/cancel before dispatch | `rejected` / `cancelled` | Pending request/approval/batch link released | Unchanged | Historical members/events retained. |
| Select rider/vehicle and dispatch | `in_transit` | `in_transit` | `hub_transfer`, transfer rider set | Dispatch authorization; rider pickup may still be outstanding. |
| Rider confirms pickup | `in_transit` | Pickup timestamp set | `hub_transfer` | Recorded rider physical pickup. |
| Receive selected parcel, further hop exists | Remains `in_transit` until every member received | This leg `completed`; member receiver/time set | Remains coarse `hub_transfer`, transfer rider cleared | Physically at intermediate hub, not necessarily currently on a vehicle. |
| Receive selected parcel at final hub | Remains `in_transit` if other members outstanding | This leg `completed` | This parcel/order `sorted` | Ready for ordinary eligible final-delivery assignment. |
| Receive last outstanding member | `completed`, batch receiver/time set | All members' legs complete | Each parcel follows its own next-hop/final rule | Whole batch receipt evidence permits completion recognition. |

The coarse `hub_transfer` shipment status at an intermediate stop is intentional; leg/member receipts and `ParcelCustody` establish whether the parcel is waiting at a hub or actually with a rider. It must not be interpreted alone as proof of movement. No additional incorrect normal-path state transition was reproduced beyond the routing/evidence findings above.

## Relationships, live data and financial boundaries

- A shipment has ordered hub legs; the current leg points to its current batch.
- A batch belongs to a company, origin and destination hubs, and optionally a company vehicle; it has rider/decision/dispatch/receipt actors and timestamps.
- Members link batch + shipment + leg, retaining tracking and actual receipt actor/time. Events retain action, actor, statuses, optional parcel and reason.
- Confirmed live uniqueness indexes: `(shipment_id, sequence)`, `(batch_id, shipment_id)` and `(batch_id, leg_id)`. Batch actor/hub/rider/vehicle foreign keys and member batch/leg/receiver relationships are installed. Some history references deliberately allow null-on-delete; company and semantic membership consistency still require application validation.
- Read-only live data: **one completed legacy-import batch, one completed leg, no pending/approved/in-transit batches**. Zero duplicate sequences/members, member-company mismatches, active rider/vehicle conflicts or unexpected order/shipment status mismatches were found. Active-batch checks are necessarily vacuous with no active batches.
- That legacy completed batch has no recorded batch receiver. The migration explicitly avoids inventing old witnesses/events. It is historical evidence incompleteness, not proof that a new destination receipt bypassed authorization.
- New dispatch snapshots transfer compensation **once per batch**, not per parcel. Completion recognition requires completed batch, actual batch receiver/time, every received member and matching completed pickup-confirmed legs. Legacy imports are excluded from invented new earnings.
- This audit did not change shipping obligations, COD collections, seller settlements, rider initial-assignment limits, barangay routes, commission ledgers or financial configuration. Final sorted parcels still need eligible delivery riders/coverage; sorted state does not waive those rules.

## Recommended fix order

1. Fix the province-aware route-builder identity check and cover actual generated routes.
2. Align transfer rider picker/dispatch availability and eligibility checks, preserving four-wheel/exclusive-work rules.
3. Correct partial-receipt activity IDs and prevent old rejected/cancelled batches from borrowing later pickup evidence.
4. Define/guard current-hub custody evidence for legacy/inconsistent records with explicit review rather than fabricated backfill.
5. Complete scoped notification/deep-link behavior and retry-safe discrepancy reporting.
6. Address separately agreed UX, personal vehicle snapshot and large-batch requirements without redesigning the existing workflow.

**No fixes have been implemented.**
