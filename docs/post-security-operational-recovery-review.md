# Post-security verification and operational recovery review

Follow-up: the subsequently approved A08/A09/A05 implementation and its verification are recorded in [Logistics operational recovery package](logistics-operational-recovery-package.md). The original review below remains the pre-implementation assessment.

29 October 2026. Review only. A05, A08 and A09 have **not** been implemented. No application code, role permissions, account status, business record, assignment, document reference, financial history or schema was changed during this review. Outputs are this report and private verification artifacts. Automated tests create temporary fixtures and the MySQL runner creates/removes its own isolated schema.

## Outcome

- A01/A02 authorization is present in the current code and passes fresh regression and MySQL checks. Admin cannot attest physical hub arrival; normal delivery requires the currently approved assigned rider.
- Local sensitive-document migration remains complete for the discovered public files: zero files in the known sensitive public directories, and no referenced sensitive path still public. Ten pre-existing missing files remain; they have not been deleted, replaced or invented.
- Missing documents belong to five accounts, **not five riders**: an approved seller, three pending buyers and a pending Logistics Admin. Each lacks its ID and selfie. References also exist in the October 28 dump; no matching basename was found in the project tree searched.
- The public domain cannot be certified from this review: all public HEAD probes returned 403 with a Cloudflare server header. Those responses do not establish which application/storage origin is active or whether every old document URL was removed.
- The owner plans to push the repo and have groupmates pull/run it for presentation. **Each presentation machine is another deployment to verify.** Git does not distribute private documents, the database, `.env` or each machine's migration state.
- A05 needs a narrow supervised recovery design, not a suspension bypass. A08 needs consistent operational completion classification. A09 needs verified absence of physical pickup, not just a rider-ID edit.

## 1. Fresh A01–A03 verification

| Control | Current source and observed result | Boundary |
| --- | --- | --- |
| Origin hub receipt | `LogisticsAccess::receivingStaff` and `LogisticsController::receiveAtSortingCenter`: fresh locked user, approved Hub Staff, operational assigned company hub and matching receiving location. Admin denied. | No substitute-Staff or Admin receipt exception is enabled. |
| Transfer receipt | `HubTransferWorkflow::receive`: actual destination hub, exact assigned hub ID, approved Staff, dispatch/rider-pickup/member evidence and current locks. Legacy scan completion delegates to the same service. | Admin authorizes/monitors/reviews discrepancies but cannot receive. Partial receipt does not complete/earn the whole batch. |
| Final delivery | `LogisticsController::updateStatus` rejects Delivered with 403; offered generic transitions omit it. `RiderController::advance` rechecks current rider role/status/company and a matching active assignment. | Buyer completion remains separate. A stale authenticated instance cannot override a committed rider suspension on this path. |
| COD delivery and earnings | Exact collection, handover confirmation, shipment/assignment timestamps, order history and compensation remain atomic. | Cash collection is not Hub Staff verification, seller settlement or payroll funding. |
| Private document access | `SensitiveDocuments`, private disk, authenticated controller/route, current owner/company/role checks, encrypted URLs and private/no-store responses. Sensitive User file attributes are hidden from normal JSON. | Copied URLs do not grant access. Approved Platform Admin review remains; Hub Staff/riders cannot browse colleagues. |
| Document lifecycle | Registration/update upload routing, referenced-file protection, rollback cleanup and review components pass. | Missing references fail closed. Authenticated public fallback supports deployment staging but does not secure an old static public copy. |

### Fresh tests

**319 tests / 4,045 assertions passed**, zero failures/errors in the selected run. Result: `storage/app/private/logistics-ui-review/post-security-verification.xml`.

Selected suites: SensitiveDocuments, RiderCompensation, HubTransferWorkflow, CodCollection, LogisticsAuthorization, LogisticsReconciliation, LogisticsShipping, LogisticsCashDesk, CodSellerPayout, GcashPurchase, FulfillmentWorkflow, MediaLifecycle, Navigation, LogisticsFinanceFoundation and BuyerCommerce.

