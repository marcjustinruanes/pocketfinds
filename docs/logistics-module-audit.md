# Logistics Admin and Hub Staff module audit

Review date: 29 October 2026. Analysis only. No application code, database records, permissions, financial rules or workbook results were changed. This document and a private test-result artifact are the review outputs.

## Scope and evidence

Reviewed the current Laravel routes, middleware, controllers, services, models, additive finance migrations and Blade workspaces against:

- `C:/Users/LENOVO/Downloads/Blackbox_Test_Cases_Logistics_Modules-1.xlsx`: Summary, Assumptions, Test Data and all 44 cases across L1–L8. Every workbook case is currently marked **Not Run**. Its expected results include assumptions, not necessarily approved PocketFinds requirements.
- `docs/logistics-finance-implementation.md` and `docs/phase7-financial-review.md`, checked against their implementation rather than accepted as proof by themselves.
- Existing authorization, transfer, navigation, hiring, rider-workbook, reconciliation and compensation tests, rerun during this review using isolated SQLite fixtures.

This is a source and automated-test audit, not execution of the workbook on physical devices, a bank audit, a penetration test or a fresh browser visual acceptance run. No live financial transaction or identity-document download was attempted. Previously reported Phase 7 MySQL races and browser checks remain prior evidence; they were not rerun here.

### Classification

1. **Implemented and working**: matching code and applicable automated evidence; does not mean the original workbook was manually passed.
2. **Implemented but needs improvement**: an existing capability has a defect, incomplete acceptance criteria or confusing behavior.
3. **Missing and necessary**: a workflow/control needed for safe operation is absent. Exact structural or financial policy still requires approval.
4. **Not applicable**: contradicts approved policy or assumes an unapproved operating model. Its absence is not automatically a defect.
5. **Redundant**: covered by an existing authoritative workflow; prefer links/reuse over another page or ledger.

Mixed workbook cases are split explicitly. A case with an unsupported detail is not reported as a full pass.

## Principal findings and priorities