These include public registration with private files, document review authorization, pickup handover/origin receipt, multi-parcel transfer, assigned rider COD/GCash delivery, seller eligibility/payment, separately funded rider payroll, audit rollback and duplicate protection. `LogisticsReconciliationTest::test_complete_buyer_gcash_to_delivery_preserves_direct_seller_payment_and_company_receipt` and `test_cod_lifecycle_reservations_payouts_and_payroll_do_not_double_count_cash` exercise complete application flows using isolated fixtures.

The real multi-process MySQL runner passed again, including Admin-versus-destination-Staff physical receipt, one whole-trip earning, cash reservation/count/release/receipt, insufficient custody, GCash reference ownership, treasury receipts, seller payout/dispute, rider earning/payroll/holds/acknowledgement and a report snapshot concurrent with payout. No live collection, payment or balance was created by those checks.

This does not certify the original manual workbook, physical cash, bank/GCash transfers, or every repository test. The known hiring/rider-workbook fixture failures and device skips were not repaired or relabelled as passes. This review did not add tests or change validation to suppress failures.

## 2. The ten missing documents

| User ID | Current account | Status | Missing fields |
| --- | --- | --- | --- |
| 221 | Seller | Approved | `id_file`, `selfie_file` |
| 222 | Buyer | Pending | `id_file`, `selfie_file` |
| 223 | Logistics Admin | Pending | `id_file`, `selfie_file` |
| 229 | Buyer | Pending | `id_file`, `selfie_file` |
| 230 | Buyer | Pending | `id_file`, `selfie_file` |

All five records were created before the security package. Each exact path appears in `Dump20261028.sql`; the earlier pre-migration check already reported the ten missing sources. Neither finding proves why the files disappeared, and usernames/paths are not sufficient evidence to dismiss the accounts as test fixtures.

Read-only investigation checked current public/private stores and searched project filenames for the ten missing basenames, excluding dependency/build caches. **Zero matching basenames were found.** The SQL dump preserves references, not recoverable image/PDF bytes. External backups, OneDrive version history, differently named copies and groupmates' drives have not been verified. No document contents were downloaded from the public domain.

Recovery recommendation:

1. An authorized reviewer identifies the account and document from the private inventory; do not post identity-document names/contents to Git or public reports.
2. Look for an authorized original/backup. A same-name file alone is insufficient proof of identity/contents. Restore only an authenticated, reviewed source into private storage and preserve provenance.
3. If no original can be verified, use the applicable authorized document-update/review process for a genuine replacement. Pending accounts without a completion path remain a manual onboarding issue; do not approve them by inventing paths or inserting placeholder files.
4. Do not automatically delete accounts/references, change account status, rewrite approval history or impose financial holds because of this inventory.

Private artifacts: `critical-document-migration.json` identifies user/field/path hashes; `post-security-missing-file-search.json` records zero project matches. The report intentionally omits personal names, document filenames and images.

## 3. Deployment and presentation readiness

| Environment | Evidence obtained now | Verdict |
| --- | --- | --- |
| Current workstation / configured database and filesystem | `APP_ENV=local`; configured URL is `https://pocketfindsph.shop`; private root is outside public root; zero sensitive public-directory files; ten missing referenced paths in both stores. | Local migration protection verified for discovered paths; missing-file review remains. |
| Running local origin `127.0.0.1:8000` | PHP development server is running. HEAD to the private route redirects unauthenticated access to `/login`; a sampled old public document URL returns 403 without downloading content. | Consistent with access protection; filesystem checks provide stronger local migration evidence than a single HTTP probe. |
| Public `pocketfindsph.shop` | Cloudflared process is running. A historical ingress snapshot points the domain to port 8000. Home, private-route and sampled old-public-URL HEAD requests each return 403 with `Server: cloudflare`. | **Unverified application/storage deployment.** Cannot infer current ingress, release version, storage mapping, cache purge or complete migration from these responses. No assumption that local equals production. |
| Groupmates' presentation machines | Owner confirmed future repo pull/run. No machine/storage/database access or per-machine migration evidence is available yet. | **Not yet verified.** Each machine must pass its own gate below. |
| Any other hosted/staging environment | None identified/accessible during this review. | Not certified. |

The public 403 may come from an edge/access rule or a proxied origin response; HEAD results do not distinguish these. No Cloudflare configuration or access rule was changed to get around it.

### Per-machine gate before presentation

1. Pull the intended complete code revision, including new private-document services/controller, views/config/routes and additive finance migrations. Merely pushing tracked changes while omitting required new files will not reproduce this workstation.
2. Configure that machine's own `.env`, MySQL connection and writable private storage. `.env`, private documents, tunnel credentials and private verification files are currently ignored by Git. Database dumps and identity files are not code assets to add to a public repo.
3. Use an authorized database/data transfer or isolated presentation dataset. Apply the existing additive migrations with `php artisan migrate`; do not use reset/fresh operations against a valued database or fabricate historical paid/verified records.
4. If transferring existing documents is authorized, transfer them privately, retain their relative references and verify integrity. Pulling Git or importing a SQL dump does **not** transfer the actual files. New registrations will store new documents privately; they do not repair old missing references.
5. Drain old upload workers, run `php artisan documents:privatize` and then `--apply` where public legacy copies exist. Review missing/conflicting files. Verify zero remaining public candidates and ensure private storage has no public alias/symlink. Missing-reference exit status must not be mistaken for a successful document recovery.
6. On that origin, check guest/foreign-company/Hub Staff document denials, authorized document review, Admin receipt/delivery denials and successful assigned Staff/rider actions. Confirm the intended database/host is being demonstrated, not another tunnel or stale checkout.
7. If a public tunnel/cache is used, verify actual ingress and purge any cached old public document responses. Keep the original verified private copies; do not commit them to solve a deployment problem.

This is a recommended verification gate, not a claim that groupmates have completed it. A03 cannot be certified for machines that have not been inspected.

## 4. A05 — Suspended rider recovery

### Actual behavior

`LogisticsController::suspendRider` changes status; `User::save`/`HubHiring::persist` records the transition and capacity/state safeguards. It does not return parcels, remove assignments, transfer COD or deduct earnings. Rider-specific decision reasons remain the separate A07 gap.

`RiderMiddleware` logs out a non-approved rider and blocks the entire Rider route group. `LogisticsCashDesk::actor` independently requires approved status, so bypassing only the middleware would not allow suspended remittance submission. Earnings destination/acknowledgement access is also unavailable through the suspended rider session.

| Situation at suspension | Existing safe path | Gap / recommendation |
| --- | --- | --- |
| Assigned but truly uncollected pickup/delivery | Future claims by an inactive rider are excluded; Admin can inspect company work. | No controlled owner replacement through normal claim endpoints. Use the proposed A09 gate, not direct DB edits. |
| Both pickup handover confirmations already recorded; parcel physically arrives at origin hub | Assigned approved origin Staff can use normal receipt even when the old rider is suspended. Completion evidence can still earn that rider's original pickup rate. | Reuse this path. Suspension is not evidence that prior completed work was unpaid or forfeited. |
| Transfer dispatched and rider pickup already confirmed; real parcels arrive at planned destination | Destination Staff can receive through the normal batch flow. Old rider approval is not required for Staff's physical receipt. | Reuse it. Whole-batch earning still requires all members; no per-parcel trip pay. |
| Rider confirmed pickup but seller has not yet confirmed | Seller may truthfully confirm a handover that actually occurred; the rider confirmation timestamp is already present. | Ready-for-pickup status is not proof of no custody. Do not erase the first confirmation. A disputed/unconfirmed handover needs investigation. |
| Dispatched transfer lacks rider pickup confirmation | Normal destination receipt remains blocked. | No Admin/Staff impersonation of rider pickup. Use a proposed recovery/exception record; do not fake the missing step. |
| Rider holds final-delivery parcels or a failed delivery needs return to hub | Parcel issue/discrepancy logging exists; Admin sees company records. | No complete physical return-to-hub/recovery workflow. Direct Returned would invoke RTS/restocking (A04) and is not an acceptable shortcut. |
| Remittance was submitted before suspension | Assigned Staff can count it using `LogisticsCashDesk::count`. That method checks the receiving Staff, not the original rider's continued approval. | Reuse exact allocation/shortage/overage/custody protections; do not require reactivation to count an existing submission. |
| COD was recorded but not submitted, or a prior shortage leaves an outstanding balance | Collection and outstanding amount remain visible; no automatic penalty/offset. | Staff cannot create a new remittance on behalf of the suspended rider. A narrowly scoped recovery submission is necessary if this must be recorded while suspension remains. |
| Earned compensation / payout | Earned snapshots remain. Admin payout preparation/payment is based on earned source, recipient destination and verified payroll funding, not automatic current rider approval. | Existing recipient/payment rules still apply. Rider cannot add a destination or acknowledge through a suspended session. Keep Paid with pending acknowledgement; do not erase earnings or automatically hold them due to COD. |