| ID | Priority / severity | Finding and consequence | Evidence / recommendation |
| --- | --- | --- | --- |
| A01 | Critical / High | Company Admin can attest physical origin-hub and transfer-batch receipt. The location check restricts Hub Staff, but deliberately exempts Admin. A receipt can also trigger pickup or whole-trip earnings. This conflicts with the requested separation of oversight from physical handovers. | `LogisticsController::receiveAtSortingCenter`, `HubTransferWorkflow::receive`, `LogisticsAccess::requireLocation`, shared receive routes in `routes/web.php`. Require the assigned receiving hub's approved Hub Staff to attest physical receipt. Preserve Admin visibility, authorization and exception review. Design emergency exceptions explicitly; do not silently let Admin impersonate staff. |
| A02 | Critical / High | The shared status endpoint lets Admin, or destination Hub Staff, mark a parcel Delivered without required reason, recipient evidence or rider attestation. It synchronizes delivery-assignment completion and can earn compensation. GCash delivery has no COD collection gate; COD still correctly requires an already recorded exact collection. | `LogisticsController::updateStatus`, `STATUS_TRANSITIONS`, `RiderCompensation::shipmentCompleted`. Restrict physical final-delivery attestation to the assigned rider; design an audited exception process separately. Do not infer that this bypasses COD cash verification: it does not. |
| A03 | Critical / High | Identity, selfie, licence and vehicle documents use public storage URLs. The standard `public/storage` symbolic link exists locally. These links are served outside participant/company authorization when public storage is exposed. Scoped document thumbnails do not protect the underlying URL. | `RegisterController::storeWithRetry` defaults to public disk; `config/filesystems.php`; `components/admin-doc-thumb.blade.php` uses `Storage::url`. Move sensitive document delivery to private, authorized endpoints and plan migration of existing files/links. File names are not an access-control boundary. No live sensitive file was fetched in this review. Finance proofs already have separate private access controls. |
| A04 | Important / High | RTS is a status shortcut, not a custody workflow: `delivery_failed -> returned` immediately restores stock and tells the seller goods are back. There is no return assignment, return transit, seller physical acknowledgement or prepaid RTS refund initiation on that path. | `LogisticsController::updateStatus`, `Order::restoreStock`; contrast buyer return receipt in `CommerceController::returnAction`. Approve a return-to-seller state/receipt design before changing stock or refunds. Keep buyer returns and delivery-failure RTS distinct, but reuse common evidence/custody concepts. |
| A05 | Important / High | Suspending a rider blocks all rider routes, including in-custody parcel work and Cash Remittances. Suspension does not reconcile active assignments or hand over cash/parcels. Balances are not deducted, but the rider cannot complete the normal remittance UI. | `LogisticsController::suspendRider`, `RiderMiddleware`, rider route group. Add a supervised custody-recovery process and define limited suspended-account access if approved. Never automatically erase assignments, balances or earnings. |
| A06 | Important / Medium | Admin-created rider and Hub Staff accounts omit identity/document completion fields. Approval is **not** freely bypassable: `User::save()` calls `HubHiring::persist/validateTransition`, which enforces approval documents and staff interview requirements. The created account can instead become stuck pending with no managed document-completion flow. | `LogisticsManagementController::accountData/storeAccount/updateAccount`, account form partial, `User::save`, `HubHiring::validateTransition`. Reuse public application/document completion or add a properly authorized completion invitation. Do not remove approval checks to make account creation appear successful. |
| A07 | Important / Medium | Rider rejection and suspension accept no documented decision reason. Hiring history exists, but the rider transition rules do not require a reason, unlike Hub Staff. Roster forms do not collect one. | `LogisticsController::rejectRider/suspendRider`, `HubHiring::validateTransition`, `logistics/riders.blade.php`. Require and audit reasons consistently; notify the rider without exposing internal notes. The workbook's exact ten-character threshold is an assumption needing agreement. |
| A08 | Important / Medium | Assignment terminal-state definitions disagree. Pickup completion can leave an assignment `accepted`; final delivery uses `delivered`. Transfer dispatch treats every status except completed/cancelled/rejected as active. Old successful assignments can therefore block a later eligible transfer rider, for example after an allowed vehicle-role change. | `RiderCompensation::completion`, `receiveAtSortingCenter`, `RiderController` delivery, `HubTransferWorkflow::dispatch`. Use one authoritative active-work predicate including completed shipment/leg evidence; do not rewrite earned snapshots or call all accepted assignments complete. This is a source-traced conditional defect, not a reproduced production incident. |
| A09 | Important / Medium | No safe pre-pickup rider reassignment/recovery workflow. Pickup assignment requires a null rider; delivery assignment requires Sorted. A suspended or unavailable rider can strand an already assigned parcel. Post-pickup overwriting is correctly blocked. | `LogisticsController::assignPickupRider/assignCourier`, rider FCFS claims. Approve audited unpicked reassignment with old/new notifications and cancellation of only unearned snapshots. Physical custody changes require a real handover. |
| A10 | Important / Medium | Lost/damaged issue logging is available but lacks parcel evidence uploads, explicit resolution outcomes, customer notifications and linkage to claim/refund review. A free-text issue is not a completed loss/claim workflow. | `LogisticsManagementController::storeIssue/updateIssue`, `LogisticsIssue`, issues view. Add evidence and controlled exception escalation; financial liability/refund rules require approval. Do not add arbitrary status overrides. |
| A11 | Important / Medium | One Admin per company is an application convention, not a clearly enforced database ownership invariant. Company identity is a shared business-name string. Founder validation checks name uniqueness before creation, but the registration identity mutex does not include a company-name ownership key; no company-owner unique constraint was found. | `RegisterController` business-name rule, `HubHiring::register`, `LogisticsCompany`, migrations. Concurrent distinct founder applications for the same name need an isolated race test and a canonical single-owner safeguard. This review did not create competing live accounts or prove an exploit. Any structural ownership change requires approval. |
| A12 | Important / Medium | General operational audit records are thinner than financial audit records. Route/actor/record/time logging does not consistently preserve old/new state and required decision reasons. Operational delivery CSV lacks the explicit private/no-store headers and export audit used by Finance CSV. | `LogisticsActivity`, `LogisticsController::updateStatus/exportReport`, `LogisticsManagementController::activity`, reconciliation export controller. Reuse finance export privacy/audit patterns and structured operation events. Existing transactions roll back on audit failure; do not remove that protection. |
| A13 | Important / Medium | Managed account password validation is only minimum length/confirmation, whereas public registration requires the stronger symbol-containing password policy. Multi-word names are rejected by both paths' letter/hyphen regex. These are actual policy/usability inconsistencies, not solved by weakening registration. | `LogisticsManagementController::accountData`, `RegisterController` validation. Align managed password rules upward after review; agree a realistic person-name policy. Tests must exercise the chosen policy explicitly. |
| A14 | Optional / Low | Dashboard cards link to broad monitor lists instead of matching filtered populations; no dashboard period selector or rider online/workload roster exists. Literal question-mark separators appear in dashboard copy. Dense operational screens have different status vocabulary from the workbook. | `LogisticsController::dashboard/monitor`, `logistics/dashboard.blade.php`, `riders.blade.php`. Clarify metric definitions and filtered links; reuse report date filters; fix copy and add actionable workload counts before introducing another dashboard. |
| A15 | Optional / Medium at scale | Reconciliation loads all-history company sources into one consistent snapshot. Export/activity bounds do not bound all balance computation. No current bank/provider balance, historical closing balance or production-load guarantee exists. | `LogisticsReconciliation`, Phase 7 review. Load test and optimize aggregation without replacing source ledgers or weakening snapshot consistency. Provider integration, reversals and closing periods remain separately approved work. |