Source-only race concern: pickup claim/confirmation paths use the authenticated rider instance without the same explicit fresh approved-actor check now present in `advance`. A request authenticated before suspension may be stale at mutation time. Existing locks help serialize resources but are not a substitute for rechecking current permission. This race was not newly reproduced in this review; proposed implementation must add negative/concurrent coverage before claiming it fixed. Transfer confirmation and Cash Desk already use fresh service actor checks.

### Proposed supervised workflow — approval required

1. Admin suspends the rider without deleting or rewriting work. Show a read-only recovery summary in existing Rider account detail: uncollected assignments, possible/confirmed parcel custody, pending remittances, outstanding COD and earned/paid compensation. Suspension should not be prevented just because custody exists.
2. Use the existing parcel Issues/transfer discrepancy surfaces for a documented recovery case linked to rider, parcel/batch and company/hub. Record what is known and what is unknown. Do not claim a current physical location from a rider-ID field alone.
3. Prefer the existing normal receipt/count paths in the table above whenever evidence already satisfies them. Admin arranges recovery; actual assigned Hub Staff receives/counts. Admin never signs a physical handover as Staff.
4. For an otherwise blocked parcel return, propose a dedicated **recovery custody event** on the existing issue/transfer history, with real receiving Staff, hub, parcel/member list, actual observation/recording times, condition and evidence/reference. Do not set Delivered, Returned, buyer confirmation or a successful delivery timestamp. Recovery is not completed delivery and does not itself earn a new pickup/transfer/delivery fee.
5. For unsubmitted recorded COD, propose an assigned-Staff-only **on-behalf recovery submission** in the existing Cash Desk. Select only that suspended rider's outstanding recorded collections for their recorded receiving hub; respect all active reservations and available amounts. Record the rider as cash holder and the Staff as the actual submitter; do not impersonate a rider login. Count/allocate through the existing explicit physical count action. No verified balance or custody credit merely from creating the submission.
6. A minimal additive `submitted_by` attribution field on existing cash handovers may be needed so recovery records distinguish cash owner from acting submitter. Existing records remain unchanged. Existing finance audit events record the real Staff actor and recovery reason/reference. This schema/authority addition is proposed, not approved or implemented.
7. Staff count preserves partial outstanding balances and shortages/overages; Admin investigates discrepancies using existing tools. Different-hub receipts, cash not recorded as a COD collection and disputed physical possession remain exceptions. Do not relabel collection hubs, invent collections/Delivered records, forgive balances or cross-fund payroll.
8. Recovery stops at confirmed hub custody/accounting. Subsequent redispatch of previously carried work needs an approved attempt/source/compensation treatment; it is not ordinary pre-pickup reassignment. Do not pay recovery transport or two riders automatically from one final-delivery entitlement.

Recommended default: keep suspended riders blocked from operational routes; let authorized Staff perform supervised recovery. Limited suspended self-service for remittance, earnings viewing or acknowledgement is an **optional alternative requiring approval**, not a reason to broadly relax `RiderMiddleware`. Ordinary reactivation restores normal operational access and is not a safe recovery-only shortcut.

## 5. A08 — Assignment completion consistency

### Current states and evidence