Severity reflects impact, not proof that an incident occurred. Critical means prioritize before relying on the affected security/custody boundary; no emergency production edits were made.

## Role separation and account structure

| Responsibility | Logistics Admin | Assigned Hub Staff | Rider |
| --- | --- | --- | --- |
| Company configuration, approved rates, intake and fleet oversight | Existing company-wide authority | No company-management access | None |
| Transfer request and parcel issue reporting | Oversight/request capability | Own-hub operational capability | Assigned-work reporting |
| Approve and dispatch transfer batches | Admin | Cannot approve/dispatch | Confirm own pickup |
| Physical hub parcel receipt | Currently allowed: A01 | Own receiving hub | Cannot substitute for receiving staff |
| Physical final delivery | Shared override currently allowed: A02 | Destination override currently allowed: A02 | Normal assigned-rider completion |
| Count rider remittance / receive hub cash | Must not impersonate staff; service rejects it | Own hub only | Submit own collections, not verify them |
| Hub cash transfer | Authorize; reconcile exceptions | Origin release / destination actual count | No authority |
| Treasury receipt, seller payout, rider payroll | Verify actual company receipts and record actual payments | Release authorized hub cash; cannot verify treasury receipt/pay recipients | View own earnings/payments; acknowledge receipt |
| Financial reports and exports | Company scoped | Own-hub Cash Desk only | Own records only |

The finance role split is substantially stronger than the parcel override split. Do not introduce Finance Officer/Manager accounts or require a second Admin: the approved same-person preparation/verification policy remains. Company-wide Admin authority must not be interpreted as physical-presence evidence.

Hub Staff shipment visibility intentionally includes parcels whose route touches their hub, even after departure; current operational writes additionally check the relevant origin/destination/current leg. This is route-related visibility, not unrestricted company access. Review whether former-hub read access should be narrowed, but do not classify it as an unrelated-hub mutation bypass without evidence.

## L1 — Dashboard

Implementation: `LogisticsController::dashboard/hubStaffDashboard/sidebarCounts`, `LogisticsAccess::shipments`, dashboard and sidebar Blade views. Company and hub scoping exists. Admin sees current-state summaries; Hub Staff sees own-hub workload/arrivals. Active includes a wider set than the internal in-transit bucket; failed counts are current failures, not all historical attempts.

| Workbook case | Classification | Current behavior and recommendation |
| --- | --- | --- |
| LOG-DASH-001 | 2 | Counts use defined current shipment states, but card links do not guarantee a correspondingly filtered list. Workbook ReadyForPickup/Unassigned names differ. Add explicit metric definitions and matching filter links; A14. |
| LOG-DASH-002 | 2 | Delivery moves current active/in-progress and completed totals. Workbook's in-transit bucket is different: code excludes Out for Delivery from that narrower bucket. Agree names/populations and test that invariant rather than declaring its arithmetic wrong. |
| LOG-DASH-003 | 3 for dashboard filter; 5 for another independent reporting filter | Dashboard has no date selector. History/Reports already support inclusive created-date filters and Finance supports Manila activity dates. Prefer links/reuse; do not compare current balance cards to period activity totals. |
| LOG-DASH-004 | 1 | Company query scope and Hub Staff hub constraints are present; authorization tests pass. No cross-company dashboard permission was added. |

## L2 — Rider management

Implementation: company-owned personnel routes, `LogisticsController::riders/approveRider/rejectRider/suspendRider/activateRider`, management account endpoints, `User::save`, `HubHiring`, `RiderMiddleware`, rider/staff/account views. Staff recruitment already has interview, reasons, capacity limits, revisions and history; rider decisions are simpler. Public application and Admin-managed account creation are different flows and should not be treated as equivalent document onboarding.

| Workbook case | Classification | Current behavior and recommendation |
| --- | --- | --- |
| LOG-RIDER-001 | 1 for approval safeguards/history; 2 for managed onboarding; 4 for online switch | Approval requires valid documents via model/service guards, not merely controller inspection. Approved status enables login; there is no independent Online presence flag. Managed account document completion is A06. |
| LOG-RIDER-002 | 2 | Rejection is scoped and prevents approved access, but no rider reason input/requirement or exact-reason notification; A07. |
| LOG-RIDER-003 | 2 | Suspension blocks login and preserves records. It does not return unpicked assignments or establish custody recovery; in-custody operations/remittance become inaccessible; A05/A09. There is no Online flag to toggle. |
| LOG-RIDER-004 | 3 for mandatory reason; 4 for unapproved length rule | Empty rider suspension reason is not rejected because none is collected. Ten-character minimum is not an approved policy; propose a documented reason requirement first. |
| LOG-RIDER-005 | 1 for controlled reactivation; 4 for Online | Suspended-to-approved transition exists and checks operational hub/capacity. It does not create a presence/availability setting. |
| LOG-RIDER-006 | 3 for useful workload visibility; 4 for fixed 0–3/Online assumptions | Roster search/status/documents exist, but no authoritative online state, active-job/today-completion counters or approved maximum-three policy. Reuse actual assignment states and resolve A08 before reporting workload. |
| LOG-RIDER-007 | 1 | Company-scoped lookup and Admin-only personnel management deny foreign IDs and Hub Staff management access. |

## L3 — Dispatch and assignment

Implementation: pickup approval, hub-specific eligible riders, conditional claims, `assignPickupRider/assignCourier`, rider self-accept, `HubTransferWorkflow` request/approval/dispatch, company vehicle eligibility, compensation snapshots. Final delivery requires Sorted; pickup requires approved pickup. Transfer batches have a distinct four-wheel rider/vehicle workflow.

| Workbook case | Classification | Current behavior and recommendation |
| --- | --- | --- |
| LOG-DISP-001 | 4 | No GPS-nearest automatic offers/60-second timeout. Existing FCFS/manual hub assignment is the approved working architecture; do not add an optimizer under a bug-fix label. |
| LOG-DISP-002 | 1 | Manual assignment records rider/assignment, notifies rider and updates order status history. Hub and company/vehicle checks apply. |
| LOG-DISP-003 | 1 for suspended/ineligible checks; 4 for Offline flag | Only approved eligible same-company/hub riders can be assigned; forged inactive assignments are rejected. Offline is not a modeled availability state. |
| LOG-DISP-004 | 4 | No approved maximum-three rule. Transfer conflict checking exists, but is not a three-job capacity system. A08 is a separate defect. |
| LOG-DISP-005 | 3 | No complete pre-pickup reassignment flow with both recipients' notifications/history. Existing claim endpoints deliberately do not overwrite assignments; A09. |
| LOG-DISP-006 | 1 | Post-pickup reassignment through the normal assign endpoints fails current-state/claim predicates. Future recovery must retain this custody protection. |
| LOG-DISP-007 | 4 for 3/2 capacity result; 3 only if bulk assignment approved | There is no five-parcel bulk final-delivery assignment with that skip policy. Transfer batch dispatch is a different implemented feature; do not duplicate it as per-parcel transfer compensation. |
| LOG-DISP-008 | 1 | Unready/unsorted parcels fail direct assignment predicates. Ready pickup additionally requires pickup approval and shipping prepayment when applicable. |

A08 and A09 matter more than GPS dispatch: terminal-state consistency and recovery are needed before capacity automation.

## L4 — Hub and warehouse operations

Implementation: scanner lookup, origin receipt, automatic locality sorting, ordered hub legs, requested/approved/dispatched batches, rider pickup confirmation, partial destination receipt, discrepancy history, phone scanner relay. `LogisticsAccess`, `HubTransferWorkflow` and locks reject unrelated hubs, companies, skipped legs and duplicate transitions. Admin physical receipt remains A01. Municipality/province routing is not a polygon warehouse-zone system.

| Workbook case | Classification | Current behavior and recommendation |
| --- | --- | --- |
| LOG-HUB-001 | 2 | Picked-up origin receipt records history/timestamp; local parcels become Sorted while transfer parcels remain at sorting center. It is not literally an AtHub state. Correct Admin receipt authority; do not break the existing local fast path. |
| LOG-HUB-002 | 1 for route locality checks; 4 for separate NCR sorting-zone model | Existing hub municipality/province and ordered route legs prevent unrelated-hub handling. No arbitrary NCR zone assignment UI is required by the approved regional-rate policy. |
| LOG-HUB-003 | 1 for staged outbound assignment; 4 for OTP | Delivery assignment follows sorting; rider alone starts Out for Delivery through normal progression. No buyer OTP workflow exists. Shared Delivered override still needs A02 correction. |
| LOG-HUB-004 | 1 for duplicate protection; 2 for message | Second inbound transition is rejected, not recorded again. Current error says the parcel has not been picked up rather than clearly saying it was already received. Improve message without weakening the state guard. |
| LOG-HUB-005 | 1 | Unknown tracking lookup does not create a shipment. |
| LOG-HUB-006 | 1 | Assignment/progression and transfer receipt require preceding sorting/dispatch/rider pickup evidence; out-of-order operations are rejected. |
| LOG-HUB-007 | 1 | Company and relevant-hub scoping reject foreign/unrelated parcels. Same municipality names in different provinces are distinguished. |

Phone relay is a lookup-input mechanism, not another status-writing authority. Keep server-side write authorization independent of scan input.

## L5 — Tracking and exceptions

Implementation: scoped history search, monitoring, order timeline, generic operational activity log, issue records/events with assigned hub/person and required resolution text. Financial discrepancies are separate financial records. Do not merge parcel damage into cash discrepancy resolution or create another transaction ledger.