| Leg | Current operational write | Authoritative evidence / inconsistency |
| --- | --- | --- |
| Pickup | Assignment created as Accepted. Seller/rider confirmations move the shipment to Picked Up. Origin Staff receipt moves it to Sorting/Sorted. Assignment can remain Accepted. | `RiderCompensation::completion` correctly requires matching original rider, both handover confirmations, pickup and origin receipt timestamps. Resource conflict logic does not consistently recognize that completed work. |
| Final delivery | Assignment follows Assigned / Out for Delivery / Delivered. Buyer may later complete the order/shipment. | Delivered with matching assignment/shipment timestamp is operationally terminal for the rider. Buyer confirmation, COD cash verification and payout eligibility are separate. Transfer dispatch currently fails to exclude Delivered. |
| Hub transfer | Dispatch sets per-parcel assignments to Hub Transfer; each receipt completes that member's leg/assignment. The batch remains In Transit until all members arrive. | Batch/leg/member receipt evidence is authoritative. Per-parcel Completed is not proof the entire trip is finished. Existing batch conflict guard and one batch earning are correct. |

`HubTransferWorkflow::dispatch` rejects any delivery assignment not in Completed/Cancelled/Rejected. Consequently it treats Delivered and evidence-complete Accepted pickups as active. A previously completed two-wheel job can block a later legitimate four-wheel transfer after an allowed vehicle change.

Read-only local data now contains **5 shipments / 7 assignments**. One legacy pickup has both confirmations and pickup/origin-receipt timestamps yet remains Accepted; two delivery assignments are Delivered with shipment delivery timestamps. There are zero suspended riders, zero assignments owned by suspended riders, zero COD ledger collections/cash handovers and zero compensation snapshots in this local business dataset. This confirms an operational inconsistency; it does not prove a current four-wheel dispatch incident or authorize financial backfill. Zero ledger records do not establish real-world payment/cash facts.

### Minimal proposed correction

1. Agree one shared operational classification for ongoing, completed and ambiguous work; use it in dispatch conflict checks and workload/recovery summaries. Do not equate every status other than Completed with ongoing work.
2. Prospectively close a pickup assignment as Completed within the existing hub receipt transaction **only when its matching handover/receipt evidence is complete**. Preserve the source assignment ID/rider and all compensation snapshot values. A later return does not undo completed pickup work.
3. Treat matching Delivered delivery work as terminal for rider availability; retain the existing Delivered state rather than mass-renaming it. Do not wait for buyer confirmation, remittance or seller payout to free an operational rider. Financial earning checks still enforce their own COD/source evidence, including legacy exceptions.
4. Keep transfer availability based on the actual batch. All members must be received before the trip is complete/earned. A parcel may proceed to another hub leg without making the previous entire batch complete prematurely.
5. For older records, show proposed normalization candidates and evidence gaps first. Only explicitly reviewed, unambiguous operational corrections may be applied with before/after actor/reason history. Missing/contradictory evidence remains reviewable; do not infer completion from an order's terminal label alone or automatically unblock unknown custody.
6. Operational correction does not automatically create financial earnings, payments or current-rate snapshots. Existing Phase 6 audited recognition remains the separate approved path where a valid original snapshot and completion evidence exist. Keep unique earned-source protection and transaction rollback on audit failure.

No new state vocabulary, compensation rate, payroll offset or wholesale migration is needed for the prospective predicate/receipt correction. An optional reviewed legacy normalization action must be explicit, additive in audit history and approved before running it.

## 6. A09 — Safe pre-pickup reassignment

### Existing limitations

- `assignPickupRider` requires approved pickup and a null pickup rider. `assignCourier` requires Sorted. Neither is a safe generic replacement endpoint for an already assigned parcel.
- Self-claim endpoints use conditional first-winner updates; they intentionally cannot overwrite another rider.
- The first rider pickup confirmation represents actual receipt even while shipment status is still Ready for Pickup. Either handover timestamp, any pickup timestamp or a contradictory timeline must block ordinary reassignment.
- Final-delivery assignment does not have a distinct reliable hub-to-rider release record. Assigned to Rider alone is not proof the parcel is still physically uncollected.
- Transfer dispatch immediately creates In Transit batch/leg state, reserves a vehicle where applicable and snapshots the trip. An unconfirmed rider pickup is not a safe reason to reset or replace a dispatched batch. Dispatched-batch recovery/reassignment requires its own explicit proposal.