| Workbook case | Classification | Current behavior and recommendation |
| --- | --- | --- |
| LOG-TRK-001 | 2 | History filters search tracking and order number using bound ORM values; not buyer name or arbitrary order primary key. Monitor is a different active list. No source evidence of SQL interpolation from the search string. Consolidate clear search/filter affordances; test injection-like input as literal data. |
| LOG-TRK-002 | 2 | Only allowed state transitions are accepted, but reason is nullable and physical delivery override is too broad; A02/A12. Do not satisfy this case merely by adding a reason to fabricated delivery. |
| LOG-TRK-003 | 2 | Actor/time/target history and transactional activity exist; generic operations do not always retain old/new/reason. There is no normal event edit/delete UI. Rich transfer/issue/finance events should be reused; absence of UI editing is not database-level immutability. |
| LOG-TRK-004 | 3 | No Lost shipment state or complete claim/refund escalation. Generic issues can record a problem; automatic refund or liability amounts need approved policy. |
| LOG-TRK-005 | 2 | Damaged issue kind and description exist, but mandatory photo, customer notification and complete resolution/claim flow do not; A10. |

## L6 — Return to sender

Implementation: delivery failure reason/history, retry assignment transition and direct Returned state. Buyer-initiated returns/refunds separately use `CommerceController::returnRequest/returnAction`; seller receipt restores selected items, Platform Admin records an actual refund reference. Finance blocks settlement for active returns/disputes and flags paid exceptions. These protections do not constitute a physical RTS workflow.

| Workbook case | Classification | Current behavior and recommendation |
| --- | --- | --- |
| LOG-RTS-001 | 3 for operational RTS queue/attempt history; 4 for automatic threshold | No automatic three-attempt/refusal RTS policy. Shipment stores latest failure reason and order history retains transition notes. Agree attempt counting, retry/return authorization and refusal handling before implementing automation. |
| LOG-RTS-002 | 3 | No assigned reverse journey and seller physical handover. Direct Returned transition is not proof goods reached the seller; A04. Workbook buyer Cancelled/seller awaiting-confirmation labels need an approved state mapping. |
| LOG-RTS-003 | 2 | Stock restoration exists but happens at the logistics Returned transition, not seller receipt. Buyer-return receipt has a separate item-restock flow. Review shared idempotent restock provenance before connecting them; this review does not assert a reproduced double-restock exploit. |
| LOG-RTS-004 | 3 for prepaid RTS financial review; 4 for automatic full-refund assumption | No RTS-triggered prepaid refund instruction. GCash order cancellation/release and buyer returns are separate paths. Preserve actual payment records and approve refund amount, funding and shipping treatment rather than assuming every RTS deserves the same full refund. |

Do not add automatic failed-delivery charges, deductions or payout offsets. Later returns do not erase valid completed rider compensation.

## L7 — Coverage and approved shipping rates

Implementation: operational hub coverage, geographic API validation, company destination-region approved rate revisions, first kilogram/additional started kilogram, original gross amount comparison, voucher issuer funding and immutable obligations. Checkout selects the company during seller preparation; early preflight can reject a definite all-carrier mismatch, but incomplete rates produce a warning and actual handoff still validates strictly. Buyer totals are never silently changed.

| Workbook case | Classification | Current behavior and recommendation |
| --- | --- | --- |
| LOG-RATE-001 | 1 for prospective approved revisions; 4 for automatic checkout repricing | New company rate does not replace seller checkout price or historical snapshots. New handoff must strictly match original gross shipping or stop for review. Approving this workbook expectation literally would reverse the approved pricing policy. |
| LOG-RATE-002 | 1 for exact money/negative/text validation; 4 for banning zero | Explicit approved zero shipping is supported. Negative/text values are invalid; centavos are integer parsed. Zero is not a validation bug. |
| LOG-RATE-003 | 1 for serviceability checks; 2 for timing limitation | Coverage is checked and new handoff cannot use an unsupported carrier. Full carrier-specific validation cannot always precede buyer payment with current post-payment carrier selection. Preserve the warning/strict handoff boundary; no guessed carrier. |
| LOG-RATE-004 | 2 for composite onboarding; 4 for zone-row-only expectation | Service requires operational geographic coverage, approved regional rate, applicable route and matching seller quote. Adding only a Batanes price/zone is insufficient. Present coverage/rate readiness together rather than introducing a parallel zone engine. |

Hub Staff does not manage company-approved rates or verify company GCash shipping receipts. Financial policy pages are Admin only.

## L8 — COD reconciliation and reports

Implementation: `CodCollection`, `LogisticsCashDesk`, `LogisticsTreasury`, `LogisticsShipping`, `CodSellerPayouts`, `RiderCompensation`, `LogisticsReconciliation` and their role-scoped controllers/views. COD collection, parcel status, remittance reservations, actual hub count, treasury custody, revenue entitlement, payout reservation, actual disbursement and acknowledgement remain separate. Payout ledger mirrors do not debit treasury twice.

| Workbook case | Classification | Current behavior and recommendation |
| --- | --- | --- |
| LOG-COD-001 | 1 | Exact remittance count plus explicit per-collection allocation yields verified cash and zero difference. Both collection records remain traceable. This is staff-attested physical cash, not provider confirmation. |
| LOG-COD-002 | 1 | Short count requires notes, preserves shortage/outstanding collection balance and discrepancy history. It does not deduct rider earnings or silently forgive the difference. |
| LOG-COD-003 | 4 for Admin physical confirmation; 1 for Hub Staff verification/Admin reconciliation | Approved workflow intentionally rejects Admin as physical cash receiver. Assigned Hub Staff counts; Admin reviews discrepancies/treasury/payouts. Update the workbook actor instead of broadening permissions. |
| LOG-COD-004 | 1 for scoped CSV; 2 for operational export consistency; 4 for mandatory XLSX | Finance CSV is audited, formula-safe, private/no-store, bounded and company scoped. Delivery CSV shares shipment filters but needs A12 privacy/audit consistency. XLSX is an optional format, not a missing ledger or permission. Finance current balances and activity rows intentionally have different date semantics. |
| LOG-COD-005 | 1 for existing CSV paths; 4 for XLSX | Existing CSV writers emit headers even without rows. No XLSX exporter exists. Keep empty exports explicit; never invent opening balances to populate reports. |

### Finance feature coverage beyond the original workbook

| Feature | Classification / review result | Relevant implementation |
| --- | --- | --- |
| Exact COD collection and atomic delivery | 1; operational override exception A02 | `CodCollection`, rider delivery, Shipment/Order guards |
| Partial remittance, allocation reservation, short/over count | 1 | `LogisticsCashDesk`; no automatic earning deduction |
| Hub custody and authorized transfers | 1 | Signed movements; authorization is not release; in-transit is not on-hand |
| Treasury COD provenance and receipt | 1 | `LogisticsTreasury`; staff release and actual Admin receipt remain distinct |
| Company shipping obligations and GCash proofs | 1, with manual/provider and post-payment carrier limitations | `LogisticsShipping`; exact gross entitlement, private proofs and global GCash reference ownership |
| Seller eligibility and disputes | 1 | Full verified COD, approved shipping basis, buyer confirmation/168 hours, return/complaint/appeal checks |
| Seller payout reservations, holds, sent proof and acknowledgement | 1 | `CodSellerPayouts`; positive additions separately funded; no offsets/commissions |
| Immutable rider rates/one trip earning | 1, but completion authority A01/A02 and state mismatch A08 | `LogisticsFinance`, `RiderCompensation`, full-batch completion |
| Separate payroll funding/payment | 1 | Verified `rider_payroll` contributions only; not seller COD or shipping receipts |
| Reconciliation and exports | 1 within documented operational boundary; A15 at scale | Consistent current snapshot, explicit buckets/transit/reservations and manual review |
| Platform voucher reimbursement | 4 for automatic payment; 1 for separate unresolved claims | Claims explicitly unverified/unpaid; not treasury funds or reduced shipping entitlement |
| Automated refunds/reversals, write-offs, bank matching | 3 for a future controlled correction process; 4 for silently adding financial policy | Current finalized errors require manual exception review. Approval needed before correction rules or fund movements. |
| Finance Officer/Manager role or second company Admin | 4 | Explicitly excluded. Keep existing single Admin and assigned Hub Staff structure. |

## Redundancy and UI/UX review

- **5 — Reuse existing Cash Desk.** Do not create an Admin cash-receiving page that duplicates Hub Staff verification. Admin reconciliation and staff physical counting are different responsibilities within the existing shared workspace.
- **5 — Reuse transaction sources.** Treasury movements and payout financial transactions represent different views of one operation. Do not sum their mirrored debits/receipts as additional cash.
- **5 — Reuse batch workflow.** Transfer queue, approval and batch receipt already exist. Legacy hub-request/assignment routes wrap the batch flow; retire aliases only after checking callers and links. Do not restore per-parcel transfer pay.
- **5 — Reuse personnel navigation.** Rider/Staff tabs and account detail forms already consolidate management. There is no need for another generic user-management module or finance-role personnel page.
- **2 — Clarify two report purposes.** Operational Reports/History show parcel activity; Finance Reconciliation shows money/custody/liabilities. Link between them and label their date basis. Merging totals without those distinctions would be misleading.
- **2 — Improve actionable status language.** Show pickup approval versus assigned pickup, accepted versus physically received, verified cash versus paid seller, and earned versus paid rider distinctly. Do not introduce a generic Paid badge covering all of them.
- **2 — Complete managed onboarding.** A polished account-created message is misleading if required approval documents cannot subsequently be supplied. Surface missing requirements and a real completion route.
- **2 — Improve card navigation/search/error wording.** Dashboard metric links should match card populations; tracking search scope should be explicit; repeated scan errors should explain current custody. Fix literal question-mark separators in dashboard copy.
- **1 — Preserve the current Finance hierarchy.** Admin: Overview & Settings, Seller Settlements, Rider Earnings & Payouts, Cash Desk, Reconciliation & Reports. Hub Staff: My Hub/Operations including own Cash Desk, without Finance management. Rider: Earnings & Payouts and Cash Remittances. Seller: buyer payments, outgoing shipping payments and incoming COD payouts are separate.