### Recommended initial scope and exact steps

Scope: seller-to-origin pickup assignments and destination-hub final delivery **before physical collection**. Company transfer rider selection remains as implemented before dispatch; do not add dispatched-batch reassignment to this minimal package.

1. Existing company Admin or relevant origin/destination Hub Staff opens Reassign in the existing assignment/account/scan detail. Require reason, expected old rider and a revision token. Preserve the current same-company and relevant-hub write scopes.
2. Pickup gate: pickup approved, original GCash shipping gate still satisfied where applicable, Ready for Pickup, old rider matches, **no** rider/seller pickup confirmation, pickup timestamp, subsequent receipt/leg evidence or completed/earned source. Obtain an explicit seller assertion that the parcel remains in their possession; never reuse Seller Confirm Pickup, which means the opposite.
3. Final-delivery gate: assigned but not Out for Delivery, no collection/delivery/pickup evidence and no earned source. Require destination Hub Staff to attest that the parcel remains at the hub and has not been handed to the old rider. An Admin reason alone is insufficient. Any uncertainty/known prior release goes to A05 recovery, not this gate.
4. The replacement must be approved, same company and actual required operational hub, with the existing two-wheel/ownership/available-company-vehicle eligibility. Recheck current rider status and all source evidence under `GcashLocks::operation`, with users/hubs/orders/assignments locked in the established order.
5. In one transaction update the existing current assignment/owner mapping, retain the assignment ID and preserve an append-only before/after record including old/new rider, evidence, actor, reason and prior timestamps. Never delete the old work record or clear handover evidence to make it eligible. Add a monotonic assignment revision if needed: expected rider alone does not protect a stale A-to-B-to-A submission.
6. Reuse `LogisticsFinance::snapshotAssignment`: only prior unearned Pending compensation is cancelled with audit, new rider snapshots use the existing approved prospective-rate behavior, and an earned source blocks financial reassignment. If a previously assigned rider returns to the same source, the existing service restores their original cancelled snapshot/rate; do not silently reprice that case. Snapshot amounts/revisions and all existing earned/paid records remain unchanged.
7. Append timeline and old/new rider/seller or hub notifications after commit. The old rider loses normal access to this uncollected job; the new rider uses the unchanged genuine pickup/delivery workflow. Reassignment itself records no physical pickup or successful delivery.
8. Race outcomes: if pickup confirmation, suspension, cancellation or an evidence/owner change commits first, reassignment fails without partial state. If reassignment commits first, the old rider's stale claim/confirmation must fail its fresh owner/status check. Duplicate/replayed keys must not create multiple snapshots/events/notifications.

Preserving one current assignment row fits the existing update-or-create architecture, but complete owner-change history must be append-only and visible. Do not overwrite the old rider identity without that history. If stronger audit details/revision storage is absent, propose an additive field on existing records/logs rather than silently deleting/recreating assignments or adding a parallel dispatch module.

## 7. Necessity and compatibility

| Classification | Recommendation |
| --- | --- |
| Necessary | A08 shared completion semantics and fresh suspension/owner checks on relevant pickup mutation boundaries. |
| Necessary | A09 controlled pre-collection replacement with custody evidence, scoped eligibility, version checks and preserved assignment/compensation history. |
| Necessary when suspended unsubmitted cash must be processed | A05 assigned-Staff recovery submission/count with true actor attribution and unchanged cash liability. Existing pre-submitted cash already has a count path. |
| Necessary when normal parcel receipt is blocked | Explicit recovery custody/evidence record; do not fabricate a missing normal handover. |
| Optional | Recovery-only suspended self-service; reviewed legacy normalization UI; additional recovery notifications and read-only summaries beyond existing account/Issues/Cash Desk surfaces. |
| Incompatible | Admin physical receipt/delivery, Staff impersonating rider pickup, broad suspended operational access, changing rider IDs to transfer custody, resetting dispatched batches, Returned as a generic recovery shortcut. |
| Incompatible | New Finance roles, GPS dispatch, fixed job limits, penalties/deductions/offsets, automatic wage withholding, recovery pay or changed trip/rate policies without approval. |