Existing shared finance CSS uses responsive cards, restrained pink actions, label associations and scrollable tables. Current navigation render tests passed. This review did not rerun viewport screenshots, contrast measurement, keyboard/screen-reader or real-device scanning; do not treat source styling or prior screenshots as complete accessibility acceptance.

## Hiring and rider-workbook failure analysis

### Current rerun

Command: `php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --filter 'HubHiringTest|RiderWorkbookTest|LogisticsAuthorizationTest|HubTransferWorkflowTest|LogisticsReconciliationTest|RiderCompensationTest|NavigationTest' --log-junit storage/app/private/logistics-ui-review/module-review-tests.xml`

PHPUnit's configuration uses `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, array sessions/cache and test mail. No production migration or financial operation was executed.

| Suite | Passed | Failures | Errors | Skipped |
| --- | ---: | ---: | ---: | ---: |
| LogisticsAuthorizationTest | 36 | 0 | 0 | 0 |
| HubTransferWorkflowTest | 16 | 0 | 0 | 0 |
| LogisticsReconciliationTest | 19 | 0 | 0 | 0 |
| RiderCompensationTest | 23 | 0 | 0 | 0 |
| NavigationTest | 4 | 0 | 0 | 0 |
| HubHiringTest | 9 | 9 | 2 | 0 |
| RiderWorkbookTest | 7 | 13 | 0 | 27 |
| **Total** | **114** | **22** | **2** | **27** |

165 tests, 2,474 assertions. Passing current tests does not prove the stricter requested Admin physical-handover policy: several existing flows/tests intentionally allow Admin receipt and must be revised after approval.

### HubHiringTest

Nine failures occur before the intended behavior because shared public registration data omits required region and uses `FixturePassword123` without a required symbol. They are:

- `test_staff_requires_interview_and_records_each_decision`
- `test_staff_early_rejection_requires_reason_and_preserves_reapplication`
- `test_switches_are_independent_and_audited_without_changing_legacy_value`
- `test_staff_switch_does_not_block_rider_or_change_other_settings`
- `test_only_approved_assigned_role_consumes_slots_and_zero_blocks_intake`
- `test_reapplication_preserves_account_id_rejection_and_attempt_history`
- `test_history_failure_rolls_back_account_and_uploaded_files`
- `test_staff_fallback_ownership_and_vehicle_rules_remain_enforced`
- `test_shared_registration_preserves_rejected_buyer_records_without_hiring_restrictions`

Two errors are downstream `ModelNotFoundException` lookups after account creation fails: `test_generic_staff_creation_and_edits_cannot_establish_recruitment_states` and `test_management_creation_status_changes_and_moves_enforce_capacity`. The managed creation path validates barangay via `HubAddress`; these tests prevent stray HTTP but supply no controlled geography hierarchy. Assert the creation response first and install controlled provider fixtures before evaluating downstream state.

Recommended test-only repair: valid region/password and controlled region/province/municipality/barangay responses with stray-request blocking retained. Preserve interview, document, capacity, role, mobile, identity-lock and audit-failure guards. These failures are not evidence that hiring limits or transactional history are broken.

### RiderWorkbookTest

| Case(s) currently failing | Fixture/expectation issue versus production finding |
| --- | --- |
| TC-RID-001 | Multi-word name is rejected by current regex. Pending activation/email invitation is not the implemented pending approval flow. Name usability needs a policy decision; activation is not an existing feature to pretend passed. |
| TC-RID-003 | Geography failure masks intended required-field assertions. Separately, managed creation really omits licence number, plate and document fields: A06. Approval still checks documents through HubHiring. OR/CR applicability also depends on own versus company vehicle. |
| TC-RID-005 | Accepted name-boundary attempts hit uncontrolled geography; the additional multi-word name also conflicts with current validation. Fix isolation and review the real-name policy separately. |
| TC-RID-006, 010, 014 | Intended accepted age/mobile/licence-expiry boundaries are blocked by uncontrolled geography. The failure does not prove those boundary validators are defective. |
| TC-RID-007 | An expected email-error key is missing while address validation fails. The generic email rule does not necessarily enforce every workbook domain example. Isolate email expectations before deciding to impose stronger rules. |
| TC-RID-012, 013, 015 | Geography masks licence/plate assertions. Managed accountData does not validate/persist those submitted fields, so creation-time format/uniqueness expectations are also genuine onboarding gaps. Public HubHiring registration separately locks/checks those identifiers; do not claim global uniqueness is wholly absent. |
| TC-RID-028 | Initial creation fails geography before roster/isolation assertions. Legacy `/logistics/accounts` is an alias rather than a separate authoritative roster. Check the current personnel destination after fixing the fixture. |
| TC-RID-029 | Protected accounts route redirects to login as expected. Public `/register/rider` intentionally returns 200; expecting 404 contradicts existing public recruitment. This is not an authorization bypass. |
| TC-RID-032 | Initial creation fails geography before rider-versus-Admin access assertions. Exhaustive authorization tests independently pass; rerun the intended rider assertion with a controlled fixture. |