## 8. Proposed affected files, implementation order and regression risks

All following edits are **proposed only**.

| Order | Work / likely files | Main regression risks and required checks |
| --- | --- | --- |
| 1 | A08: `DeliveryAssignment`, `LogisticsAccess` or a small shared completion classifier; `HubTransferWorkflow::dispatch`; `LogisticsController::receiveAtSortingCenter`; relevant workload queries. Keep `RiderCompensation` as the financial authority. | Accepted is not universally complete; Delivered is terminal for availability; partial batches still reserve trip resources; legacy/COD gaps cannot create earnings. Test vehicle changes, later returns, missing evidence and duplicate recognition. |
| 2 | A09: existing assignment controller/routes/views; `RiderController` pickup/claim mutation guards; `SellerController` narrowly scoped still-in-possession assertion; `Shipment`, `DeliveryAssignment`, `LogisticsFinance`, existing activity/financial audit. Additive revision/detail storage only if approved. | Reassignment versus both handover confirmations, old rider stale requests, cancelled orders, shipping gate, wrong hub/company/vehicle, restored old-rate snapshot and audit failure rollback. No new transfer leg or payment from an ID change. |
| 3 | A05: `LogisticsController` rider/account summary; `LogisticsManagementController` Issues; `HubTransferWorkflow` recovery event where needed; `LogisticsCashDesk`/controller/view; existing finance audit and private evidence lifecycle. Optional additive submitter attribution. | Physical receiver must be Staff; submitted reservations cannot be allocated twice; partial/short/over counts preserve liability/custody; original rider debt persists; no payroll offset; no fake normal arrival/delivery/RTS. |
| 4 | Extend `LogisticsAuthorizationTest`, `HubTransferWorkflowTest`, `RiderCompensationTest`, `LogisticsCashDeskTest`, `FulfillmentWorkflowTest`, `LogisticsFinanceFoundationTest`, `LogisticsReconciliationTest` and `tests/Support/cash-desk-mysql.php`. | Real MySQL races: suspension versus claim/pickup; reassign versus rider/seller confirmation; A-to-B-to-A staleness; two replacement requests; count versus recovery submission; recognition versus normalization; full rollback on audit failure. Retain A01–A03 negative tests and finance totals. |

No broad middleware relaxation is recommended. Preserve one company Admin, assigned Hub Staff and assigned Riders. Existing payroll/seller/treasury permissions remain unchanged.

## 9. Decisions requiring approval

1. **A08:** approve prospective pickup closure plus a shared availability predicate, with older candidates reviewed separately and no automatic earning/backfill.
2. **A09 authority/evidence:** approve existing Admin/relevant Hub Staff initiating replacement, seller still-in-possession confirmation for pickup and mandatory destination-Staff no-release/physical-hub attestation for delivery. Approve the minimal revision/history storage if required. Exclude dispatched batches and already-carried parcels from initial scope.
3. **A05 suspended access:** approve the recommended Staff-supervised design while keeping the rider suspended, or explicitly choose a separately scoped recovery-only rider surface. No general reactivation/bypass.
4. **A05 cash attribution:** approve Staff preparing an on-behalf recovery submission for recorded COD at the recorded hub, followed by existing counting/verification, with real submitter attribution. Decide whether the same assigned Staff may submit and physically count that recovery entry; the proposal does not silently impose or remove dual control.
5. **A05 physical recovery evidence:** approve mandatory parcel/member scan, actual Staff/hub, observation/recording times, condition and private evidence/reference. Missing normal pickup evidence must remain distinguishable from a successful normal handover.
6. **Keep deferred:** redispatch after carried-work recovery, permanently unavailable-rider delivery correction, recovery transport compensation, new payout access/destination rules for suspended recipients and any dispatched-batch reassignment need separate approval. Do not infer these from approval of ordinary pre-pickup replacement.

Missing-document recovery needs an authorized original/re-upload source, not permission to manufacture replacements. Presentation deployments need per-machine evidence before they can be certified. This review stops at the plan; no A05/A08/A09 implementation, structural migration or financial rule change has begun.