27 cases are explicitly skipped: document controls absent from Admin Create Rider, unimplemented emailed activation, and phone/UI/device/network requirements. These are **not passes**. Public application uploads are real but are not the Admin-managed creation controls those cases target. Correct the workbook's approved workflow mapping and then execute applicable device cases separately.

No test or production validation was edited during this review. Fixture repairs alone will not resolve A01–A15 or make every original workbook expectation appropriate.

## Evidence map for follow-up

| Area | Files/functions inspected |
| --- | --- |
| Routes and guards | `routes/web.php`; `LogisticsMiddleware`, `LogisticsAdminMiddleware`, `RiderMiddleware`, `LogisticsActivity`, `MediaConsistency` |
| Company/hub/rider scope | `LogisticsAccess`; `LogisticsCompany`; `User`; `LogisticsHub`; `CompanyVehicle` |
| Operational states | `LogisticsController`; `RiderController`; `Shipment`; `DeliveryAssignment`; `ShipmentHubLeg`; `HubTransferBatch`; `HubTransferWorkflow`; `HubTransferController` |
| Personnel and issues | `LogisticsManagementController`; `HubHiring`; `RegisterController`; `LogisticsIssue`; account/rider/staff/issues views |
| COD and cash | `CodCollection`; `LogisticsCashDesk`; `LogisticsCashDeskController`; cash-desk view |
| Shipping | `LogisticsShipping`; `BuyerCommerce`; `GcashWorkflow`; shipping/Finance controllers and views |
| Treasury and payouts | `LogisticsTreasury`; `CodSellerPayouts`; `RiderCompensation`; seller/rider payout controllers and workspaces; private evidence endpoint |
| Returns and disputes | `CommerceController::returnAction`; `BuyerController::confirmReceipt`; `Complaint`; `Order::restoreStock`; settlement eligibility |
| Reports | `LogisticsReconciliation`; reconciliation controller/view; `filteredShipments/reports/exportReport`; activity view |
| UI/media | Role sidebars; logistics dashboard/monitor/history/scan/transfer/personnel views; `public/css/logistics-cash-desk.css`; `components/admin-doc-thumb.blade.php`; `MediaLifecycle`; `config/filesystems.php` |
| Schema | Existing workflow/hiring migrations and Phase 1–6 additive financial migrations; Phase 7 uses those sources without an additional schema |

## Proposed approval sequence — no changes made

1. **Critical security/custody package:** A01–A03. Approve private sensitive-document delivery and strict assigned-Hub-Staff/rider physical attestation. Decide how an unavailable staff member is handled without adding roles or impersonation. Add negative tests for Admin physical receipt and prepaid delivery overrides before changing routes/services.
2. **Important operational package:** A05–A09 and A12–A13. Complete managed onboarding, reasons/history, custody recovery, safe unpicked reassignment, terminal-state consistency and export privacy. Preserve all financial snapshots and existing staff cash-verification authority.
3. **Structural/financial design approval:** A04, A10 and A11. Agree RTS custody/attempt rules, seller receipt/restock evidence, lost/damaged claim/refund funding and single-company-owner enforcement. Present migrations/state transitions and legacy treatment for approval before implementation. Do not silently adopt the workbook's three attempts, full-refund, GPS, OTP, three-job, commission or alternative rate assumptions.
4. **Optional UI/reporting package:** A14–A15, accessible viewport/device checks, clearer filter links, workload visibility, reporting performance and optional XLSX. Reuse existing sections and authoritative ledgers.

Release readiness: core finance and tested company/hub scoping are credible within their manual-verification boundary, but the module is **not ready to declare fully compliant with this review's custody/security requirements** until A01–A03 and the assigned-work recovery gaps are addressed. The workbook remains Not Run. No new role, structural change or financial policy has been approved by this report.

## Subsequent approved implementation

A01–A03 were subsequently authorized and implemented; see [Critical logistics security and custody package](critical-logistics-security-package.md) for code changes, 319 passing regression tests, real MySQL receipt/finance races, UI verification and legacy document migration. Ten pre-existing missing document files remain for manual review. The audit classifications above describe the pre-change review; workbook execution and A04–A15 remain separate. No emergency receipt/delivery bypass was enabled.
